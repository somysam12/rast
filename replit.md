# Mod APK Manager

## Overview

Mod APK Manager is a web-based distribution platform for modified Android applications (APK files). The system provides a dual-panel architecture with separate admin and user interfaces for managing mod distribution, license keys, user accounts, and financial transactions. Built with PHP and MySQL, it handles APK file uploads, license key generation and sales, user balance management, referral systems, and transaction tracking.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture

**Technology Stack**: The frontend uses vanilla JavaScript with ES6 classes for enhanced UI functionality and dark mode support. The design system is built with custom CSS using CSS variables for theming.

**Component Organization**: The UI is organized into reusable JavaScript classes (`DarkModeManager`, `EnhancedUI`) that handle:
- Dark/light theme switching with localStorage persistence
- Loading overlays and spinners for asynchronous operations
- Form enhancements and validation
- Smooth transitions and scroll animations
- Tooltip system

**Styling Approach**: Uses a custom CSS framework with:
- CSS custom properties (variables) for consistent theming
- Two font families (Inter and Poppins) from Google Fonts
- Comprehensive color palette including primary gradients, semantic colors (success, warning, danger), and complete dark mode variants
- Predefined spacing scale and border radius values
- Shadow system for depth perception

**Rationale**: The decision to use vanilla JavaScript instead of a framework keeps the application lightweight and reduces dependencies, suitable for a PHP-based application. The component-based JavaScript architecture provides modularity while maintaining simplicity.

### Backend Architecture

**Language & Version**: PHP 7.4+ with PDO for database operations.

**Architecture Pattern**: Traditional server-side MVC-style architecture where:
- PHP handles routing, business logic, and database operations
- HTML templates are likely rendered server-side
- AJAX/fetch calls from frontend for dynamic operations

**Session Management**: Standard PHP session handling for authentication and authorization, with separate admin and user roles.

**File Upload System**: Dedicated file upload handling for APK files stored in `uploads/apks/` directory with file system permission requirements (755/777).

**Rationale**: PHP with PDO provides a straightforward, widely-supported solution for this type of application. The server-side rendering approach is appropriate for a CRUD-heavy admin panel system. PDO offers prepared statements for SQL injection protection.

### Data Storage

**Database**: MySQL (database name: `mod_apk_manager`)

**Configuration**: Database credentials stored in `config/database.php` (centralized configuration pattern).

**Data Model** (inferred from features):
- **Users table**: Stores user accounts with balance, referral codes, roles (admin/user)
- **Mods table**: Mod APK metadata (name, description, status)
- **License Keys table**: Generated keys linked to mods with status tracking (available/sold)
- **Transactions table**: Financial transaction history for balance additions and key purchases
- **APK Files**: File paths stored in database, actual files in filesystem

**Relationships**:
- Users have many transactions
- Users have many purchased keys
- Mods have many license keys
- Mods have APK file references
- License keys belong to mods and users (after purchase)

**Rationale**: MySQL is a reliable choice for this transactional system requiring ACID compliance for financial operations. Storing APK files on the filesystem rather than in the database is appropriate given their large size, with only metadata/paths in the database.

### Authentication & Authorization

**Authentication Method**: Username/password based authentication with default admin credentials (admin/admin123).

**User Registration**: Self-service registration system for end users.

**Role-Based Access Control**: Two-tier system:
- **Admin Role**: Full system access including mod management, key generation, user management, balance operations, and system settings
- **User Role**: Limited access to purchasing keys, viewing balance, downloading purchased APKs, and profile management

**Session Security**: PHP session-based authentication (specifics would be in auth implementation files).

**Rationale**: Simple role-based access is sufficient for this two-tier system. Username/password authentication is straightforward for both admin and user portals.

### Key Functional Components

**Mod Management System**:
- CRUD operations for mod entries
- APK file upload and storage
- Status management for mods

**License Key System**:
- Single and bulk key generation
- Key-to-mod association
- Purchase/activation workflow
- Status tracking (available/sold/activated)

**Financial System**:
- User balance tracking
- Admin balance addition capability
- Transaction logging for all financial operations
- Key purchase transactions

**Referral System**:
- Referral code generation
- Referral tracking (implementation details not visible in provided files)

**Download System**:
- Secure APK download for purchased/activated keys
- File access control based on ownership

## External Dependencies

### Third-Party Libraries

**Font Awesome**: Icon library used throughout the UI (`fas fa-moon`, `fas fa-sun` icons visible in dark mode toggle).

**Google Fonts**: 
- Inter font family (weights: 300, 400, 500, 600, 700, 800)
- Poppins font family (weights: 300, 400, 500, 600, 700)

**Rationale**: Font Awesome provides comprehensive iconography. Google Fonts CDN delivery ensures fonts load reliably without local hosting overhead.

### Server Requirements

**PHP Extensions Required**:
- PDO MySQL extension for database connectivity
- File upload extensions (standard with PHP)

**Web Server**: Any PHP-compatible web server (Apache, Nginx) with PHP 7.4+ support.

**File System**: Write permissions required on `uploads/apks/` directory for APK storage.

### Database

**MySQL Database**: Requires MySQL server with database named `mod_apk_manager`. Connection configured via `config/database.php`.

**No External Database Services**: Uses self-hosted MySQL rather than cloud database services.