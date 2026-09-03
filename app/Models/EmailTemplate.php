<?php

namespace App\Models;

use App\Enums\EmailEditor;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $team_id
 * @property string $name
 * @property string|null $description
 * @property string|null $subject
 * @property string|null $preheader
 * @property EmailEditor $editor
 * @property string|null $html
 * @property string|null $source
 * @property array<string, mixed>|null $design
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 */
#[Fillable(['team_id', 'name', 'description', 'subject', 'preheader', 'editor', 'html', 'source', 'design', 'position'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    protected $attributes = [
        'editor' => EmailEditor::Html->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (EmailTemplate $template): void {
            $template->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Starter templates ship with the app and belong to no team, so they are
     * visible to everyone and editable by nobody.
     */
    public function isStarter(): bool
    {
        return $this->team_id === null;
    }

    /**
     * Limit the query to the starter templates plus the given team's own ones.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAvailableTo(Builder $query, Team $team): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->whereNull('team_id')
            ->orWhere('team_id', $team->id));
    }

    /**
     * Starters first, in their curated order, then the team's own by name.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOrderedForPicker(Builder $query): void
    {
        $query->orderByRaw('(team_id IS NULL) DESC')
            ->orderBy('position')
            ->orderByRaw('LOWER(name)');
    }

    /**
     * The starter that backs "start from scratch" for each editor.
     */
    public const string BLANK_BUILDER_UUID = '0f7f6a3e-9d64-4b0e-9ad3-4a4c6f2f6c01';

    public const string BLANK_HTML_UUID = '0f7f6a3e-9d64-4b0e-9ad3-4a4c6f2f6c04';

    /**
     * The empty body to compose from when no template was picked.
     *
     * @return array{html: string|null, source: string|null, design: array<string, mixed>|null}
     */
    public static function blankBodyFor(EmailEditor $editor): array
    {
        if ($editor->usesSource()) {
            return [
                'html' => null,
                'source' => '',
                'design' => null,
            ];
        }

        $uuid = $editor === EmailEditor::Builder
            ? self::BLANK_BUILDER_UUID
            : self::BLANK_HTML_UUID;

        $starter = collect(self::starters())->firstWhere('uuid', $uuid);

        return [
            'html' => $starter['html'] ?? null,
            'source' => null,
            'design' => $starter['design'] ?? null,
        ];
    }

    /**
     * The templates seeded for every installation.
     *
     * The uuids are fixed so the seeding migration stays idempotent and the
     * same starter keeps its identity across environments.
     *
     * @return list<array{uuid: string, name: string, description: string, subject: string|null, preheader: string|null, editor: EmailEditor, html: string|null, design: array<string, mixed>|null, position: int}>
     */
    public static function starters(): array
    {
        return [
            [
                'uuid' => '0f7f6a3e-9d64-4b0e-9ad3-4a4c6f2f6c01',
                'name' => 'Blank canvas',
                'description' => 'An empty block layout to build up from scratch.',
                'subject' => null,
                'preheader' => null,
                'editor' => EmailEditor::Builder,
                'html' => null,
                'design' => [
                    'root' => [
                        'type' => 'EmailLayout',
                        'data' => [
                            'backdropColor' => '#f5f5f5',
                            'canvasColor' => '#ffffff',
                            'textColor' => '#262626',
                            'fontFamily' => 'MODERN_SANS',
                            'childrenIds' => ['block-intro'],
                        ],
                    ],
                    'block-intro' => [
                        'type' => 'Text',
                        'data' => [
                            'style' => ['padding' => ['top' => 24, 'bottom' => 24, 'left' => 24, 'right' => 24]],
                            'props' => ['text' => 'Start writing here.'],
                        ],
                    ],
                ],
                'position' => 10,
            ],
            [
                'uuid' => '0f7f6a3e-9d64-4b0e-9ad3-4a4c6f2f6c02',
                'name' => 'Newsletter',
                'description' => 'Headline, story, and a call to action for a regular update.',
                'subject' => 'This month at your company',
                'preheader' => 'Here is what the team has been up to since the last issue.',
                'editor' => EmailEditor::Builder,
                'html' => null,
                'design' => [
                    'root' => [
                        'type' => 'EmailLayout',
                        'data' => [
                            'backdropColor' => '#f5f5f5',
                            'canvasColor' => '#ffffff',
                            'textColor' => '#262626',
                            'fontFamily' => 'MODERN_SANS',
                            'childrenIds' => [
                                'block-heading',
                                'block-intro',
                                'block-divider',
                                'block-story',
                                'block-cta',
                                'block-footer',
                            ],
                        ],
                    ],
                    'block-heading' => [
                        'type' => 'Heading',
                        'data' => [
                            'style' => ['padding' => ['top' => 32, 'bottom' => 8, 'left' => 24, 'right' => 24]],
                            'props' => ['text' => 'This month at your company', 'level' => 'h2'],
                        ],
                    ],
                    'block-intro' => [
                        'type' => 'Text',
                        'data' => [
                            'style' => ['padding' => ['top' => 0, 'bottom' => 16, 'left' => 24, 'right' => 24]],
                            'props' => ['text' => 'Hi there — here is what the team has been up to since the last issue.'],
                        ],
                    ],
                    'block-divider' => [
                        'type' => 'Divider',
                        'data' => [
                            'style' => ['padding' => ['top' => 0, 'bottom' => 16, 'left' => 24, 'right' => 24]],
                            'props' => ['lineColor' => '#e5e5e5', 'lineHeight' => 1],
                        ],
                    ],
                    'block-story' => [
                        'type' => 'Text',
                        'data' => [
                            'style' => ['padding' => ['top' => 0, 'bottom' => 16, 'left' => 24, 'right' => 24]],
                            'props' => ['text' => 'Replace this paragraph with the story you want to lead with.'],
                        ],
                    ],
                    'block-cta' => [
                        'type' => 'Button',
                        'data' => [
                            'style' => ['padding' => ['top' => 0, 'bottom' => 24, 'left' => 24, 'right' => 24], 'textAlign' => 'left'],
                            'props' => [
                                'text' => 'Read the full update',
                                'url' => 'https://example.com',
                                'buttonBackgroundColor' => '#262626',
                                'buttonTextColor' => '#ffffff',
                                'buttonStyle' => 'rounded',
                                'size' => 'medium',
                            ],
                        ],
                    ],
                    'block-footer' => [
                        'type' => 'Text',
                        'data' => [
                            'style' => [
                                'padding' => ['top' => 0, 'bottom' => 32, 'left' => 24, 'right' => 24],
                                'color' => '#737373',
                                'fontSize' => 13,
                            ],
                            'props' => ['text' => 'You are receiving this because you subscribed to our list.'],
                        ],
                    ],
                ],
                'position' => 20,
            ],
            [
                'uuid' => '0f7f6a3e-9d64-4b0e-9ad3-4a4c6f2f6c03',
                'name' => 'Announcement',
                'description' => 'A single message with one clear call to action.',
                'subject' => 'Something new is here',
                'preheader' => 'Say what changed and why it matters.',
                'editor' => EmailEditor::Builder,
                'html' => null,
                'design' => [
                    'root' => [
                        'type' => 'EmailLayout',
                        'data' => [
                            'backdropColor' => '#f5f5f5',
                            'canvasColor' => '#ffffff',
                            'textColor' => '#262626',
                            'fontFamily' => 'MODERN_SANS',
                            'childrenIds' => ['block-heading', 'block-body', 'block-cta'],
                        ],
                    ],
                    'block-heading' => [
                        'type' => 'Heading',
                        'data' => [
                            'style' => ['padding' => ['top' => 40, 'bottom' => 12, 'left' => 24, 'right' => 24], 'textAlign' => 'center'],
                            'props' => ['text' => 'Something new is here', 'level' => 'h1'],
                        ],
                    ],
                    'block-body' => [
                        'type' => 'Text',
                        'data' => [
                            'style' => ['padding' => ['top' => 0, 'bottom' => 24, 'left' => 24, 'right' => 24], 'textAlign' => 'center'],
                            'props' => ['text' => 'Say what changed and why it matters, in a sentence or two.'],
                        ],
                    ],
                    'block-cta' => [
                        'type' => 'Button',
                        'data' => [
                            'style' => ['padding' => ['top' => 0, 'bottom' => 40, 'left' => 24, 'right' => 24], 'textAlign' => 'center'],
                            'props' => [
                                'text' => 'Take a look',
                                'url' => 'https://example.com',
                                'buttonBackgroundColor' => '#2563eb',
                                'buttonTextColor' => '#ffffff',
                                'buttonStyle' => 'pill',
                                'size' => 'large',
                            ],
                        ],
                    ],
                ],
                'position' => 30,
            ],
            [
                'uuid' => '0f7f6a3e-9d64-4b0e-9ad3-4a4c6f2f6c04',
                'name' => 'Blank HTML',
                'description' => 'A bare responsive HTML shell for hand-written markup.',
                'subject' => null,
                'preheader' => null,
                'editor' => EmailEditor::Html,
                'html' => <<<'HTML'
                <!DOCTYPE html>
                <html>
                    <body style="margin:0;padding:32px 0;background-color:#f5f5f5;font-family:Helvetica,Arial,sans-serif;color:#262626;">
                        <table align="center" width="100%" role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;max-width:600px;background-color:#ffffff;">
                            <tr>
                                <td style="padding:24px;">
                                    <p style="margin:0;font-size:16px;line-height:1.5;">Start writing here.</p>
                                </td>
                            </tr>
                        </table>
                    </body>
                </html>
                HTML,
                'design' => null,
                'position' => 40,
            ],
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'editor' => EmailEditor::class,
            'design' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
