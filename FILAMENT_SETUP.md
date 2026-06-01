# Filament Shield Setup Instructions

## Step 1: Install Filament Shield
Run the following command to install Filament Shield:
```bash
composer require bezhansalleh/filament-shield
```

## Step 2: Publish Shield Configuration
```bash
php artisan vendor:publish --tag=filament-shield-config
```

## Step 3: Run Migrations
```bash
php artisan migrate
```

## Step 4: Install Shield
This will set up roles and permissions:
```bash
php artisan shield:install --fresh
```

## Step 5: Generate Shield Resources
Generate permissions for all your resources:
```bash
php artisan shield:generate --all
```

## Step 6: Create Super Admin
```bash
php artisan shield:super-admin
```

## Configuration Notes

### User Model
- Updated to implement `FilamentUser` interface
- Added `HasRoles` trait from Spatie Permission
- Added `canAccessPanel()` method to check user status
- All fields from migration are now fillable

### Models Created
- SystemLog
- IssueCategory
- IssueList
- Department
- Position
- Ticket

### Next Steps
After running the above commands, you'll need to:
1. Create Filament resources for each model
2. Configure the Filament panel
3. Set up navigation and access control

Run: `php artisan make:filament-resource [ModelName] --generate`
