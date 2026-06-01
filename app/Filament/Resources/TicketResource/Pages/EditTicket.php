<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Notifications\TicketAssigned;
use App\TicketStatus;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * @var array<int, int>
     */
    protected array $originalTechnicalSupportUserIds = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['department']);

        $data = TicketResource::sanitizeTechnicalSupportAssignmentData($data);

        return TicketResource::sanitizeClientTicketData($data);
    }

    protected function beforeSave(): void
    {
        $this->originalTechnicalSupportUserIds = $this->record
            ->technicalSupportUsers()
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (TicketResource::canManageTechnicalSupportAssignments()) {
            if (TicketResource::canShowStatusTransitionAction($this->record, 'startProgress')) {
                $actions[] = Action::make('start_progress')
                    ->label('Start Progress')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn () => $this->record->transitionTo(TicketStatus::OnProgress) && $this->refreshFormData(['status']));
            }

            if (TicketResource::canShowStatusTransitionAction($this->record, 'markPending')) {
                $actions[] = Action::make('mark_pending')
                    ->label('Mark Pending')
                    ->icon('heroicon-o-pause')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn () => $this->record->transitionTo(TicketStatus::Pending) && $this->refreshFormData(['status']));
            }

            if (TicketResource::canShowStatusTransitionAction($this->record, 'close')) {
                $actions[] = Action::make('close')
                    ->label('Close Ticket')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Textarea::make('technical_support_remarks')
                            ->label('Technical Support Remarks')
                            ->default(fn (): ?string => $this->record->technical_support_remarks)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (array $data): void {
                        $this->record->forceFill([
                            'technical_support_remarks' => $data['technical_support_remarks'],
                        ]);

                        $this->record->transitionTo(TicketStatus::Closed);
                        $this->refreshFormData(['status', 'technical_support_remarks']);
                    });
            }
        }

        $actions[] = DeleteAction::make()
            ->visible(fn (): bool => auth()->user()->can('delete', $this->record));

        return $actions;
    }

    protected function afterSave(): void
    {
        if (! TicketResource::canManageTechnicalSupportAssignments()) {
            return;
        }

        $this->record->load('technicalSupportUsers');
        $this->record->syncAssignmentState();

        $newlyAssigned = $this->record->technicalSupportUsers
            ->whereNotIn('id', $this->originalTechnicalSupportUserIds);

        foreach ($newlyAssigned as $user) {
            $user->notify(new TicketAssigned($this->record));
        }
    }
}
