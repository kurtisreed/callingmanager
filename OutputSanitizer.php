<?php
/**
 * Output Sanitization Class
 * Prevents XSS attacks by properly encoding output data
 */

class OutputSanitizer {
    
    /**
     * Sanitize string for HTML output
     * 
     * @param string $data Data to sanitize
     * @param bool $preserveNewlines Whether to convert newlines to <br> tags
     * @return string Sanitized data
     */
    public static function html($data, $preserveNewlines = false) {
        if ($data === null) {
            return '';
        }
        
        $sanitized = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        if ($preserveNewlines) {
            $sanitized = nl2br($sanitized);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize string for HTML attribute output
     * 
     * @param string $data Data to sanitize
     * @return string Sanitized data
     */
    public static function attribute($data) {
        if ($data === null) {
            return '';
        }
        
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Sanitize string for JavaScript output (inside script tags)
     * 
     * @param string $data Data to sanitize
     * @return string Sanitized data
     */
    public static function javascript($data) {
        if ($data === null) {
            return '""';
        }
        
        // Escape for JavaScript context
        $data = str_replace(['\\', '"', "'", "\n", "\r", "\t"], 
                          ['\\\\', '\\"', "\\'", '\\n', '\\r', '\\t'], 
                          $data);
        
        return '"' . $data . '"';
    }
    
    /**
     * Sanitize data for JSON output
     * 
     * @param mixed $data Data to sanitize
     * @param int $flags JSON flags
     * @return string JSON-encoded sanitized data
     */
    public static function json($data, $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) {
        return json_encode($data, $flags | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Sanitize data for URL context
     * 
     * @param string $data Data to sanitize
     * @return string URL-encoded data
     */
    public static function url($data) {
        if ($data === null) {
            return '';
        }
        
        return urlencode($data);
    }
    
    /**
     * Sanitize data for CSS context
     * 
     * @param string $data Data to sanitize
     * @return string Sanitized data
     */
    public static function css($data) {
        if ($data === null) {
            return '';
        }
        
        // Remove potentially dangerous characters
        return preg_replace('/[^a-zA-Z0-9\-_#\.\s%]/', '', $data);
    }
    
    /**
     * Sanitize array of data for HTML output
     * 
     * @param array $data Array to sanitize
     * @param bool $preserveNewlines Whether to preserve newlines
     * @return array Sanitized array
     */
    public static function htmlArray($data, $preserveNewlines = false) {
        if (!is_array($data)) {
            return self::html($data, $preserveNewlines);
        }
        
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitizedKey = self::html($key);
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = self::htmlArray($value, $preserveNewlines);
            } else {
                $sanitized[$sanitizedKey] = self::html($value, $preserveNewlines);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Clean HTML by allowing only safe tags
     * 
     * @param string $html HTML to clean
     * @param array $allowedTags Allowed HTML tags
     * @return string Cleaned HTML
     */
    public static function cleanHtml($html, $allowedTags = ['p', 'br', 'strong', 'em', 'u']) {
        if ($html === null) {
            return '';
        }
        
        // Convert allowed tags to format strip_tags expects
        $allowedTagsString = '<' . implode('><', $allowedTags) . '>';
        
        // Strip all tags except allowed ones
        $cleaned = strip_tags($html, $allowedTagsString);
        
        // Additional cleaning - remove javascript: and other dangerous protocols
        $cleaned = preg_replace('/javascript:/i', '', $cleaned);
        $cleaned = preg_replace('/vbscript:/i', '', $cleaned);
        $cleaned = preg_replace('/onload=/i', '', $cleaned);
        $cleaned = preg_replace('/onerror=/i', '', $cleaned);
        $cleaned = preg_replace('/onclick=/i', '', $cleaned);
        
        return $cleaned;
    }
    
    /**
     * Sanitize filename for safe file operations
     * 
     * @param string $filename Filename to sanitize
     * @return string Safe filename
     */
    public static function filename($filename) {
        if ($filename === null) {
            return '';
        }
        
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Allow only alphanumeric, dots, hyphens, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $filename);
        
        // Prevent hidden files and multiple extensions
        $filename = ltrim($filename, '.');
        
        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * Sanitize data based on context
     * 
     * @param mixed $data Data to sanitize
     * @param string $context Context (html, attribute, javascript, json, url, css)
     * @param array $options Additional options
     * @return mixed Sanitized data
     */
    public static function sanitize($data, $context = 'html', $options = []) {
        switch ($context) {
            case 'html':
                return self::html($data, $options['preserve_newlines'] ?? false);
                
            case 'attribute':
                return self::attribute($data);
                
            case 'javascript':
                return self::javascript($data);
                
            case 'json':
                return self::json($data, $options['flags'] ?? JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                
            case 'url':
                return self::url($data);
                
            case 'css':
                return self::css($data);
                
            case 'filename':
                return self::filename($data);
                
            case 'clean_html':
                return self::cleanHtml($data, $options['allowed_tags'] ?? ['p', 'br', 'strong', 'em', 'u']);
                
            default:
                return self::html($data);
        }
    }
}

/**
 * Helper functions for common sanitization tasks
 */

/**
 * Quick HTML sanitization
 */
function e($data, $preserveNewlines = false) {
    return OutputSanitizer::html($data, $preserveNewlines);  
}

/**
 * Quick attribute sanitization
 */
function attr($data) {
    return OutputSanitizer::attribute($data);
}

/**
 * Quick JSON sanitization
 */
function jsonSafe($data) {
    return OutputSanitizer::json($data);
}
?>