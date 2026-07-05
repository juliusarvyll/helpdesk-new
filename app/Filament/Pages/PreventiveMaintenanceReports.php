<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\Location;
use App\Models\User;
use App\PreventiveMaintenanceAssetCheckStatus;
use App\PreventiveMaintenancePdfReport;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PreventiveMaintenanceReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Preventive Maintenance';

    protected static ?string $navigationLabel = 'PMS Reports';

    protected string $view = 'filament.pages.preventive-maintenance-reports';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['department_id' => Filament::getTenant()?->id]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([Section::make('PMS Report Filters')->schema([
            Select::make('location_id')->options(fn (): array => Location::query()->where('department_id', Filament::getTenant()?->id)->where('is_deleted', false)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Select::make('checked_by')->label('Inspector')->options(fn (): array => User::role(['technical_support', 'admin', 'super_admin'])->when(Filament::getTenant()?->id, fn ($query, $departmentId) => $query->canAccessDepartment($departmentId))->where('status', 1)->where('is_deleted', 0)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Select::make('status')->label('Result')->multiple()->options(PreventiveMaintenanceAssetCheckStatus::resultOptions()),
            Select::make('inventory_item_id')->label('Asset')->options(fn (): array => InventoryItem::query()->where('department_id', Filament::getTenant()?->id)->where('is_it_asset', true)->where('is_deleted', false)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Select::make('inventory_item_serial_number_id')->label('Serial Number')->options(fn (): array => InventoryItemSerialNumber::query()->whereHas('inventoryItem', fn ($query) => $query->where('department_id', Filament::getTenant()?->id)->where('is_it_asset', true))->orderBy('serial_number')->pluck('serial_number', 'id')->all())->searchable(),
            DatePicker::make('completed_from')->label('From'), DatePicker::make('completed_until')->label('Until'),
            Select::make('department_id')->options([Filament::getTenant()?->id => Filament::getTenant()?->name])->required(),
        ])->columns(3)])->statePath('data');
    }

    public function metrics(): array
    {
        return PreventiveMaintenancePdfReport::metrics($this->form->getState(), auth()->user());
    }

    public function generatePdf()
    {
        return redirect()->route('reports.preventive-maintenance.pdf', $this->form->getState());
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'technical_support']) ?? false;
    }
}
