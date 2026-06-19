<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\IssueCategoryResource\Pages;
use App\Filament\Resources\IssueCategoryResource\RelationManagers\IssueListRelationManager;
use App\Models\IssueCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IssueCategoryResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = IssueCategory::class;

    protected static bool $isScopedToTenant = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Helpdesk';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            TextEntry::make('name'),
            TextEntry::make('issueList_count')
                ->label('Issues')
                ->state(fn (IssueCategory $record): int => $record->issueList()->count())
                ->badge()
                ->color('info'),
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
                TextColumn::make('issueList_count')
                    ->label('Issues')
                    ->counts('issueList')
                    ->sortable()
                    ->badge()
                    ->color('info'),
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
            ->actions([ViewAction::make(), EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            IssueListRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIssueCategories::route('/'),
            'create' => Pages\CreateIssueCategory::route('/create'),
            'view' => Pages\ViewIssueCategory::route('/{record}'),
            'edit' => Pages\EditIssueCategory::route('/{record}/edit'),
        ];
    }
}
