<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\InventoryCategoryResource\Pages;
use App\Models\InventoryCategory;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class InventoryCategoryResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = InventoryCategory::class;

    protected static ?string $tenantOwnershipRelationshipName = 'department';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('type')
                ->required()
                ->maxLength(255)
                ->datalist(fn (): array => array_values(static::categoryTypeOptions())),
            Toggle::make('is_it_asset')
                ->label('IT Asset Category')
                ->helperText('IT Assets are serviced through the Helpdesk Ticket system. Non-IT Assets are serviced through Job Orders.'),
            Select::make('parent_id')
                ->relationship(
                    'parent',
                    'name',
                    fn ($query) => $query
                        ->where('is_deleted', false)
                        ->where('department_id', Filament::getTenant()?->id)
                )
                ->searchable()
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::compactTextColumn(TextColumn::make('name'), 32)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::categoryTypeLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'asset' => 'success',
                        'consumable' => 'warning',
                        'license' => 'info',
                        'peripheral' => 'gray',
                        'spare_part' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('is_it_asset')
                    ->label('Asset Classification')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'IT Asset' : 'Non-IT Asset')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                static::compactTextColumn(TextColumn::make('parent.name'), 32)
                    ->label('Parent Category')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('type')
                    ->options(fn (): array => static::categoryTypeOptions()),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->visible(fn (): bool => auth()->user()?->can('delete_inventory::category') ?? false)
                        ->action(fn (Collection $records): int => $records->toQuery()->update(['is_deleted' => true])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryCategories::route('/'),
            'create' => Pages\CreateInventoryCategory::route('/create'),
            'edit' => Pages\EditInventoryCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_deleted', false);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_inventory::category') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_inventory::category') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_inventory::category') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_inventory::category') ?? false;
    }

    /**
     * @return array<string, string>
     */
    private static function categoryTypeOptions(): array
    {
        $defaults = [
            'asset' => 'Asset',
            'consumable' => 'Consumable',
            'license' => 'License',
            'peripheral' => 'Peripheral',
            'spare_part' => 'Spare Part',
        ];

        $customTypes = InventoryCategory::query()
            ->where('department_id', Filament::getTenant()?->id)
            ->whereNotNull('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->mapWithKeys(fn (string $type): array => [$type => static::categoryTypeLabel($type)])
            ->all();

        return [...$defaults, ...$customTypes];
    }

    private static function categoryTypeLabel(string $type): string
    {
        return Str::of($type)->replace('_', ' ')->headline()->toString();
    }
}
