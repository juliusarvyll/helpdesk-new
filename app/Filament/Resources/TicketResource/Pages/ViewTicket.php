<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Ticket Information')
                ->schema([
                    TextEntry::make('subject')->columnSpanFull(),
                    TextEntry::make('description')->columnSpanFull()->markdown(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(fn ($state) => $state->color()),
                    TextEntry::make('priority')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'low' => 'success',
                            'normal' => 'info',
                            'critical' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('issue.category.name')->label('Category'),
                    TextEntry::make('issue.issue')->label('Issue'),
                ])->columns(2),
            Section::make('Assignment')
                ->schema([
                    TextEntry::make('client.name')->label('Client'),
                    TextEntry::make('technicalSupportUsers.name')
                        ->label('Technical Support')
                        ->badge()
                        ->visible(fn (): bool => TicketResource::canManageTechnicalSupportAssignments())
                        ->listWithLineBreaks(),
                    TextEntry::make('assignment_status')
                        ->label('Assignment Status')
                        ->badge()
                        ->state(fn ($record): string => $record->technicalSupportUsers->isNotEmpty() ? 'Assigned' : 'Not Assigned')
                        ->color(fn ($record): string => $record->technicalSupportUsers->isNotEmpty() ? 'success' : 'warning')
                        ->visible(fn (): bool => TicketResource::canManageTechnicalSupportAssignments()),
                    TextEntry::make('client.department.name')->label('Department'),
                ])->columns(2),
            Section::make('Timeline')
                ->schema([
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('start_time')->dateTime(),
                    TextEntry::make('end_time')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ])->columns(2),
            Section::make('Remarks')
                ->schema([
                    TextEntry::make('technical_support_remarks')
                        ->columnSpanFull()
                        ->markdown()
                        ->visible(fn (): bool => TicketResource::canManageTechnicalSupportAssignments()),
                    TextEntry::make('client_comments')->columnSpanFull()->markdown(),
                ]),
        ]);
    }
}
