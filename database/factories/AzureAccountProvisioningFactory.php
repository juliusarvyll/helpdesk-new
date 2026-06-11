<?php

namespace Database\Factories;

use App\Models\AzureAccountProvisioning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AzureAccountProvisioning>
 */
class AzureAccountProvisioningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_type' => fake()->randomElement(['student', 'faculty']),
            'display_name' => fake()->name(),
            'given_name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'user_principal_name' => fake()->unique()->userName().'@example.edu',
            'mail_nickname' => fake()->unique()->userName(),
            'usage_location' => 'PH',
            'license_sku_id' => fake()->uuid(),
            'license_sku_part_number' => 'STANDARDWOFFPACK_STUDENT',
            'status' => 'pending',
        ];
    }
}
