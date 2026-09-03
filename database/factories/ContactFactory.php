<?php

namespace Database\Factories;

use App\Enums\ContactCompanyAssignmentMode;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'company_assignment_mode' => ContactCompanyAssignmentMode::Automatic,
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
        ];
    }
}
