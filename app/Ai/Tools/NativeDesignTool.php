<?php

namespace App\Ai\Tools;

use App\Ai\DesignSession;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * One of the email builder package's native agent tools, exposed under its
 * native name so the package's system prompt describes it exactly. Inputs are
 * passed through as-is; the package validates them and explains rejections.
 */
class NativeDesignTool implements Tool
{
    private const string BLOCK = 'A block: {id?: readable id, type, props?, style?, children?: blocks (containers only)}. See the block catalog in your instructions.';

    /**
     * @param  Closure(JsonSchema): array<string, Type>  $schema
     */
    public function __construct(
        private readonly DesignSession $session,
        private readonly string $name,
        private readonly string $description,
        private readonly Closure $schema,
    ) {}

    /** @return list<self> */
    public static function all(DesignSession $session): array
    {
        $tool = fn (string $name, string $description, Closure $schema): self => new self($session, $name, $description, $schema);
        $id = fn (JsonSchema $schema) => $schema->string()->description('Block id from the outline.')->required();
        $parent = fn (JsonSchema $schema) => $schema->string()->description('Container, column or "root" (document body). Default "root".');
        $index = fn (JsonSchema $schema) => $schema->integer()->min(0)->description('Position among siblings; omit to append.');
        $block = fn (JsonSchema $schema) => $schema->object()->description(self::BLOCK);
        $patch = fn (JsonSchema $schema, string $keys) => $schema->object()->description("Keys to set ({$keys}); null removes a key so the default applies.");

        return [
            $tool('get_document', 'Returns the outline of the email: settings, theme colors and every block as "id type: summary".', fn () => []),
            $tool('get_block', 'Returns one block with all its props, style and nested children as JSON.', fn (JsonSchema $schema) => [
                'id' => $id($schema),
            ]),
            $tool('insert_blocks', 'Inserts one or more blocks (with nested children) into a parent. Returns the new ids.', fn (JsonSchema $schema) => [
                'parentId' => $parent($schema),
                'index' => $index($schema),
                'blocks' => $schema->array()->items($block($schema))->min(1)->required(),
            ]),
            $tool('update_block', 'Changes props and/or style of a block. Only the given keys change.', fn (JsonSchema $schema) => [
                'id' => $id($schema),
                'props' => $patch($schema, 'block props'),
                'style' => $patch($schema, 'style keys'),
            ]),
            $tool('move_block', 'Moves a block (with its children) to another parent or position.', fn (JsonSchema $schema) => [
                'id' => $id($schema),
                'parentId' => $parent($schema)->required(),
                'index' => $index($schema),
            ]),
            $tool('remove_block', 'Deletes a block and everything inside it.', fn (JsonSchema $schema) => [
                'id' => $id($schema),
            ]),
            $tool('duplicate_block', 'Copies a block (with its children) right after itself.', fn (JsonSchema $schema) => [
                'id' => $id($schema),
            ]),
            $tool('replace_block', 'Replaces a block, keeping its position, with new block content.', fn (JsonSchema $schema) => [
                'id' => $id($schema),
                'block' => $block($schema)->required(),
            ]),
            $tool('insert_section', 'Inserts a ready-made section (see the section list in your instructions).', fn (JsonSchema $schema) => [
                'name' => $schema->string()->enum([
                    'header', 'navHeader', 'hero', 'article', 'features', 'imageText', 'cards',
                    'gallery', 'testimonial', 'promo', 'signoff', 'cta', 'footer',
                ])->required(),
                'params' => $schema->object()->description('Section parameters as strings; see the section list in your instructions.'),
                'parentId' => $parent($schema),
                'index' => $index($schema),
            ]),
            $tool('update_settings', 'Changes email settings such as preheader, width or colors.', fn (JsonSchema $schema) => [
                'settings' => $patch($schema, 'width, preheader, title, lang, padding, backdropColor, canvasColor, textColor, linkColor, borderColor, borderRadius, fontSize, lineHeight')->required(),
            ]),
            $tool('update_theme', 'Changes theme colors and fonts; every block using $tokens follows.', fn (JsonSchema $schema) => [
                'colors' => $patch($schema, 'primary, secondary, text, muted, background, surface, border, link; hex only'),
                'fonts' => $patch($schema, 'body, heading'),
            ]),
            $tool('replace_document', 'Replaces the whole email with new blocks. Use only to start from scratch.', fn (JsonSchema $schema) => [
                'settings' => $patch($schema, 'settings'),
                'theme' => $schema->object()->description('{colors?, fonts?} as in update_theme.'),
                'blocks' => $schema->array()->items($block($schema))->required(),
            ]),
            $tool('apply_ops', 'Applies several operations atomically: all succeed or none do. When any fail, every failing op is reported (op numbers are 0-based) so you can fix them in one retry.', fn (JsonSchema $schema) => [
                'ops' => $schema->array()->items($schema->object())->min(1)->description(
                    'Each op is one of: {op:"insert", parentId?, index?, blocks}, {op:"update", id, props?, style?}, {op:"move", id, parentId, index?}, {op:"remove", id}, {op:"duplicate", id}, {op:"replace", id, block}, {op:"updateSettings", settings}, {op:"updateTheme", colors?, fonts?}.',
                )->required(),
            ]),
            $tool('check_email', 'Validates the email and returns warnings (accessibility, deliverability, placeholders) plus the plain-text version.', fn () => []),
        ];
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): Stringable|string
    {
        return $this->description;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->session->run($this->name, $request->toArray());
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ($this->schema)($schema);
    }
}
