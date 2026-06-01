<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
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
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as ShieldRole;

class UserResource extends Resource
{
    use HasCompactTableColumns;

    protected const ASSIGNABLE_SHIELD_ROLES = ['super_admin', 'admin', 'technical_support', 'client'];

    protected static ?string $model = User::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('username')->required(),
            TextInput::make('email')->email()->required(),
            TextInput::make('password')->password()->dehydrateStateUsing(fn ($state) => bcrypt($state))
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),
            TextInput::make('address'),
            TextInput::make('contact'),
            Select::make('department_id')
                ->label('Primary Department')
                ->relationship('department', 'name', fn ($query) => $query->where('department.is_deleted', 0))
                ->searchable()
                ->preload()
                ->required(),
            Select::make('departments')
                ->label('Tenant Departments')
                ->relationship('departments', 'name', fn (Builder $query) => $query->where('department.is_deleted', 0))
                ->multiple()
                ->searchable()
                ->preload()
                ->helperText('Controls which department workspaces this user can access. The primary department is always included.'),
            Select::make('position_id')
                ->relationship('position', 'name', fn ($query) => $query->where('is_deleted', 0))
                ->searchable()
                ->preload(),
            Select::make('roles')
                ->label('Roles')
                ->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', static::ASSIGNABLE_SHIELD_ROLES))
                ->multiple()
                ->options(fn (): array => static::shieldRoleOptions())
                ->preload()
                ->searchable()
                ->required(),
            Select::make('status')
                ->options([1 => 'Active', 0 => 'Inactive'])
                ->default(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('name'), 28)
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('username'), 24)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('email'), 30)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),
                static::compactTextColumn(TextColumn::make('contact'), 20)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-m-phone'),
                static::compactTextColumn(TextColumn::make('department.name'), 28)
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                static::compactTextColumn(TextColumn::make('position.name'), 28)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (int $state): string => $state === 1 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? 'Active' : 'Inactive'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', static::ASSIGNABLE_SHIELD_ROLES))
                    ->multiple()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([1 => 'Active', 0 => 'Inactive']),
                SelectFilter::make('department_id')
                    ->relationship('department', 'name', fn ($query) => $query->where('department.is_deleted', 0))
                    ->searchable()
                    ->preload(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function syncPrimaryDepartmentTenant(User $user): void
    {
        if (! $user->department_id) {
            return;
        }

        $user->departments()->syncWithoutDetaching([$user->department_id]);
    }

    /**
     * @return array<int, string>
     */
    public static function shieldRoleOptions(): array
    {
        return ShieldRole::query()
            ->whereIn('name', static::ASSIGNABLE_SHIELD_ROLES)
            ->get(['id', 'name'])
            ->sortBy(fn (ShieldRole $role): int => array_search($role->name, static::ASSIGNABLE_SHIELD_ROLES, true))
            ->pluck('name', 'id')
            ->all();
    }
}
