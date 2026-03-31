<?php

namespace Database\Factories;

use App\Models\Scenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scenario>
 */
class ScenarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'company_name' => $this->faker->company(),
            'industry' => $this->faker->word(),
            'customer_persona' => '営業部長',
            'difficulty' => $this->faker->randomElement(['初級', '中級', '上級']),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'summary' => $this->faker->paragraph(),
            'goal' => $this->faker->sentence(),
        ];
    }
}
