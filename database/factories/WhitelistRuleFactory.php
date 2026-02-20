<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhitelistRule>
 */
class WhitelistRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hostname' => $this->faker->domainName(),
            'path_prefix' => '/',
            'scheme' => 'https',
            'allow_any_port' => false,
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
