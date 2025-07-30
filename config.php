<?php
/**
 * Configuration loader for environment variables
 * Loads environment variables from .env file
 */

class Config {
    private static $env = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load() {
        $envFile = __DIR__ . '/.env';
        
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found. Please create it from .env.example');
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }
                
                self::$env[$key] = $value;
                
                // Also set as PHP environment variable
                putenv("$key=$value");
            }
        }
    }
    
    /**
     * Get environment variable value
     * 
     * @param string $key The environment variable key
     * @param mixed $default Default value if key not found
     * @return mixed The environment variable value
     */
    public static function get($key, $default = null) {
        // First check our loaded env array
        if (isset(self::$env[$key])) {
            return self::$env[$key];
        }
        
        // Then check PHP environment
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Finally check $_ENV superglobal
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        return $default;
    }
    
    /**
     * Check if environment variable exists
     * 
     * @param string $key The environment variable key
     * @return bool
     */
    public static function has($key) {
        return isset(self::$env[$key]) || getenv($key) !== false || isset($_ENV[$key]);
    }
}

// Load environment variables when this file is included
try {
    Config::load();
} catch (Exception $e) {
    // In production, you might want to log this error instead
    die('Configuration Error: ' . $e->getMessage());
}
?>