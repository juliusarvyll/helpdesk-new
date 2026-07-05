<?php

namespace Database\Factories;

use App\Models\PmsChecklistItem;
use App\Models\PmsChecklistTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class PmsChecklistItemFactory extends Factory
{
    protected $model = PmsChecklistItem::class;

    public function definition(): array
    {
        return ['template_id' => PmsChecklistTemplate::factory(), 'label' => fake()->sentence(3), 'input_type' => 'pass_fail', 'options' => null, 'is_required' => true, 'sort_order' => 0];
    }
}
