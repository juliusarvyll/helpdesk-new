<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_inventory_transaction(): void
    {
        $item = InventoryItem::factory()->create();
        $user = User::factory()->create();

        $transaction = InventoryTransaction::factory()->create([
            'inventory_item_id' => $item->id,
            'user_id' => $user->id,
            'type' => 'created',
        ]);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'type' => 'created',
        ]);
    }

    public function test_transaction_belongs_to_inventory_item(): void
    {
        $item = InventoryItem::factory()->create(['name' => 'Test Item']);
        $transaction = InventoryTransaction::factory()->create(['inventory_item_id' => $item->id]);

        $this->assertEquals('Test Item', $transaction->inventoryItem->name);
    }

    public function test_transaction_belongs_to_user(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        $transaction = InventoryTransaction::factory()->create(['user_id' => $user->id]);

        $this->assertEquals('John Doe', $transaction->user->name);
    }

    public function test_transaction_can_be_linked_to_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $transaction = InventoryTransaction::factory()->create(['ticket_id' => $ticket->id]);

        $this->assertEquals($ticket->id, $transaction->ticket->id);
    }

    public function test_assigned_transaction_has_assigned_to_user(): void
    {
        $assignedUser = User::factory()->create();
        $transaction = InventoryTransaction::factory()->assigned()->create([
            'assigned_to_user_id' => $assignedUser->id,
        ]);

        $this->assertEquals('assigned', $transaction->type);
        $this->assertEquals($assignedUser->id, $transaction->assignedToUser->id);
    }

    public function test_consumed_transaction_has_quantity(): void
    {
        $transaction = InventoryTransaction::factory()->consumed()->create([
            'quantity' => 5,
        ]);

        $this->assertEquals('consumed', $transaction->type);
        $this->assertEquals(5, $transaction->quantity);
    }

    public function test_transaction_metadata_is_cast_to_array(): void
    {
        $transaction = InventoryTransaction::factory()->create([
            'metadata' => ['reason' => 'Replacement', 'notes' => 'Urgent'],
        ]);

        $this->assertIsArray($transaction->metadata);
        $this->assertEquals('Replacement', $transaction->metadata['reason']);
    }
}
