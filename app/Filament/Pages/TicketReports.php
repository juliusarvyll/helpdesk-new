<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\User;
use App\TicketStatus;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class TicketReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Helpdesk';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Ticket Reports';

    protected static ?string $slug = 'ticket-reports';

    protected static string $view = 'filament.pages.ticket-reports';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'status' => [],
            'priority' => [],
            'department_id' => Filament::getTenant()?->id,
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
                Section::make('PDF Report Maker')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->multiple()
                            ->options(TicketStatus::options())
                            ->native(false)
                            ->searchable(),
                        Select::make('priority')
                            ->label('Priority')
                            ->multiple()
                            ->options([
                                'low' => 'Low',
                                'normal' => 'Normal',
                                'critical' => 'Critical',
                            ])
                            ->native(false)
                            ->searchable(),
                        Select::make('assignment')
                            ->label('Assignment')
                            ->options([
                                'assigned' => 'Assigned',
                                'unassigned' => 'Unassigned',
                            ])
                            ->native(false)
                            ->placeholder('Any'),
                        Select::make('department_id')
                            ->label('Department')
                            ->options(fn (): array => $this->departmentOptions())
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->placeholder('Current / all allowed'),
                        Select::make('client_id')
                            ->label('Client')
                            ->options(fn (): array => $this->clientOptions())
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->placeholder('Any'),
                        Select::make('technical_support_user_id')
                            ->label('Technical Support')
                            ->options(fn (): array => $this->technicalSupportUserOptions())
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->placeholder('Any'),
                        DatePicker::make('created_from')
                            ->label('Created From')
                            ->native(false),
                        DatePicker::make('created_until')
                            ->label('Created Until')
                            ->native(false),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int, string>
     */
    public function departmentOptions(): array
    {
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return Department::query()
                ->where('is_deleted', 0)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return $user?->departments()
            ->where('department.is_deleted', 0)
            ->orderBy('department.name')
            ->pluck('department.name', 'department.id')
            ->all() ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function clientOptions(): array
    {
        return User::role('client')
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function technicalSupportUserOptions(): array
    {
        return User::role(['super_admin', 'admin', 'technical_support'])
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function generatePdf()
    {
        return redirect()->route('reports.tickets.pdf', $this->form->getState());
    }
}
