<?php
/**
 * Input Validation Class
 * Provides comprehensive input validation and sanitization
 */

class InputValidator {
    private $errors = [];
    private $rules = [];
    
    /**
     * Validation rule constants
     */
    const REQUIRED = 'required';
    const STRING = 'string';
    const INTEGER = 'integer';
    const FLOAT = 'float';
    const EMAIL = 'email';
    const DATE = 'date';
    const ALPHA = 'alpha';
    const ALPHANUMERIC = 'alphanumeric';
    const PHONE = 'phone';
    const MIN_LENGTH = 'min_length';
    const MAX_LENGTH = 'max_length';
    const IN_ARRAY = 'in_array';
    const REGEX = 'regex';
    const BOOLEAN = 'boolean';
    
    /**
     * Add validation rule for a field
     * 
     * @param string $field Field name
     * @param mixed $rule Rule or array of rules
     * @param mixed $value Rule value (for parameterized rules)
     * @return self
     */
    public function rule($field, $rule, $value = null) {
        if (!isset($this->rules[$field])) {
            $this->rules[$field] = [];
        }
        
        if (is_array($rule)) {
            $this->rules[$field] = array_merge($this->rules[$field], $rule);
        } else {
            $this->rules[$field][] = $value !== null ? [$rule, $value] : $rule;
        }
        
        return $this;
    }
    
    /**
     * Validate input data against defined rules
     * 
     * @param array $data Input data to validate
     * @return bool True if validation passes
     */
    public function validate($data) {
        $this->errors = [];
        
        foreach ($this->rules as $field => $rules) {
            $value = isset($data[$field]) ? $data[$field] : null;
            
            foreach ($rules as $rule) {
                if (is_array($rule)) {
                    $ruleName = $rule[0];
                    $ruleValue = $rule[1];
                } else {
                    $ruleName = $rule;
                    $ruleValue = null;
                }
                
                if (!$this->validateRule($field, $value, $ruleName, $ruleValue)) {
                    break; // Stop validating this field on first error
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate a single rule
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule name
     * @param mixed $ruleValue Rule parameter
     * @return bool
     */
    private function validateRule($field, $value, $rule, $ruleValue = null) {
        switch ($rule) {
            case self::REQUIRED:
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $this->addError($field, "Field '$field' is required");
                    return false;
                }
                break;
                
            case self::STRING:
                if (!is_string($value) && !is_null($value)) {
                    $this->addError($field, "Field '$field' must be a string");
                    return false;
                }
                break;
                
            case self::INTEGER:
                if (!is_null($value) && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "Field '$field' must be an integer");
                    return false;
                }
                break;
                
            case self::FLOAT:
                if (!filter_var($value, FILTER_VALIDATE_FLOAT) && !is_null($value)) {
                    $this->addError($field, "Field '$field' must be a number");
                    return false;
                }
                break;
                
            case self::EMAIL:
                if (!filter_var($value, FILTER_VALIDATE_EMAIL) && !empty($value)) {
                    $this->addError($field, "Field '$field' must be a valid email address");
                    return false;
                }
                break;
                
            case self::DATE:
                if (!empty($value) && !$this->isValidDate($value)) {
                    $this->addError($field, "Field '$field' must be a valid date (YYYY-MM-DD)");
                    return false;
                }
                break;
                
            case self::ALPHA:
                if (!empty($value) && !ctype_alpha(str_replace(' ', '', $value))) {
                    $this->addError($field, "Field '$field' must contain only letters and spaces");
                    return false;
                }
                break;
                
            case self::ALPHANUMERIC:
                if (!empty($value) && !ctype_alnum(str_replace([' ', '-', '_'], '', $value))) {
                    $this->addError($field, "Field '$field' must contain only letters, numbers, spaces, hyphens, and underscores");
                    return false;
                }
                break;
                
            case self::MIN_LENGTH:
                if (!empty($value) && strlen($value) < $ruleValue) {
                    $this->addError($field, "Field '$field' must be at least $ruleValue characters long");
                    return false;
                }
                break;
                
            case self::MAX_LENGTH:
                if (!empty($value) && strlen($value) > $ruleValue) {
                    $this->addError($field, "Field '$field' must not exceed $ruleValue characters");
                    return false;
                }
                break;
                
            case self::IN_ARRAY:
                if (!empty($value) && !in_array($value, $ruleValue)) {
                    $allowed = implode(', ', $ruleValue);
                    $this->addError($field, "Field '$field' must be one of: $allowed");
                    return false;
                }
                break;
                
            case self::REGEX:
                if (!empty($value) && !preg_match($ruleValue, $value)) {
                    $this->addError($field, "Field '$field' format is invalid");
                    return false;
                }
                break;
                
            case self::BOOLEAN:
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $this->addError($field, "Field '$field' must be true or false");
                    return false;
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Check if a date string is valid
     * 
     * @param string $date Date string
     * @return bool
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get first error for a field
     * 
     * @param string $field Field name
     * @return string|null
     */
    public function getFirstError($field) {
        return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
    }
    
    /**
     * Get all error messages as flat array
     * 
     * @return array
     */
    public function getAllErrorMessages() {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }
    
    /**
     * Sanitize input data
     * 
     * @param mixed $data Data to sanitize
     * @param string $type Sanitization type
     * @return mixed
     */
    public static function sanitize($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $data);
        }
        
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
                
            case 'email':
                return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
                
            case 'int':
            case 'integer':
                return (int) filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return (float) filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'url':
                return filter_var(trim($data), FILTER_SANITIZE_URL);
                
            case 'alpha':
                return preg_replace('/[^a-zA-Z\s]/', '', trim($data));
                
            case 'alphanumeric':
                return preg_replace('/[^a-zA-Z0-9\s\-_]/', '', trim($data));
                
            case 'date':
                $date = DateTime::createFromFormat('Y-m-d', trim($data));
                return $date ? $date->format('Y-m-d') : null;
                
            default:
                return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate and sanitize data in one step
     * 
     * @param array $data Input data
     * @param array $rules Validation rules
     * @param array $sanitize Sanitization rules
     * @return array ['valid' => bool, 'data' => array, 'errors' => array]
     */
    public static function validateAndSanitize($data, $rules, $sanitize = []) {
        $validator = new self();
        
        // Apply validation rules
        foreach ($rules as $field => $fieldRules) {
            if (is_array($fieldRules)) {
                foreach ($fieldRules as $rule) {
                    if (is_array($rule)) {
                        $validator->rule($field, $rule[0], $rule[1]);
                    } else {
                        $validator->rule($field, $rule);
                    }
                }
            } else {
                $validator->rule($field, $fieldRules);
            }
        }
        
        // Validate
        $isValid = $validator->validate($data);
        
        // Sanitize data
        $sanitizedData = [];
        foreach ($data as $field => $value) {
            $sanitizeType = isset($sanitize[$field]) ? $sanitize[$field] : 'string';
            $sanitizedData[$field] = self::sanitize($value, $sanitizeType);
        }
        
        return [
            'valid' => $isValid,
            'data' => $sanitizedData,
            'errors' => $validator->getErrors()
        ];
    }
}

/**
 * Validation rule sets for common data types in this application
 */
class ValidationRules {
    
    /**
     * Member validation rules
     */
    public static function member() {
        return [
            'first_name' => [
                InputValidator::REQUIRED,
                InputValidator::STRING,
                InputValidator::ALPHA,
                [InputValidator::MIN_LENGTH, 1],
                [InputValidator::MAX_LENGTH, 50]
            ],
            'last_name' => [
                InputValidator::REQUIRED,
                InputValidator::STRING,
                InputValidator::ALPHA,
                [InputValidator::MIN_LENGTH, 1],
                [InputValidator::MAX_LENGTH, 50]
            ],
            'gender' => [
                InputValidator::REQUIRED,
                [InputValidator::IN_ARRAY, ['M', 'F']]
            ],
            'birthdate' => [
                InputValidator::REQUIRED,
                InputValidator::DATE
            ],
            'status' => [
                [InputValidator::IN_ARRAY, [
                    'active', 'inactive', 'moved', 
                    'no_calling', 'deceased', 'unknown'
                ]]
            ],
            'status_notes' => [
                InputValidator::STRING,
                [InputValidator::MAX_LENGTH, 500]
            ]
        ];
    }
    
    /**
     * Calling validation rules
     */
    public static function calling() {
        $validOrganizations = [
            'Aaronic Priesthood Quorums', 'Additional Callings', 'Bishopric',
            'Elders Quorum', 'Facilities', 'Full-Time Missionaries', 'History',
            'Music', 'Primary', 'Relief Society', 'Stake', 'Sunday School',
            'Technology', 'Temple and Family History', 'Ward Missionaries',
            'Welfare and Self-Reliance', 'Young Women'
        ];
        
        $validGroupings = [
            'AP Advisors', 'AP Class Presidency', 'Additional Callings',
            'Bishopric', 'Elders Quorum', 'Full-Time Missionaries', 'Music',
            'Primary Teachers', 'Primary Other', 'Primary Presidency',
            'RS Other', 'RS Presidency', 'Stake', 'SS Teachers', 'SS Other',
            'SS Presidency', 'Work of Salvation', 'YW Class Presidency', 'YW Presidency'
        ];
        
        return [
            'calling_name' => [
                InputValidator::REQUIRED,
                InputValidator::STRING,
                InputValidator::ALPHANUMERIC,
                [InputValidator::MIN_LENGTH, 1],
                [InputValidator::MAX_LENGTH, 100]
            ],
            'organization' => [
                InputValidator::REQUIRED,
                [InputValidator::IN_ARRAY, $validOrganizations]
            ],
            'grouping' => [
                InputValidator::REQUIRED,
                [InputValidator::IN_ARRAY, $validGroupings]
            ],
            'priority' => [
                InputValidator::REQUIRED,
                InputValidator::INTEGER,
                [InputValidator::REGEX, '/^[0-9]{1,3}$/'] // 0-999
            ]
        ];
    }
    
    /**
     * Calling assignment validation rules
     */
    public static function callingAssignment() {
        return [
            'member_id' => [
                InputValidator::REQUIRED,
                InputValidator::INTEGER
            ],
            'calling_id' => [
                InputValidator::REQUIRED,
                InputValidator::INTEGER
            ],
            'date_set_apart' => [
                InputValidator::REQUIRED,
                InputValidator::DATE
            ]
        ];
    }
    
    /**
     * PIN validation rules
     */
    public static function pin() {
        return [
            'pin' => [
                InputValidator::REQUIRED,
                InputValidator::STRING,
                [InputValidator::MIN_LENGTH, 4],
                [InputValidator::MAX_LENGTH, 20],
                [InputValidator::REGEX, '/^[0-9a-zA-Z!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]*$/']
            ]
        ];
    }
    
    /**
     * Comment validation rules
     */
    public static function comments() {
        return [
            'comments' => [
                InputValidator::STRING,
                [InputValidator::MAX_LENGTH, 2000]
            ],
            'calling_id' => [
                InputValidator::REQUIRED,
                InputValidator::INTEGER
            ]
        ];
    }
    
    /**
     * Get member status options
     * 
     * @return array Associative array of status values and display names
     */
    public static function getMemberStatuses() {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive', 
            'moved' => 'Moved Away',
            'no_calling' => 'No Current Calling',
            'deceased' => 'Deceased',
            'unknown' => 'Status Unknown'
        ];
    }
    
    /**
     * Get status options that should appear in calling assignments
     * (excludes moved, deceased, etc.)
     * 
     * @return array Status values for calling-eligible members
     */
    public static function getCallingEligibleStatuses() {
        return ['active', 'no_calling'];
    }
}
?>