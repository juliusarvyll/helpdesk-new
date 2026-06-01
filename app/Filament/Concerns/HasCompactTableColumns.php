<?php

namespace App\Filament\Concerns;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

trait HasCompactTableColumns
{
    protected static function compactTextColumn(TextColumn $column, int $limit = 32): TextColumn
    {
        return $column
            ->limit($limit)
            ->tooltip(fn (TextColumn $column): ?string => static::compactColumnTooltip($column));
    }

    protected static function compactColumnTooltip(TextColumn $column): ?string
    {
        $limit = $column->getCharacterLimit();

        if ($limit === null) {
            return null;
        }

        $state = $column->getState();

        if (blank($state)) {
            return null;
        }

        $text = is_array($state)
            ? collect($state)->filter()->join(', ')
            : (string) $state;

        return Str::length($text) > $limit ? $text : null;
    }
}
