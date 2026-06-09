<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Account';

    protected static ?string $navigationLabel = 'My Profile';

    protected static ?string $title = 'My Profile';

    protected static ?string $slug = 'my-profile';

    protected static string $view = 'filament.pages.my-profile';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            'name' => $user?->name,
            'email' => $user?->email,
            'username' => $this->hasUserColumn('username') ? $user?->username : null,
            'contact' => $this->hasUserColumn('contact') ? $user?->contact : null,
            'address' => $this->hasUserColumn('address') ? $user?->address : null,
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessPanel(Filament::getCurrentPanel()) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Profile Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('username')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (): bool => $this->hasUserColumn('username')),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('contact')
                            ->maxLength(255)
                            ->visible(fn (): bool => $this->hasUserColumn('contact')),
                        TextInput::make('address')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->visible(fn (): bool => $this->hasUserColumn('address')),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),
                Section::make('Change Password')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->revealable()
                            ->autocomplete('current-password'),
                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password'),
                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @throws ValidationException
     */
    public function save(): void
    {
        $state = $this->form->getState();

        $validated = Validator::make($state, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            ...($this->hasUserColumn('username') ? ['username' => ['required', 'string', 'max:255']] : []),
            ...($this->hasUserColumn('contact') ? ['contact' => ['nullable', 'string', 'max:255']] : []),
            ...($this->hasUserColumn('address') ? ['address' => ['nullable', 'string', 'max:255']] : []),
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'password_confirmation' => ['nullable', 'required_with:password'],
        ])->validate();

        $user = auth()->user();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($this->hasUserColumn('username')) {
            $user->username = $validated['username'];
        }

        if ($this->hasUserColumn('contact')) {
            $user->contact = $validated['contact'] ?? null;
        }

        if ($this->hasUserColumn('address')) {
            $user->address = $validated['address'] ?? null;
        }

        if (filled($validated['password'] ?? null)) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        $this->form->fill([
            ...$this->form->getState(),
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        Notification::make()
            ->title('Profile updated.')
            ->success()
            ->send();
    }

    private function hasUserColumn(string $column): bool
    {
        return Schema::hasColumn('users', $column);
    }
}
