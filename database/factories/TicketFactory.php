<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(['low', 'normal', 'critical']),
            'status' => TicketStatus::Active,
            'support_assignment_status' => 'Not Yet Assigned',
            'created_ticket' => fake()->name(),
        ];
    }
}
