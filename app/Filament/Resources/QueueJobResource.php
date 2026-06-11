<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QueueJobResource\Pages;
use App\Models\FailedJob;
use App\Models\QueueJob;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QueueJobResource extends Resource
{
    protected static ?string $model = QueueJob::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Queue Workers';

    protected static ?string $modelLabel = 'Queue Job';

    protected static ?string $pluralModelLabel = 'Queue Workers';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('queue')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('display_name')
                    ->label('Job')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'processing' ? 'warning' : 'gray'),
                TextColumn::make('attempts')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('available_at_date')
                    ->label('Available')
                    ->dateTime()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('available_at', $direction))
                    ->since(),
                TextColumn::make('reserved_at_date')
                    ->label('Reserved')
                    ->dateTime()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('reserved_at', $direction))
                    ->placeholder('Not reserved')
                    ->toggleable(),
                TextColumn::make('created_at_date')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('created_at', $direction))
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('queue')
                    ->options(fn (): array => QueueJob::query()
                        ->distinct()
                        ->orderBy('queue')
                        ->pluck('queue', 'queue')
                        ->all()),
                SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'processing' => 'Processing',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'queued' => $query->whereNull('reserved_at'),
                            'processing' => $query->whereNotNull('reserved_at'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = QueueJob::query()->count();
        $failed = FailedJob::query()->count();

        return $failed > 0 ? "{$pending} / {$failed} failed" : (string) $pending;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return FailedJob::query()->exists() ? 'danger' : 'gray';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQueueJobs::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
