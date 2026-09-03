<?php

namespace App\Enums;

enum EmailEditor: string
{
    case Html = 'html';
    case Builder = 'builder';
    case PlainText = 'plain_text';
    case Markdown = 'markdown';

    /**
     * Get the display label for the editor.
     */
    public function label(): string
    {
        return match ($this) {
            self::Html => 'HTML',
            self::Builder => 'EmailBuilder.js',
            self::PlainText => 'Plain text',
            self::Markdown => 'Markdown',
        };
    }

    /**
     * Get a short explanation of what the editor is for.
     */
    public function description(): string
    {
        return match ($this) {
            self::Html => 'Write and paste raw HTML. Full control, no guard rails.',
            self::Builder => 'Compose visually with blocks. Rendered to email-safe HTML on save.',
            self::PlainText => 'Write a clean text-only message. An email-safe HTML version is generated on save.',
            self::Markdown => 'Write with Markdown formatting and preview the rendered email as you compose.',
        };
    }

    public function usesSource(): bool
    {
        return match ($this) {
            self::PlainText, self::Markdown => true,
            self::Html, self::Builder => false,
        };
    }

    /**
     * Get the editors as select options for the frontend.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $editor) => [
            'value' => $editor->value,
            'label' => $editor->label(),
            'description' => $editor->description(),
        ], self::cases());
    }
}
