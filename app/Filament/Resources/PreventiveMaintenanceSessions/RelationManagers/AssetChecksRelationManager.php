<?php

namespace App\Filament\Resources\PreventiveMaintenanceSessions\RelationManagers;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\PreventiveMaintenanceAssetChecks\PreventiveMaintenanceAssetCheckResource;
use App\Filament\Resources\TicketResource;
use App\Models\PmsChecklistItem;
use App\Models\PreventiveMaintenanceAssetCheck;
use App\PmsInspectionService;
use App\PreventiveMaintenanceAssetCheckStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AssetChecksRelationManager extends RelationManager
{
    use HasCompactTableColumns;

    protected static string $relationship = 'assetChecks';

    protected static ?string $title = 'IT Asset Inspections';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['inventoryItem', 'serialNumber', 'inspector', 'checklistTemplate.items', 'ticket'])->withCount('results'))
            ->columns([
                static::compactTextColumn(TextColumn::make('inventoryItem.name'), 30)->label('Asset')->searchable(),
                static::compactTextColumn(TextColumn::make('inventoryItem.asset_tag'), 24)->label('Asset Tag')->searchable(),
                static::compactTextColumn(TextColumn::make('serialNumber.serial_number'), 28)->label('Serial Number')->searchable(),
                TextColumn::make('serialNumber.status')->label('Current Status')->badge(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (PreventiveMaintenanceAssetCheckStatus $state): string => $state->label())->color(fn (PreventiveMaintenanceAssetCheckStatus $state): string => $state->color()),
                TextColumn::make('inspector.name')->label('Inspector'),
                TextColumn::make('results_count')->label('Results'),
                TextColumn::make('completed_at')->dateTime(),
                TextColumn::make('ticket.id')->label('Repair Ticket')->url(fn (PreventiveMaintenanceAssetCheck $record): ?string => $record->ticket ? TicketResource::getUrl('view', ['record' => $record->ticket]) : null),
            ])
            ->recordActions([
                Action::make('startInspection')
                    ->label('Start Inspection')
                    ->icon('heroicon-o-play')
                    ->visible(fn (PreventiveMaintenanceAssetCheck $record): bool => $record->status === PreventiveMaintenanceAssetCheckStatus::Pending)
                    ->action(fn (PreventiveMaintenanceAssetCheck $record): PreventiveMaintenanceAssetCheck => app(PmsInspectionService::class)->startInspection($record, auth()->user())),
                Action::make('completeInspection')
                    ->label('Complete Checklist')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (PreventiveMaintenanceAssetCheck $record): bool => in_array($record->status, [PreventiveMaintenanceAssetCheckStatus::Pending, PreventiveMaintenanceAssetCheckStatus::InProgress], true))
                    ->form(fn (PreventiveMaintenanceAssetCheck $record): array => [
                        ...$this->inspectionFields($record),
                        Select::make('inspection_status')->label('Inspection Result')->options(PreventiveMaintenanceAssetCheckStatus::resultOptions())->required(),
                        Textarea::make('remarks')->columnSpanFull(),
                    ])
                    ->action(function (PreventiveMaintenanceAssetCheck $record, array $data): void {
                        $values = collect($data)->filter(fn (mixed $value, string $key): bool => Str::startsWith($key, 'result_'))->mapWithKeys(fn (mixed $value, string $key): array => [(int) Str::after($key, 'result_') => $value])->all();
                        app(PmsInspectionService::class)->completeInspection($record, auth()->user(), PreventiveMaintenanceAssetCheckStatus::from($data['inspection_status']), $values, $data['remarks'] ?? null);
                    }),
                Action::make('createRepairTicket')
                    ->label('Create Helpdesk Ticket')
                    ->icon('heroicon-o-ticket')
                    ->color('danger')
                    ->visible(fn (PreventiveMaintenanceAssetCheck $record): bool => $record->status === PreventiveMaintenanceAssetCheckStatus::NeedsRepair && ! $record->ticket_id && (auth()->user()?->can('create_ticket') ?? false))
                    ->action(function (PreventiveMaintenanceAssetCheck $record): void {
                        $ticket = app(PmsInspectionService::class)->createRepairTicket($record, auth()->user());
                        $this->redirect(TicketResource::getUrl('view', ['record' => $ticket]));
                    }),
                Action::make('viewInspection')->label('View')->icon('heroicon-o-eye')->url(fn (PreventiveMaintenanceAssetCheck $record): string => PreventiveMaintenanceAssetCheckResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('id');
    }

    private function inspectionFields(PreventiveMaintenanceAssetCheck $record): array
    {
        $record->loadMissing('checklistTemplate.items');

        return $record->checklistTemplate->items->map(function (PmsChecklistItem $item) {
            $field = match ($item->input_type) {
                'checkbox' => Checkbox::make("result_{$item->id}"),
                'pass_fail' => Radio::make("result_{$item->id}")->options(['pass' => 'Pass', 'fail' => 'Fail'])->inline(),
                'number' => TextInput::make("result_{$item->id}")->numeric(),
                'select' => Select::make("result_{$item->id}")->options(collect($item->options ?? [])->mapWithKeys(fn (string $option): array => [$option => $option])->all()),
                default => TextInput::make("result_{$item->id}"),
            };

            return $field->label($item->label)->required($item->is_required);
        })->all();
    }
}
