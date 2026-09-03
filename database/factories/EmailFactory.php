<?php

namespace Database\Factories;

use App\Enums\EmailEditor;
use App\Models\Email;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Email>
 */
class EmailFactory extends Factory
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
            'name' => fake()->unique()->words(3, true),
            'subject' => fake()->sentence(),
            'preheader' => fake()->sentence(),
            'editor' => EmailEditor::Html,
            'html' => '<p>'.fake()->paragraph().'</p>',
            'design' => null,
        ];
    }

    /**
     * An email composed with the block editor.
     */
    public function builder(): static
    {
        return $this->state(fn () => [
            'editor' => EmailEditor::Builder,
            'html' => '<p>rendered</p>',
            'design' => [
                'root' => [
                    'type' => 'EmailLayout',
                    'data' => ['childrenIds' => ['block-1']],
                ],
                'block-1' => [
                    'type' => 'Text',
                    'data' => ['props' => ['text' => fake()->sentence()]],
                ],
            ],
        ]);
    }
}
