<?php
// Require authentication for this endpoint
require_once __DIR__ . '/auth_required.php';

// Log this access for auditing
logUserActivity('reconcile_members', ['risk_level' => 'medium', 'file' => 'reconcile_members.php']);

header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['pdf_members'])) {
        throw new Exception('Invalid input data');
    }
    
    $pdfMembers = $input['pdf_members'];
    $debugInfo = $input['debug_info'] ?? [];
    
    // Get current members from database
    $currentMembers = getCurrentMembers($conn);
    
    // Add debug information
    $debug = array_merge($debugInfo, [
        'current_members_count' => count($currentMembers),
        'pdf_sample' => array_slice($pdfMembers, 0, 5),
        'current_sample' => array_slice($currentMembers, 0, 5),
        'matching_attempts' => []
    ]);
    
    // Reconcile members
    $reconciliation = reconcileMembers($pdfMembers, $currentMembers, $debug);
    
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
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
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

function reconcileMembers($pdfMembers, $currentMembers, &$debug) {
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
        
        // Require minimum confidence for a match
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
    
    return $score / $maxScore;
}

$conn->close();
?>