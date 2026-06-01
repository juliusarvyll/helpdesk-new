# Laravel Helpdesk to Filament Migration Guide

## Overview
This guide will help you migrate your Laravel helpdesk application to use Filament with Shield for roles and permissions management.

## What Has Been Done

### 1. Models Created ✓
All necessary models have been created with proper relationships:

- **SystemLog** (`app/Models/SystemLog.php`)
  - Tracks user activities
  - Belongs to User

- **IssueCategory** (`app/Models/IssueCategory.php`)
  - Categories for issues
  - Has many IssueList

- **IssueList** (`app/Models/IssueList.php`)
  - Specific issues under categories
  - Belongs to IssueCategory

- **Department** (`app/Models/Department.php`)
  - Organization departments

- **Position** (`app/Models/Position.php`)
  - User positions/roles

- **Ticket** (`app/Models/Ticket.php`)
  - Main ticket management model
  - Includes priority, status, assignments, etc.

### 2. User Model Updated ✓
The User model has been updated to:
- Implement `FilamentUser` interface
- Include `HasRoles` trait from Spatie Permission
- Add all fields from your migration (username, address, contact, photo, department, position, role, status, is_deleted)
- Include `canAccessPanel()` method for access control

## Installation Steps

### Option 1: Automated Setup (Recommended)
Run the provided setup script:

```bash
sudo ./setup-filament.sh
```

This script will:
1. Install Filament Shield package
2. Publish Shield configuration
3. Update AdminPanelProvider with Shield plugin
4. Run migrations
5. Install Shield (roles & permissions)
6. Generate Filament resources for all models
7. Generate Shield permissions
8. Create a super admin user

### Option 2: Manual Setup

#### Step 1: Install Filament Shield
```bash
composer require bezhansalleh/filament-shield
```

#### Step 2: Publish Configuration
```bash
php artisan vendor:publish --tag=filament-shield-config
```

#### Step 3: Update AdminPanelProvider
Replace the content of `app/Providers/Filament/AdminPanelProvider.php` with the content from `AdminPanelProvider.php.new`:

```bash
sudo cp AdminPanelProvider.php.new app/Providers/Filament/AdminPanelProvider.php
```

Or manually add this line to the panel configuration:
```php
->plugin(FilamentShieldPlugin::make())
```

And add this import at the top:
```php
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
```

#### Step 4: Run Migrations
```bash
php artisan migrate
```

#### Step 5: Install Shield
```bash
php artisan shield:install --fresh
```

#### Step 6: Generate Filament Resources
```bash
php artisan make:filament-resource Department --generate
php artisan make:filament-resource Position --generate
php artisan make:filament-resource IssueCategory --generate
php artisan make:filament-resource IssueList --generate
php artisan make:filament-resource Ticket --generate
php artisan make:filament-resource SystemLog --generate
```

#### Step 7: Generate Shield Permissions
```bash
php artisan shield:generate --all
```

#### Step 8: Create Super Admin
```bash
php artisan shield:super-admin
```

## Database Schema

Your existing migrations define:

### Users Table
- Standard auth fields (name, email, password)
- Additional fields: username, address, contact, photo
- Organization fields: department, position, role
- Status fields: status, is_deleted

### Department Table
- name, unit_head, is_deleted

### Position Table
- name, is_deleted

### Role Table (Legacy)
- name, is_deleted
- **Note**: Shield will create its own roles table via Spatie Permission

### Issue Category Table
- name, is_deleted

### Issue List Table
- issue_category_id, issue, is_deleted

### Tickets Table
- Comprehensive ticket management fields
- Client and technical support tracking
- Priority, status, and assignment management
- Time tracking and ratings

### System Logs Table
- user_id, name, ip_address, mac_address, message

## Post-Installation Configuration

### 1. Access the Admin Panel
Navigate to: `http://your-domain/admin`

### 2. Configure Roles
Shield creates default roles:
- super_admin
- panel_user

You can create custom roles via the Filament panel.

### 3. Assign Permissions
Use the Shield interface to assign permissions to roles for each resource:
- view, view_any, create, update, delete, restore, force_delete

### 4. Customize Resources
Edit the generated resources in `app/Filament/Resources/` to:
- Customize form fields
- Add filters and actions
- Configure table columns
- Set up relationships

### 5. Navigation
Resources will automatically appear in the navigation. Customize order and grouping in each resource's `getNavigationGroup()` and `getNavigationSort()` methods.

## Key Features

### Filament Shield Provides:
- Role-based access control (RBAC)
- Permission management UI
- Resource-level permissions
- Super admin role with full access
- Integration with Spatie Laravel Permission

### Your Helpdesk Features:
- Department and position management
- Issue categorization
- Ticket management with priorities
- Technical support assignment
- Client feedback and ratings
- System activity logging

## Troubleshooting

### Permission Denied Errors
If you encounter permission errors, ensure proper file ownership:
```bash
sudo chown -R $USER:$USER .
```

### Migration Issues
If migrations fail, check your database connection in `.env`

### Shield Installation Issues
If Shield installation fails, ensure:
- Spatie Permission is compatible with your Laravel version
- Database is properly configured
- No conflicting role/permission tables exist

## Next Steps

1. Run the setup script or follow manual steps
2. Login to `/admin` with super admin credentials
3. Create additional roles for your helpdesk (e.g., "Support Agent", "Client", "Manager")
4. Assign appropriate permissions to each role
5. Customize the generated resources to match your workflow
6. Test the permission system with different user roles

## Additional Resources

- [Filament Documentation](https://filamentphp.com/docs)
- [Filament Shield Documentation](https://github.com/bezhanSalleh/filament-shield)
- [Spatie Permission Documentation](https://spatie.be/docs/laravel-permission)

## Files Created/Modified

### Created:
- `app/Models/SystemLog.php`
- `app/Models/IssueCategory.php`
- `app/Models/IssueList.php`
- `app/Models/Department.php`
- `app/Models/Position.php`
- `app/Models/Ticket.php`
- `setup-filament.sh`
- `AdminPanelProvider.php.new`
- `FILAMENT_SETUP.md`
- `MIGRATION_GUIDE.md` (this file)

### Modified:
- `app/Models/User.php` - Added Filament and Shield support

### To Be Modified (by setup script):
- `app/Providers/Filament/AdminPanelProvider.php` - Add Shield plugin
