<?php

namespace Database\Factories;

use App\Enums\EmailEditor;
use App\Enums\TransactionalEmailStatus;
use App\Models\Team;
use App\Models\TransactionalEmail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TransactionalEmail>
 */
class TransactionalEmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::of(fake()->unique()->sentence(3))->trim('.')->toString();

        return [
            'team_id' => Team::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'subject' => fake()->sentence(),
            'preheader' => fake()->sentence(),
            'editor' => EmailEditor::Html,
            'status' => TransactionalEmailStatus::Draft,
            'html' => '<p>'.fake()->paragraph().'</p>',
            'design' => null,
            'variables' => [],
        ];
    }

    /**
     * A transactional email composed with the block editor.
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

    /**
     * A published transactional email whose slug is frozen.
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => TransactionalEmailStatus::Published,
            'published_at' => now(),
        ]);
    }
}
