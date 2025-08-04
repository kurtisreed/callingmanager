<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('process_member_pdf', ['risk_level' => 'medium', 'file' => 'process_member_pdf.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Check if file was uploaded
    if (!isset($_FILES['member_pdf']) || $_FILES['member_pdf']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No PDF file uploaded or upload error occurred');
    }

    $uploadedFile = $_FILES['member_pdf'];
    
    // Validate file type
    if ($uploadedFile['type'] !== 'application/pdf') {
        throw new Exception('Only PDF files are allowed');
    }
    
    // Validate file size (max 10MB for shared hosting)
    if ($uploadedFile['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum 10MB allowed');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $filename = 'member_list_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.pdf';
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($uploadedFile['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Parse PDF content
    $pdfMembers = parsePDFMembers($filepath);
    
    // Get current members from database
    $currentMembers = getCurrentMembers($conn);
    
    // Add debugging info
    $debug = [
        'pdf_members_found' => count($pdfMembers),
        'current_members_count' => count($currentMembers),
        'pdf_sample' => array_slice($pdfMembers, 0, 5), // First 5 PDF members
        'current_sample' => array_slice($currentMembers, 0, 5), // First 5 current members
        'matching_attempts' => []
    ];
    
    // Reconcile members
    $reconciliation = reconcileMembers($pdfMembers, $currentMembers);
    
    // Clean up uploaded file
    unlink($filepath);
    
    // Return reconciliation results
    echo json_encode([
        'success' => true,
        'reconciliation' => $reconciliation,
        'stats' => [
            'new_members' => count($reconciliation['new_members']),
            'updates' => count($reconciliation['updates']),
            'no_changes' => count($reconciliation['no_changes']),
            'removed' => count($reconciliation['removed'])
        ],
        'debug' => $debug
    ]);

} catch (Exception $e) {
    // Clean up file if it exists
    if (isset($filepath) && file_exists($filepath)) {
        unlink($filepath);
    }
    
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

function parsePDFMembers($filepath) {
    global $debug;
    $members = [];
    $extractedText = '';
    
    // Method 1: Try pdftotext command
    $textFile = $filepath . '.txt';
    $command = "pdftotext -layout '$filepath' '$textFile' 2>/dev/null";
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($textFile)) {
        $extractedText = file_get_contents($textFile);
        unlink($textFile);
        $debug['extraction_method'] = 'pdftotext_command';
    } else {
        // Method 2: Try alternative pdftotext command
        $command2 = "pdftotext '$filepath' '$textFile' 2>/dev/null";
        exec($command2, $output2, $returnCode2);
        
        if ($returnCode2 === 0 && file_exists($textFile)) {
            $extractedText = file_get_contents($textFile);
            unlink($textFile);
            $debug['extraction_method'] = 'pdftotext_simple';
        } else {
            // Method 3: Fall back to PHP PDF parser
            require_once 'pdf_parser_fallback.php';
            try {
                $members = parsePDFWithPHP($filepath);
                $debug['extraction_method'] = 'php_parser';
                return $members;
            } catch (Exception $e) {
                $debug['extraction_method'] = 'failed';
                $debug['extraction_error'] = $e->getMessage();
            }
        }
    }
    
    // If we got text, try to parse it
    if (!empty($extractedText)) {
        $debug['extracted_text_length'] = strlen($extractedText);
        $debug['text_sample'] = substr($extractedText, 0, 500); // First 500 chars
        $members = parseTextContent($extractedText);
    }
    
    $debug['parsed_members_count'] = count($members);
    
    return $members;
}

function parseTextContent($text) {
    global $debug;
    $members = [];
    $lines = explode("\n", $text);
    
    $debug['total_lines'] = count($lines);
    $debug['sample_lines'] = array_slice($lines, 0, 10);
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line) || strlen($line) < 10) continue;
        
        // Pattern 1: "Last, First Middle    MM/DD/YYYY"
        if (preg_match('/^([A-Za-z\s\-\'\.]+),\s*([A-Za-z\s\-\'\.]+)\s+(\d{1,2}\/\d{1,2}\/\d{4})/', $line, $matches)) {
            $member = extractMemberFromMatches($matches);
            if ($member) $members[] = $member;
            continue;
        }
        
        // Pattern 2: "Last, First    MM/DD/YYYY" (no middle name)
        if (preg_match('/^([A-Za-z\s\-\'\.]+),\s*([A-Za-z\-\'\.]+)\s+(\d{1,2}\/\d{1,2}\/\d{4})/', $line, $matches)) {
            $member = extractMemberFromMatches($matches);
            if ($member) $members[] = $member;
            continue;
        }
        
        // Pattern 3: "First Last    MM/DD/YYYY" (no comma)
        if (preg_match('/^([A-Za-z\-\'\.]+)\s+([A-Za-z\s\-\'\.]+)\s+(\d{1,2}\/\d{1,2}\/\d{4})/', $line, $matches)) {
            $firstName = trim($matches[1]);
            $lastPart = trim($matches[2]);
            $birthdate = $matches[3];
            
            // Split last part to get last name (take last word as last name)
            $nameParts = explode(' ', $lastPart);
            $lastName = array_pop($nameParts);
            
            $member = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birthdate' => convertDateToMysql($birthdate),
                'status' => 'active'
            ];
            
            if ($member['birthdate']) $members[] = $member;
            continue;
        }
        
        // Pattern 4: Look for any line with a date pattern
        if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $line, $dateMatches)) {
            // Try to extract names before the date
            $beforeDate = trim(str_replace($dateMatches[0], '', $line));
            if (!empty($beforeDate) && preg_match('/([A-Za-z\-\'\.]+)[\s,]+([A-Za-z\s\-\'\.]+)/', $beforeDate, $nameMatches)) {
                $member = [
                    'first_name' => trim($nameMatches[1]),
                    'last_name' => trim($nameMatches[2]),
                    'birthdate' => convertDateToMysql($dateMatches[1]),
                    'status' => 'active'
                ];
                
                if ($member['birthdate']) $members[] = $member;
            }
        }
    }
    
    return $members;
}

function extractMemberFromMatches($matches) {
    $lastName = trim($matches[1]);
    $firstMiddle = trim($matches[2]);
    $birthdate = $matches[3];
    
    // Just take the first name (ignore middle names)
    $nameParts = explode(' ', $firstMiddle);
    $firstName = $nameParts[0];
    
    $mysqlDate = convertDateToMysql($birthdate);
    if ($mysqlDate) {
        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birthdate' => $mysqlDate,
            'status' => 'active'
        ];
    }
    
    return null;
}

function convertDateToMysql($dateString) {
    // Try different date formats
    $formats = ['m/d/Y', 'n/j/Y', 'm/d/y', 'n/j/y'];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);
        if ($date) {
            return $date->format('Y-m-d');
        }
    }
    
    return null;
}

function getCurrentMembers($conn) {
    $sql = "SELECT member_id, first_name, last_name, birthdate, status, status_notes 
            FROM members 
            ORDER BY last_name, first_name";
    
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Failed to fetch current members: " . $conn->error);
    }
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    return $members;
}

function reconcileMembers($pdfMembers, $currentMembers) {
    global $debug;
    
    $reconciliation = [
        'new_members' => [],
        'updates' => [],
        'no_changes' => [],
        'removed' => []
    ];
    
    $matchedCurrentMembers = [];
    
    // Process each PDF member
    foreach ($pdfMembers as $index => $pdfMember) {
        $match = findMemberMatch($pdfMember, $currentMembers);
        
        // Add debugging for first few attempts
        if ($index < 5) {
            $debug['matching_attempts'][] = [
                'pdf_member' => $pdfMember,
                'match_found' => $match ? true : false,
                'match_details' => $match ? [
                    'member_id' => $match['member_id'],
                    'name' => $match['first_name'] . ' ' . $match['last_name'],
                    'birthdate' => $match['birthdate']
                ] : null
            ];
        }
        
        if ($match) {
            $matchedCurrentMembers[] = $match['member_id'];
            
            // Check if updates are needed
            $changes = [];
            
            if (trim($match['first_name']) !== trim($pdfMember['first_name'])) {
                $changes['first_name'] = [
                    'old' => $match['first_name'],
                    'new' => $pdfMember['first_name']
                ];
            }
            
            if (trim($match['last_name']) !== trim($pdfMember['last_name'])) {
                $changes['last_name'] = [
                    'old' => $match['last_name'],
                    'new' => $pdfMember['last_name']
                ];
            }
            
            if ($match['birthdate'] !== $pdfMember['birthdate']) {
                $changes['birthdate'] = [
                    'old' => $match['birthdate'],
                    'new' => $pdfMember['birthdate']
                ];
            }
            
            // Status logic: if member is in PDF, they should be active
            if ($match['status'] !== 'active') {
                $changes['status'] = [
                    'old' => $match['status'],
                    'new' => 'active'
                ];
            }
            
            if (!empty($changes)) {
                $reconciliation['updates'][] = [
                    'member_id' => $match['member_id'],
                    'current' => $match,
                    'pdf_data' => $pdfMember,
                    'changes' => $changes,
                    'confidence' => calculateMatchConfidence($pdfMember, $match)
                ];
            } else {
                $reconciliation['no_changes'][] = [
                    'member_id' => $match['member_id'],
                    'current' => $match,
                    'confidence' => calculateMatchConfidence($pdfMember, $match)
                ];
            }
        } else {
            // New member
            $reconciliation['new_members'][] = $pdfMember;
        }
    }
    
    // Find members not in PDF (potentially removed/moved away)
    foreach ($currentMembers as $currentMember) {
        if (!in_array($currentMember['member_id'], $matchedCurrentMembers) 
            && $currentMember['status'] === 'active') {
            $reconciliation['removed'][] = $currentMember;
        }
    }
    
    return $reconciliation;
}

function findMemberMatch($pdfMember, $currentMembers) {
    $bestMatch = null;
    $bestScore = 0;
    
    foreach ($currentMembers as $currentMember) {
        $score = calculateMatchConfidence($pdfMember, $currentMember);
        
        // Lower confidence threshold and add debugging
        if ($score >= 0.6 && $score > $bestScore) {
            $bestMatch = $currentMember;
            $bestScore = $score;
        }
    }
    
    return $bestMatch;
}

function calculateMatchConfidence($pdfMember, $currentMember) {
    $score = 0;
    $maxScore = 0;
    
    // Birthdate match (most important)
    $maxScore += 40;
    if ($pdfMember['birthdate'] === $currentMember['birthdate']) {
        $score += 40;
    }
    
    // Last name match
    $maxScore += 30;
    $lastNameSimilarity = similar_text(
        strtolower(trim($pdfMember['last_name'])), 
        strtolower(trim($currentMember['last_name'])),
        $percent
    );
    $score += ($percent / 100) * 30;
    
    // First name match
    $maxScore += 30;
    $firstNameSimilarity = similar_text(
        strtolower(trim($pdfMember['first_name'])), 
        strtolower(trim($currentMember['first_name'])),
        $percent
    );
    $score += ($percent / 100) * 30;
    
    // Remove middle name matching since we don't have that column
    
    return $score / $maxScore;
}

$conn->close();
?>