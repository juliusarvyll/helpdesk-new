<?php

namespace App;

use App\Models\InventoryItemSerialNumber;
use App\Models\Location;
use App\Models\PmsChecklistTemplate;
use App\Models\PreventiveMaintenanceAssetCheck;
use App\Models\PreventiveMaintenanceSession;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\PmsRepairNeeded;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsInspectionService
{
    public function __construct(
        public InventoryTicketDefaults $defaults,
        public TicketCreationService $tickets,
    ) {}

    public function startSession(Location $location, User $starter, ?PmsChecklistTemplate $template = null, ?string $remarks = null): PreventiveMaintenanceSession
    {
        $this->authorizePmsUser($starter, $location->department_id);

        return DB::transaction(function () use ($location, $starter, $template, $remarks): PreventiveMaintenanceSession {
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);

            if ($location->preventiveMaintenanceSessions()->where('status', PreventiveMaintenanceSessionStatus::Active->value)->exists()) {
                throw ValidationException::withMessages(['location_id' => 'This location already has an active PMS session.']);
            }

            $template ??= PmsChecklistTemplate::query()->where('is_active', true)->oldest()->first();

            if (! $template) {
                throw ValidationException::withMessages(['checklist_template_id' => 'Create an active PMS checklist template before starting a session.']);
            }

            if (! $template->is_active || ! $template->items()->exists()) {
                throw ValidationException::withMessages(['checklist_template_id' => 'Select an active PMS checklist template that contains checklist items.']);
            }

            $serialNumbers = $location->itAssetSerialNumbers()->with('inventoryItem')->get();

            if ($serialNumbers->isEmpty()) {
                throw ValidationException::withMessages(['location_id' => 'This location has no active IT asset serial numbers to inspect.']);
            }

            $session = PreventiveMaintenanceSession::create([
                'department_id' => $location->department_id,
                'location_id' => $location->id,
                'started_by' => $starter->id,
                'started_at' => now(),
                'status' => PreventiveMaintenanceSessionStatus::Active,
                'remarks' => $remarks,
            ]);

            $session->assetChecks()->createMany($serialNumbers->map(fn (InventoryItemSerialNumber $serialNumber): array => [
                'inventory_item_id' => $serialNumber->inventory_item_id,
                'inventory_item_serial_number_id' => $serialNumber->id,
                'checklist_template_id' => $template->id,
                'status' => PreventiveMaintenanceAssetCheckStatus::Pending,
            ])->all());

            return $session->load('assetChecks');
        });
    }

    public function completeInspection(PreventiveMaintenanceAssetCheck $assetCheck, User $inspector, PreventiveMaintenanceAssetCheckStatus $status, array $values, ?string $remarks = null): PreventiveMaintenanceAssetCheck
    {
        if (! in_array($status, [PreventiveMaintenanceAssetCheckStatus::Passed, PreventiveMaintenanceAssetCheckStatus::Failed, PreventiveMaintenanceAssetCheckStatus::NeedsRepair], true)) {
            throw ValidationException::withMessages(['status' => 'Select a valid completed PMS result.']);
        }

        return DB::transaction(function () use ($assetCheck, $inspector, $status, $values, $remarks): PreventiveMaintenanceAssetCheck {
            $assetCheck = PreventiveMaintenanceAssetCheck::query()->lockForUpdate()->with(['session', 'serialNumber.inventoryItem', 'checklistTemplate.items'])->findOrFail($assetCheck->id);

            $this->authorizePmsUser($inspector, $assetCheck->session->department_id);

            if ($assetCheck->session->status !== PreventiveMaintenanceSessionStatus::Active) {
                throw ValidationException::withMessages(['session_id' => 'Only active PMS sessions may be inspected.']);
            }

            if (! in_array($assetCheck->status, [PreventiveMaintenanceAssetCheckStatus::Pending, PreventiveMaintenanceAssetCheckStatus::InProgress], true)) {
                throw ValidationException::withMessages(['status' => 'This PMS inspection has already been completed.']);
            }

            if (! $assetCheck->serialNumber->isActiveForPms()) {
                throw ValidationException::withMessages(['inventory_item_serial_number_id' => 'Only active IT asset serial numbers may be inspected.']);
            }

            foreach ($assetCheck->checklistTemplate->items as $item) {
                $value = $values[$item->id] ?? null;

                if ($item->is_required && blank($value) && $value !== false && $value !== 0 && $value !== '0') {
                    throw ValidationException::withMessages(["results.{$item->id}" => "{$item->label} is required."]);
                }

                if ($value === null) {
                    continue;
                }

                $assetCheck->results()->updateOrCreate(
                    ['checklist_item_id' => $item->id],
                    ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
                );
            }

            $assetCheck->forceFill([
                'checked_by' => $inspector->id,
                'status' => $status,
                'started_at' => $assetCheck->started_at ?? now(),
                'completed_at' => now(),
                'remarks' => $remarks,
            ])->save();

            $assetCheck->session->refreshCompletionState();

            if ($status === PreventiveMaintenanceAssetCheckStatus::NeedsRepair) {
                User::role('technical_support')
                    ->canAccessDepartment($assetCheck->session->department_id)
                    ->where('status', 1)
                    ->where('is_deleted', 0)
                    ->get()
                    ->each->notify(new PmsRepairNeeded($assetCheck));
            }

            return $assetCheck->refresh()->load('results');
        });
    }

    public function startInspection(PreventiveMaintenanceAssetCheck $assetCheck, User $inspector): PreventiveMaintenanceAssetCheck
    {
        return DB::transaction(function () use ($assetCheck, $inspector): PreventiveMaintenanceAssetCheck {
            $assetCheck = PreventiveMaintenanceAssetCheck::query()
                ->lockForUpdate()
                ->with(['session', 'serialNumber.inventoryItem'])
                ->findOrFail($assetCheck->id);

            $this->authorizePmsUser($inspector, $assetCheck->session->department_id);

            if ($assetCheck->session->status !== PreventiveMaintenanceSessionStatus::Active || $assetCheck->status !== PreventiveMaintenanceAssetCheckStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Only pending inspections in an active PMS session may be started.']);
            }

            if (! $assetCheck->serialNumber->isActiveForPms()) {
                throw ValidationException::withMessages(['inventory_item_serial_number_id' => 'Only active IT asset serial numbers may be inspected.']);
            }

            $assetCheck->forceFill([
                'status' => PreventiveMaintenanceAssetCheckStatus::InProgress,
                'checked_by' => $inspector->id,
                'started_at' => now(),
            ])->save();

            return $assetCheck->refresh();
        });
    }

    public function createRepairTicket(PreventiveMaintenanceAssetCheck $assetCheck, User $creator): Ticket
    {
        return DB::transaction(function () use ($assetCheck, $creator): Ticket {
            $assetCheck = PreventiveMaintenanceAssetCheck::query()
                ->lockForUpdate()
                ->with(['session', 'inventoryItem', 'serialNumber', 'ticket'])
                ->findOrFail($assetCheck->id);

            $this->authorizePmsUser($creator, $assetCheck->session->department_id);

            if (! $creator->can('create_ticket')) {
                throw new AuthorizationException('You are not authorized to create a repair ticket.');
            }

            if ($assetCheck->status !== PreventiveMaintenanceAssetCheckStatus::NeedsRepair) {
                throw ValidationException::withMessages(['status' => 'A repair ticket can only be created for an inspection marked Needs Repair.']);
            }

            if ($assetCheck->ticket_id) {
                return $assetCheck->ticket;
            }

            $ticket = $this->tickets->create([
                'subject' => $this->defaults->subject($assetCheck->inventoryItem, $assetCheck->inventory_item_serial_number_id),
                'description' => $this->defaults->description($assetCheck->inventoryItem, $assetCheck->inventory_item_serial_number_id).PHP_EOL.PHP_EOL.'PMS Result: Needs Repair'.PHP_EOL.($assetCheck->remarks ?? ''),
                'priority' => 'normal',
                'client_id' => $this->defaults->clientId($assetCheck->inventoryItem, $assetCheck->inventory_item_serial_number_id, $creator),
                'department_id' => $assetCheck->session->department_id,
                'inventory_item_id' => $assetCheck->inventory_item_id,
                'inventory_item_serial_number_id' => $assetCheck->inventory_item_serial_number_id,
                'asset_id' => $assetCheck->inventoryItem->asset_tag,
                'asset_name' => $assetCheck->inventoryItem->name,
            ], $creator);

            $assetCheck->forceFill(['ticket_id' => $ticket->id])->save();

            return $ticket;
        });
    }

    private function authorizePmsUser(User $user, int $departmentId): void
    {
        $hasAccess = $user->hasAnyRole(['super_admin', 'admin', 'technical_support'])
            && User::query()->whereKey($user->getKey())->canAccessDepartment($departmentId)->exists();

        if (! $hasAccess) {
            throw new AuthorizationException('You are not authorized to access PMS for this department.');
        }
    }
}
