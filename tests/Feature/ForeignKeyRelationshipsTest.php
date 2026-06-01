<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\IssueCategory;
use App\Models\IssueList;
use App\Models\Position;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForeignKeyRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_department_relationship(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);

        $this->assertInstanceOf(Department::class, $user->department);
        $this->assertEquals($department->id, $user->department->id);
    }

    public function test_user_has_position_relationship(): void
    {
        $position = Position::factory()->create();
        $user = User::factory()->create(['position_id' => $position->id]);

        $this->assertInstanceOf(Position::class, $user->position);
        $this->assertEquals($position->id, $user->position->id);
    }

    public function test_user_has_role_relationship(): void
    {
        $role = Role::create(['name' => 'Test Role']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertInstanceOf(Role::class, $user->roleRelation);
        $this->assertEquals($role->id, $user->roleRelation->id);
    }

    public function test_ticket_has_client_relationship(): void
    {
        $client = User::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        $this->assertInstanceOf(User::class, $ticket->client);
        $this->assertEquals($client->id, $ticket->client->id);
    }

    public function test_ticket_has_issue_relationship(): void
    {
        $category = IssueCategory::factory()->create();
        $issue = IssueList::factory()->create(['issue_category_id' => $category->id]);
        $ticket = Ticket::factory()->create(['issue_id' => $issue->id]);

        $this->assertInstanceOf(IssueList::class, $ticket->issue);
        $this->assertEquals($issue->id, $ticket->issue->id);
    }

    public function test_ticket_has_creator_relationship(): void
    {
        $creator = User::factory()->create();
        $ticket = Ticket::factory()->create(['created_by' => $creator->id]);

        $this->assertInstanceOf(User::class, $ticket->creator);
        $this->assertEquals($creator->id, $ticket->creator->id);
    }

    public function test_issue_list_has_category_relationship(): void
    {
        $category = IssueCategory::factory()->create();
        $issue = IssueList::factory()->create(['issue_category_id' => $category->id]);

        $this->assertInstanceOf(IssueCategory::class, $issue->category);
        $this->assertEquals($category->id, $issue->category->id);
    }
}
