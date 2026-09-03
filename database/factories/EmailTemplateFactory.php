<?php

namespace Database\Factories;

use App\Enums\EmailEditor;
use App\Models\EmailTemplate;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'subject' => fake()->sentence(4),
            'preheader' => fake()->sentence(),
            'editor' => EmailEditor::Html,
            'html' => '<p>'.fake()->sentence().'</p>',
            'design' => null,
            'position' => 0,
        ];
    }

    /**
     * A template built with the block editor.
     */
    public function builder(): static
    {
        return $this->state(fn () => [
            'editor' => EmailEditor::Builder,
            'html' => null,
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

    /**
     * A starter template, which belongs to no team.
     */
    public function starter(): static
    {
        return $this->state(fn () => ['team_id' => null]);
    }
}
