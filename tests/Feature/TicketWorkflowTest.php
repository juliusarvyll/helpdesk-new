<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_can_transition_from_active_to_on_progress(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);

        $result = $ticket->transitionTo(TicketStatus::OnProgress);

        $this->assertTrue($result);
        $this->assertEquals(TicketStatus::OnProgress, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->start_time);
    }

    public function test_ticket_can_transition_from_on_progress_to_closed(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::OnProgress,
            'technical_support_remarks' => 'Resolved by resetting the user password.',
        ]);

        $result = $ticket->transitionTo(TicketStatus::Closed);

        $this->assertTrue($result);
        $this->assertEquals(TicketStatus::Closed, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->end_time);
    }

    public function test_ticket_cannot_transition_from_closed_to_active(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed]);

        $result = $ticket->transitionTo(TicketStatus::Active);

        $this->assertFalse($result);
        $this->assertEquals(TicketStatus::Closed, $ticket->fresh()->status);
    }

    public function test_ticket_can_transition_from_active_to_pending(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Active]);

        $result = $ticket->transitionTo(TicketStatus::Pending);

        $this->assertTrue($result);
        $this->assertEquals(TicketStatus::Pending, $ticket->fresh()->status);
    }

    public function test_ticket_can_transition_from_pending_to_on_progress(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Pending]);

        $result = $ticket->transitionTo(TicketStatus::OnProgress);

        $this->assertTrue($result);
        $this->assertEquals(TicketStatus::OnProgress, $ticket->fresh()->status);
    }

    public function test_ticket_can_transition_from_pending_to_closed_with_technical_support_remarks(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Pending,
            'technical_support_remarks' => 'Resolved after confirming the requester no longer needs access.',
        ]);

        $result = $ticket->transitionTo(TicketStatus::Closed);

        $this->assertTrue($result);
        $this->assertEquals(TicketStatus::Closed, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->end_time);
    }

    public function test_ticket_cannot_close_without_technical_support_remarks(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::OnProgress]);

        $result = $ticket->transitionTo(TicketStatus::Closed);

        $this->assertFalse($result);
        $this->assertEquals(TicketStatus::OnProgress, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->end_time);
    }

    public function test_ticket_status_enum_has_correct_labels(): void
    {
        $this->assertEquals('Active', TicketStatus::Active->label());
        $this->assertEquals('On Progress', TicketStatus::OnProgress->label());
        $this->assertEquals('Pending', TicketStatus::Pending->label());
        $this->assertEquals('Overdue', TicketStatus::Overdue->label());
        $this->assertEquals('Closed', TicketStatus::Closed->label());
    }

    public function test_ticket_status_enum_has_correct_colors(): void
    {
        $this->assertEquals('success', TicketStatus::Active->color());
        $this->assertEquals('warning', TicketStatus::OnProgress->color());
        $this->assertEquals('gray', TicketStatus::Pending->color());
        $this->assertEquals('danger', TicketStatus::Overdue->color());
        $this->assertEquals('info', TicketStatus::Closed->color());
    }
}
