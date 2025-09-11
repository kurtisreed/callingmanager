# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Application Overview

This is a **Kimberly 1st Ward Calling Manager** - a PHP web application for managing LDS ward callings. The application is a single-page web app with PIN-based authentication that allows ward leadership to:

- Track member assignments to church callings
- Manage calling assignments and releases
- View member information and calling history
- Monitor calling processes and approvals

## Architecture

### Core Files
- `index.html` - Main application interface with tabbed navigation
- `script.js` - Frontend JavaScript handling UI interactions and AJAX calls
- `styles.css` - Application styling
- `config.php` - Environment variable loader and configuration management
- `db_connect.php` - MySQL database connection setup

### Security Components
- `auth_required.php` - Authentication middleware (include in protected files)
- `RateLimiter.php` - Prevents brute force attacks on authentication
- `InputValidator.php` - Comprehensive input validation and sanitization
- `OutputSanitizer.php` - XSS prevention through proper output encoding
- `admin_security.php` - Administrative security functions

### Database Operations
The application uses procedural PHP with MySQLi for database operations. Files follow naming patterns:
- `get_*.php` - Data retrieval endpoints
- `add_*.php` - Create operations
- `update_*.php` - Update operations
- `remove_*.php` - Delete operations

## Configuration

### Environment Setup
1. Copy `.env.example` to `.env`
2. Configure database credentials and security settings:
   - `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`
   - `AUTH_PIN` - Application access PIN
   - Rate limiting and session timeout settings

### Database Requirements
- MySQL/MariaDB database
- Tables for members, callings, calling history, and rate limiting
- Connection handled through `db_connect.php` with proper error handling

## Development Commands

This is a pure PHP application with no build system or dependency management:
- **No build process** - Files are served directly
- **No package manager** - Uses native PHP and CDN resources (SortableJS)
- **No testing framework** - Manual testing required
- **Development server**: Use PHP built-in server or configure Apache/Nginx

For local development:
```bash
php -S localhost:8000
```

## Key Patterns

### Authentication Flow
All protected endpoints must include:
```php
require_once __DIR__ . '/auth_required.php';
```

### Database Queries
Use prepared statements for all user input:
```php
$stmt = $conn->prepare("SELECT * FROM table WHERE id = ?");
$stmt->bind_param("i", $id);
```

### Input Validation
Use InputValidator class for comprehensive validation:
```php
$validator = new InputValidator();
$validator->rule('field', InputValidator::REQUIRED)
          ->rule('field', InputValidator::STRING);
```

### Output Sanitization
Always sanitize output with OutputSanitizer:
```php
echo OutputSanitizer::html($user_data);
```

### Error Handling
- Use proper HTTP status codes
- Log errors without exposing details to users
- Return JSON responses for AJAX endpoints

## Security Considerations

- PIN-based authentication with rate limiting
- Session timeout management
- Input validation on all user data
- Output sanitization to prevent XSS
- SQL injection prevention through prepared statements
- Environment variables for sensitive configuration