<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = Department::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->required(),
            Select::make('unit_head')
                ->relationship('unitHeadUser', 'name', fn ($query) => $query->where('status', 1)->where('is_deleted', 0))
                ->searchable()
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('name'), 32)
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('unitHeadUser.name'), 28)
                    ->label('Unit Head')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
