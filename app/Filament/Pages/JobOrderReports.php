<?php

namespace App\Filament\Pages;

use App\JobOrderStatus;
use App\Models\Department;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobOrderReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Facilities';

    protected static ?string $navigationLabel = 'Job Order Reports';

    protected string $view = 'filament.pages.job-order-reports';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['department_id' => Filament::getTenant()?->id]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([Section::make('Job Order Report Filters')->schema([
            Select::make('status')->multiple()->options(JobOrderStatus::options())->searchable(),
            Select::make('priority')->multiple()->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical']),
            Select::make('department_id')->options(fn (): array => $this->departmentOptions())->required()->searchable(),
            Select::make('assigned_to_user_id')->label('Assigned User')->options(fn (): array => User::role(['job_order_manager', 'maintenance_staff', 'admin', 'super_admin'])->when(Filament::getTenant()?->id, fn ($query, $departmentId) => $query->canAccessDepartment($departmentId))->where('status', 1)->where('is_deleted', 0)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Select::make('location_id')->options(fn (): array => Location::query()->where('department_id', Filament::getTenant()?->id)->where('is_deleted', false)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Select::make('inventory_item_id')->label('Asset')->options(fn (): array => InventoryItem::query()->where('department_id', Filament::getTenant()?->id)->where('is_deleted', false)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            DatePicker::make('created_from'), DatePicker::make('created_until'),
        ])->columns(3)])->statePath('data');
    }

    public function generatePdf()
    {
        return redirect()->route('reports.job-orders.pdf', $this->form->getState());
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'job_order_manager', 'maintenance_staff']) ?? false;
    }

    private function departmentOptions(): array
    {
        if (auth()->user()?->hasRole('super_admin')) {
            return ['all' => 'All Departments'] + Department::query()->where('is_deleted', 0)->orderBy('name')->pluck('name', 'id')->all();
        }

        return auth()->user()?->departments()->where('department.is_deleted', 0)->orderBy('department.name')->pluck('department.name', 'department.id')->all() ?? [];
    }
}
