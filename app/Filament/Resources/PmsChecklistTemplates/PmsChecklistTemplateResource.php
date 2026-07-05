<?php

namespace App\Filament\Resources\PmsChecklistTemplates;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\PmsChecklistTemplates\Pages\CreatePmsChecklistTemplate;
use App\Filament\Resources\PmsChecklistTemplates\Pages\EditPmsChecklistTemplate;
use App\Filament\Resources\PmsChecklistTemplates\Pages\ListPmsChecklistTemplates;
use App\Filament\Resources\PmsChecklistTemplates\Pages\ViewPmsChecklistTemplate;
use App\Filament\Resources\PmsChecklistTemplates\RelationManagers\ItemsRelationManager;
use App\Models\PmsChecklistTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PmsChecklistTemplateResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = PmsChecklistTemplate::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'PMS Checklist Templates';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Checklist Template')->schema([
                TextInput::make('name')->label('Template Name')->required()->maxLength(255),
                Toggle::make('is_active')->label('Active')->default(true),
                Textarea::make('description')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Checklist Template')->schema([
                TextEntry::make('name'),
                TextEntry::make('is_active')->label('Status')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextEntry::make('description')->columnSpanFull()->placeholder('No description'),
                TextEntry::make('creator.name')->label('Created By'),
                TextEntry::make('created_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::compactTextColumn(TextColumn::make('name'), 36)->searchable()->sortable(),
                TextColumn::make('items_count')->counts('items')->label('Items Count')->sortable(),
                TextColumn::make('is_active')->label('Status')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                static::compactTextColumn(TextColumn::make('creator.name'), 28)->label('Created By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPmsChecklistTemplates::route('/'),
            'create' => CreatePmsChecklistTemplate::route('/create'),
            'view' => ViewPmsChecklistTemplate::route('/{record}'),
            'edit' => EditPmsChecklistTemplate::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }
}
