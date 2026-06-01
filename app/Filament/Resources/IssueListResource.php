<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\IssueListResource\Pages;
use App\Models\IssueCategory;
use App\Models\IssueList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IssueListResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = IssueList::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Helpdesk';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('issue_category_id')
                ->label('Category')
                ->options(IssueCategory::pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('issue')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('category.name'), 28)
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                static::compactTextColumn(TextColumn::make('issue'), 40)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('category.name')
            ->filters([
                SelectFilter::make('issue_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->multiple(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIssueLists::route('/'),
            'create' => Pages\CreateIssueList::route('/create'),
            'edit' => Pages\EditIssueList::route('/{record}/edit'),
        ];
    }
}
