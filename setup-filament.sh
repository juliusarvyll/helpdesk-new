#!/bin/bash

echo "=== Filament Shield Setup Script ==="
echo ""

# Step 1: Install Filament Shield
echo "Step 1: Installing Filament Shield..."
composer require bezhansalleh/filament-shield

# Step 2: Publish configuration
echo ""
echo "Step 2: Publishing Shield configuration..."
php artisan vendor:publish --tag=filament-shield-config

# Step 3: Update AdminPanelProvider
echo ""
echo "Step 3: Updating AdminPanelProvider..."
cat > app/Providers/Filament/AdminPanelProvider.php << 'EOF'
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(FilamentShieldPlugin::make());
    }
}
EOF

# Step 4: Run migrations
echo ""
echo "Step 4: Running migrations..."
php artisan migrate

# Step 5: Install Shield
echo ""
echo "Step 5: Installing Shield (setting up roles and permissions)..."
php artisan shield:install --fresh

# Step 6: Create Filament resources
echo ""
echo "Step 6: Creating Filament resources..."
php artisan make:filament-resource Department --generate
php artisan make:filament-resource Position --generate
php artisan make:filament-resource IssueCategory --generate
php artisan make:filament-resource IssueList --generate
php artisan make:filament-resource Ticket --generate
php artisan make:filament-resource SystemLog --generate

# Step 7: Generate Shield permissions
echo ""
echo "Step 7: Generating Shield permissions for all resources..."
php artisan shield:generate --all

# Step 8: Create super admin
echo ""
echo "Step 8: Creating super admin user..."
php artisan shield:super-admin

echo ""
echo "=== Setup Complete! ==="
echo ""
echo "Next steps:"
echo "1. Visit /admin to access the Filament panel"
echo "2. Login with the super admin credentials you just created"
echo "3. Configure roles and permissions as needed"
echo ""
