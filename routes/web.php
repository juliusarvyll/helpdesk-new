<?php

use App\Filament\Resources\AzureAccountProvisioningResource;
use App\Http\Controllers\ProfileController;
use App\Models\Department;
use App\TicketPdfReport;
use Illuminate\Support\Facades\Route;

Route::redirect('/admin', '/');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/reports/tickets.pdf', fn () => TicketPdfReport::response(request()->query(), request()->user()))
        ->name('reports.tickets.pdf');
    Route::get('/microsoft-teams/oauth/callback', function () {
        abort_unless(request()->user()?->hasRole('super_admin'), 403);

        $tenant = Department::query()
            ->where('is_deleted', 0)
            ->first();

        abort_unless($tenant, 403);

        return redirect(AzureAccountProvisioningResource::getUrl('index', [
            'tenant' => $tenant,
        ]));
    })->name('microsoft-teams.oauth.callback');
});
