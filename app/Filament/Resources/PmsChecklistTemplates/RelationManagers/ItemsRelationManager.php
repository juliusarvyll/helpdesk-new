<?php

namespace App\Filament\Resources\PmsChecklistTemplates\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Checklist Items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->required()->maxLength(255),
            Select::make('input_type')->label('Input Type')->options([
                'checkbox' => 'Checkbox',
                'pass_fail' => 'Pass / Fail',
                'text' => 'Text',
                'number' => 'Number',
                'select' => 'Select',
            ])->required()->live(),
            TagsInput::make('options')->helperText('Enter the available choices for Select fields.')
                ->visible(fn (Get $get): bool => $get('input_type') === 'select'),
            Toggle::make('is_required')->label('Required'),
            TextInput::make('sort_order')->numeric()->minValue(0)->default(0)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('input_type')->label('Input Type')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->headline()->toString()),
                TextColumn::make('is_required')->label('Required')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Required' : 'Optional')->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                TextColumn::make('sort_order')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
