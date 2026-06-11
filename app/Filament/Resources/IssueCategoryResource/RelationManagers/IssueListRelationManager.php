<?php

namespace App\Filament\Resources\IssueCategoryResource\RelationManagers;

use App\Filament\Concerns\HasCompactTableColumns;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IssueListRelationManager extends RelationManager
{
    use HasCompactTableColumns;

    protected static string $relationship = 'issueList';

    protected static ?string $title = 'Issues';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('issue')
            ->columns([
                static::compactTextColumn(TextColumn::make('issue'), 60)
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_deleted')
                    ->label('Deleted')
                    ->boolean()
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
            ->actions([EditAction::make()])
            ->defaultSort('issue');
    }
}
