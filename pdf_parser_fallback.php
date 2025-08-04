<?php
/**
 * PDF Parser Fallback for Shared Hosting
 * Uses a simple PHP-based approach when pdftotext is not available
 */

function parsePDFWithPHP($filepath) {
    $members = [];
    
    try {
        // Read PDF file as binary
        $pdfContent = file_get_contents($filepath);
        
        if (!$pdfContent) {
            throw new Exception('Could not read PDF file');
        }
        
        // Extract text using simple regex patterns
        // This is a basic approach that works with simple PDFs
        $text = extractTextFromPDF($pdfContent);
        
        if (empty($text)) {
            // Try alternative extraction method
            $text = extractTextAlternative($pdfContent);
        }
        
        if (!empty($text)) {
            $members = parseTextContent($text);
        } else {
            throw new Exception('Could not extract text from PDF. The PDF may be image-based or encrypted.');
        }
        
    } catch (Exception $e) {
        // If PHP parsing fails, return empty array with error message
        error_log("PDF parsing failed: " . $e->getMessage());
        throw new Exception('PDF parsing failed: ' . $e->getMessage());
    }
    
    return $members;
}

function extractTextFromPDF($pdfContent) {
    $text = '';
    
    // Look for text streams in the PDF
    if (preg_match_all('/BT\s+(.*?)\s+ET/s', $pdfContent, $matches)) {
        foreach ($matches[1] as $textBlock) {
            // Extract text from PDF text commands
            if (preg_match_all('/\[(.*?)\]/s', $textBlock, $textMatches)) {
                foreach ($textMatches[1] as $textMatch) {
                    $text .= $textMatch . ' ';
                }
            }
            
            // Also look for Tj commands
            if (preg_match_all('/\((.*?)\)\s*Tj/s', $textBlock, $tjMatches)) {
                foreach ($tjMatches[1] as $tjMatch) {
                    $text .= $tjMatch . ' ';
                }
            }
        }
    }
    
    // Clean up extracted text
    $text = str_replace(['\\(', '\\)', '\\n', '\\r'], ['(', ')', "\n", "\r"], $text);
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

function extractTextAlternative($pdfContent) {
    $text = '';
    
    // Alternative method: look for stream objects
    if (preg_match_all('/stream\s+(.*?)\s+endstream/s', $pdfContent, $matches)) {
        foreach ($matches[1] as $stream) {
            // Try to decode if it's not compressed
            if (strpos($stream, '/FlateDecode') === false) {
                // Look for readable text patterns
                if (preg_match_all('/[A-Za-z]{2,}/', $stream, $textMatches)) {
                    $text .= implode(' ', $textMatches[0]) . ' ';
                }
            }
        }
    }
    
    // Look for any readable text in the PDF
    if (empty($text)) {
        // Simple extraction of potential names and dates
        if (preg_match_all('/[A-Z][a-z]+(?:\s+[A-Z][a-z]*)*,?\s*[A-Z][a-z]+(?:\s+[A-Z][a-z]*)*\s+\d{1,2}\/\d{1,2}\/\d{4}/', $pdfContent, $matches)) {
            $text = implode("\n", $matches[0]);
        }
    }
    
    return trim($text);
}

// Alternative: If the above methods don't work, suggest using a web service
function suggestWebService() {
    return [
        'error' => true,
        'message' => 'PDF parsing failed with local methods. Consider using a web service like PDF.co or uploading a text-based PDF.',
        'suggestion' => 'For complex PDFs, you may need to convert the PDF to text manually or use an online PDF-to-text converter.'
    ];
}

/**
 * Validate if extracted content looks like member data
 */
function validateMemberData($members) {
    if (empty($members)) {
        return false;
    }
    
    // Check if at least 50% of entries have valid names and birthdates
    $validCount = 0;
    foreach ($members as $member) {
        if (!empty($member['first_name']) && 
            !empty($member['last_name']) && 
            !empty($member['birthdate']) &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $member['birthdate'])) {
            $validCount++;
        }
    }
    
    return ($validCount / count($members)) >= 0.5;
}
?>