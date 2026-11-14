<?php

namespace Database\Factories;

use App\Models\ReminderCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReminderCategoryFactory extends Factory
{
    protected $model = ReminderCategory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'color' => Str::upper(fake()->unique()->hexColor()),
        ];
    }
}
