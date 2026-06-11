<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\AzureAccountProvisioningResource\Pages;
use App\MicrosoftGraphService;
use App\Models\AzureAccountProvisioning;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;

class AzureAccountProvisioningResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = AzureAccountProvisioning::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Azure Accounts';

    protected static ?string $modelLabel = 'Azure Account';

    protected static ?string $pluralModelLabel = 'Azure Accounts';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('teams_redirect_uri')
                    ->label('Microsoft Teams Redirect URI')
                    ->content(fn (): string => route('microsoft-teams.oauth.callback'))
                    ->columnSpanFull(),
                Select::make('account_type')
                    ->options([
                        'student' => 'Student',
                        'faculty' => 'Faculty',
                    ])
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set): mixed => static::syncGeneratedStudentUserPrincipalName($get, $set))
                    ->required(),
                Placeholder::make('automatic_license')
                    ->label('Automatic License')
                    ->content(fn ($get): string => match ($get('account_type')) {
                        'student' => 'Microsoft 365 A3 Student Use Benefit (M365EDU_A3_STUUSEBNFT)',
                        'faculty' => 'Microsoft 365 A3 Faculty (M365EDU_A3_FACULTY)',
                        default => 'Choose an account type to see the assigned license.',
                    })
                    ->columnSpanFull(),
                TextInput::make('given_name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set): mixed => static::syncGeneratedStudentUserPrincipalName($get, $set))
                    ->required()
                    ->maxLength(255),
                TextInput::make('surname')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set): mixed => static::syncGeneratedStudentUserPrincipalName($get, $set))
                    ->required()
                    ->maxLength(255),
                TextInput::make('display_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('user_principal_name')
                    ->label('User Principal Name')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->disabled(fn (Get $get): bool => $get('account_type') === 'student')
                    ->dehydrated()
                    ->maxLength(255),
                TextInput::make('usage_location')
                    ->default('PH')
                    ->disabled()
                    ->dehydrated()
                    ->length(2)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::compactTextColumn(TextColumn::make('display_name'), 30)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === 'faculty' ? 'primary' : 'info'),
                static::compactTextColumn(TextColumn::make('user_principal_name'), 34)
                    ->label('UPN')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('license_sku_part_number')
                    ->label('License')
                    ->placeholder('No license')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'provisioned' => 'success',
                        'failed' => 'danger',
                        'user_created' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('azure_user_id')
                    ->label('Azure User ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('provisioned_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->options([
                        'student' => 'Student',
                        'faculty' => 'Faculty',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'user_created' => 'User Created',
                        'provisioned' => 'Provisioned',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Azure Password')
                    ->modalDescription('A new temporary password will be generated and the user will be required to change it on next sign-in.')
                    ->visible(fn (AzureAccountProvisioning $record): bool => filled($record->azure_user_id) || filled($record->user_principal_name))
                    ->action(function (AzureAccountProvisioning $record): void {
                        try {
                            app(MicrosoftGraphService::class)->resetPassword($record);

                            $record->refresh();

                            Notification::make()
                                ->title('Azure password reset')
                                ->body(new HtmlString(sprintf(
                                    '<div class="space-y-2"><div><strong>Email:</strong> <code>%s</code></div><div><strong>Temporary password:</strong> <code>%s</code></div></div>',
                                    e($record->user_principal_name),
                                    e($record->temporary_password),
                                )))
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (Throwable $throwable) {
                            $record->forceFill([
                                'status' => 'failed',
                                'last_error' => $throwable->getMessage(),
                            ])->save();

                            Notification::make()
                                ->title('Azure password reset failed')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('This deletes the user from Microsoft Entra ID first, then removes the local Azure Account record.')
                    ->using(fn (AzureAccountProvisioning $record): bool => static::deleteAzureAccountFromDirectory($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Each selected user will be deleted from Microsoft Entra ID first. Local records are removed only after the directory delete succeeds.')
                        ->action(function (Collection $records): void {
                            $result = static::deleteAzureAccountsFromDirectory($records);

                            if ($result['deleted'] > 0) {
                                Notification::make()
                                    ->title('Azure accounts deleted')
                                    ->body("Deleted {$result['deleted']} Azure account records from Microsoft Entra ID and this system.")
                                    ->success()
                                    ->send();
                            }

                            if ($result['failed'] > 0) {
                                Notification::make()
                                    ->title('Some Azure accounts could not be deleted')
                                    ->body("{$result['failed']} selected records were kept because Microsoft Entra ID deletion failed.")
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAzureAccountProvisionings::route('/'),
            'create' => Pages\CreateAzureAccountProvisioning::route('/create'),
            'edit' => Pages\EditAzureAccountProvisioning::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canUseFeature();
    }

    public static function canViewAny(): bool
    {
        return static::canUseFeature();
    }

    public static function canCreate(): bool
    {
        return static::canUseFeature();
    }

    public static function canEdit($record): bool
    {
        return static::canUseFeature();
    }

    public static function canDelete($record): bool
    {
        return static::canUseFeature();
    }

    public static function canDeleteAny(): bool
    {
        return static::canUseFeature();
    }

    public static function deleteAzureAccountFromDirectory(AzureAccountProvisioning $record): bool
    {
        try {
            app(MicrosoftGraphService::class)->deleteUser($record);

            return (bool) $record->delete();
        } catch (Throwable $throwable) {
            $record->forceFill([
                'status' => 'failed',
                'last_error' => $throwable->getMessage(),
            ])->save();

            Notification::make()
                ->title('Azure account delete failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();

            return false;
        }
    }

    /**
     * @param  Collection<int, AzureAccountProvisioning>  $records
     * @return array{deleted: int, failed: int}
     */
    public static function deleteAzureAccountsFromDirectory(Collection $records): array
    {
        $deleted = 0;
        $failed = 0;

        foreach ($records as $record) {
            if (static::deleteAzureAccountFromDirectory($record)) {
                $deleted++;

                continue;
            }

            $failed++;
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    private static function canUseFeature(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    private static function syncGeneratedStudentUserPrincipalName(Get $get, Set $set): null
    {
        if ($get('account_type') !== 'student') {
            return null;
        }

        $set('user_principal_name', static::generatedUserPrincipalName(
            $get('given_name'),
            $get('surname'),
        ));

        return null;
    }

    public static function generatedUserPrincipalName(?string $givenName, ?string $surname): string
    {
        $localPart = Str::of($givenName.' '.$surname)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();

        return "{$localPart}@spup.edu.ph";
    }
}
