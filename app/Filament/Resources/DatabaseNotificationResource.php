<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\DatabaseNotificationResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

class DatabaseNotificationResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = DatabaseNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $navigationGroup = 'System';

    protected static bool $isScopedToTenant = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('read_at')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-o-envelope')
                    ->trueColor('gray')
                    ->falseColor('primary'),
                static::compactTextColumn(TextColumn::make('data.title'), 36)
                    ->label('Title')
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('data.body'), 44)
                    ->label('Message')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('notifiable_id', auth()->id()))
            ->actions([
                Action::make('markAsRead')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check')
                    ->visible(fn ($record) => $record->read_at === null)
                    ->action(fn ($record) => $record->markAsRead()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatabaseNotifications::route('/'),
        ];
    }
}
