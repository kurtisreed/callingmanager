/**
 * Client-Side Validation Library
 * Provides immediate feedback to users before server submission
 */

class ClientValidator {
    constructor() {
        this.errors = {};
    }
    
    /**
     * Validate a single field
     */
    validateField(fieldName, value, rules) {
        this.errors[fieldName] = [];
        
        for (let rule of rules) {
            let ruleName, ruleValue;
            
            if (typeof rule === 'object') {
                ruleName = rule.rule;
                ruleValue = rule.value;
            } else {
                ruleName = rule;
                ruleValue = null;
            }
            
            const error = this.applyRule(fieldName, value, ruleName, ruleValue);
            if (error) {
                this.errors[fieldName].push(error);
                break; // Stop on first error for this field
            }
        }
        
        return this.errors[fieldName].length === 0;
    }
    
    /**
     * Apply a validation rule
     */
    applyRule(fieldName, value, ruleName, ruleValue) {
        switch (ruleName) {
            case 'required':
                if (!value || value.trim() === '') {
                    return `${this.formatFieldName(fieldName)} is required`;
                }
                break;
                
            case 'minLength':
                if (value && value.length < ruleValue) {
                    return `${this.formatFieldName(fieldName)} must be at least ${ruleValue} characters long`;
                }
                break;
                
            case 'maxLength':
                if (value && value.length > ruleValue) {
                    return `${this.formatFieldName(fieldName)} must not exceed ${ruleValue} characters`;
                }
                break;
                
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (value && !emailRegex.test(value)) {
                    return `${this.formatFieldName(fieldName)} must be a valid email address`;
                }
                break;
                
            case 'alpha':
                const alphaRegex = /^[a-zA-Z\s]*$/;
                if (value && !alphaRegex.test(value)) {
                    return `${this.formatFieldName(fieldName)} must contain only letters and spaces`;
                }
                break;
                
            case 'alphanumeric':
                const alphanumericRegex = /^[a-zA-Z0-9\s\-_]*$/;
                if (value && !alphanumericRegex.test(value)) {
                    return `${this.formatFieldName(fieldName)} must contain only letters, numbers, spaces, hyphens, and underscores`;
                }
                break;
                
            case 'integer':
                if (value && !Number.isInteger(Number(value))) {
                    return `${this.formatFieldName(fieldName)} must be a whole number`;
                }
                break;
                
            case 'date':
                if (value && !this.isValidDate(value)) {
                    return `${this.formatFieldName(fieldName)} must be a valid date (YYYY-MM-DD)`;
                }
                break;
                
            case 'inArray':
                if (value && !ruleValue.includes(value)) {
                    return `${this.formatFieldName(fieldName)} must be one of: ${ruleValue.join(', ')}`;
                }
                break;
                
            case 'regex':
                if (value && !ruleValue.test(value)) {
                    return `${this.formatFieldName(fieldName)} format is invalid`;
                }
                break;
                
            case 'futureDate':
                if (value && new Date(value) <= new Date()) {
                    return `${this.formatFieldName(fieldName)} must be in the future`;
                }
                break;
                
            case 'pastDate':
                if (value && new Date(value) >= new Date()) {
                    return `${this.formatFieldName(fieldName)} must be in the past`;
                }
                break;
        }
        
        return null; // No error
    }
    
    /**
     * Check if date string is valid
     */
    isValidDate(dateString) {
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date) && dateString === date.toISOString().split('T')[0];
    }
    
    /**
     * Format field name for display
     */
    formatFieldName(fieldName) {
        return fieldName
            .replace(/_/g, ' ')
            .replace(/([A-Z])/g, ' $1')
            .replace(/^./, str => str.toUpperCase());
    }
    
    /**
     * Get all errors
     */
    getErrors() {
        return this.errors;
    }
    
    /**
     * Get errors for a specific field
     */
    getFieldErrors(fieldName) {
        return this.errors[fieldName] || [];
    }
    
    /**
     * Check if validation passed
     */
    isValid() {
        for (let field in this.errors) {
            if (this.errors[field].length > 0) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Clear all errors
     */
    clearErrors() {
        this.errors = {};
    }
}

/**
 * Form Validation Helper
 */
class FormValidator {
    constructor(formElement) {
        this.form = formElement;
        this.validator = new ClientValidator();
        this.rules = {};
        this.realTimeValidation = false;
        
        this.setupEventListeners();
    }
    
    /**
     * Set validation rules for the form
     */
    setRules(rules) {
        this.rules = rules;
        return this;
    }
    
    /**
     * Enable real-time validation
     */
    enableRealTimeValidation() {
        this.realTimeValidation = true;
        return this;
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                this.showErrors();
            }
        });
        
        // Real-time validation on blur
        this.form.addEventListener('blur', (e) => {
            if (this.realTimeValidation && e.target.matches('input, select, textarea')) {
                this.validateField(e.target);
            }
        }, true);
    }
    
    /**
     * Validate entire form
     */
    validateForm() {
        this.validator.clearErrors();
        let isValid = true;
        
        for (let fieldName in this.rules) {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                const fieldValid = this.validator.validateField(fieldName, field.value, this.rules[fieldName]);
                if (!fieldValid) {
                    isValid = false;
                }
            }
        }
        
        return isValid;
    }
    
    /**
     * Validate single field
     */
    validateField(fieldElement) {
        const fieldName = fieldElement.name;
        if (this.rules[fieldName]) {
            const isValid = this.validator.validateField(fieldName, fieldElement.value, this.rules[fieldName]);
            this.showFieldError(fieldElement, isValid);
            return isValid;
        }
        return true;
    }
    
    /**
     * Show validation errors
     */
    showErrors() {
        const errors = this.validator.getErrors();
        
        for (let fieldName in errors) {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field && errors[fieldName].length > 0) {
                this.showFieldError(field, false, errors[fieldName][0]);
            }
        }
        
        // Focus on first error field
        const firstErrorField = this.form.querySelector('.validation-error');
        if (firstErrorField) {
            firstErrorField.focus();
        }
    }
    
    /**
     * Show error for specific field
     */
    showFieldError(fieldElement, isValid, errorMessage = null) {
        // Remove existing error styling and messages
        fieldElement.classList.remove('validation-error', 'validation-success');
        
        let errorDiv = fieldElement.parentNode.querySelector('.validation-message');
        if (errorDiv) {
            errorDiv.remove();
        }
        
        if (!isValid) {
            // Add error styling
            fieldElement.classList.add('validation-error');
            
            // Create error message
            if (!errorMessage) {
                const fieldErrors = this.validator.getFieldErrors(fieldElement.name);
                errorMessage = fieldErrors[0] || 'Invalid input';
            }
            
            errorDiv = document.createElement('div');
            errorDiv.className = 'validation-message error';
            errorDiv.textContent = errorMessage;
            fieldElement.parentNode.appendChild(errorDiv);
        } else if (fieldElement.value.trim() !== '') {
            // Add success styling for non-empty valid fields
            fieldElement.classList.add('validation-success');
        }
    }
    
    /**
     * Clear all validation messages
     */
    clearErrors() {
        this.validator.clearErrors();
        
        // Remove error styling
        this.form.querySelectorAll('.validation-error, .validation-success').forEach(el => {
            el.classList.remove('validation-error', 'validation-success');
        });
        
        // Remove error messages
        this.form.querySelectorAll('.validation-message').forEach(el => {
            el.remove();
        });
    }
}

/**
 * Predefined validation rule sets
 */
const ValidationRules = {
    member: {
        first_name: [
            'required',
            'alpha',
            { rule: 'maxLength', value: 50 }
        ],
        last_name: [
            'required',
            'alpha',
            { rule: 'maxLength', value: 50 }
        ],
        gender: [
            'required',
            { rule: 'inArray', value: ['M', 'F'] }
        ],
        birthdate: [
            'required',
            'date',
            'pastDate'
        ]
    },
    
    calling: {
        calling_name: [
            'required',
            'alphanumeric',
            { rule: 'maxLength', value: 100 }
        ],
        organization: [
            'required'
        ],
        grouping: [
            'required'
        ],
        priority: [
            'required',
            'integer',
            { rule: 'regex', value: /^[0-9]{1,3}$/ }
        ]
    },
    
    pin: {
        pin: [
            'required',
            { rule: 'minLength', value: 4 },
            { rule: 'maxLength', value: 20 }
        ]
    }
};

// Make classes available globally
window.ClientValidator = ClientValidator;
window.FormValidator = FormValidator;
window.ValidationRules = ValidationRules;