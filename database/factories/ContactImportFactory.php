<?php

namespace Database\Factories;

use App\Models\ContactImport;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactImport>
 */
class ContactImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'original_name' => 'contacts.csv',
            'disk' => 'local',
            'path' => 'contact-imports/'.fake()->uuid().'.csv',
            'status' => 'pending',
        ];
    }
}
