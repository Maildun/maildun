<?php

namespace App\Enums;

enum ContactImportIssue: string
{
    case Typo = 'typo';
    case Disposable = 'disposable';
    case Undeliverable = 'undeliverable';
    case RoleAddress = 'role_address';
    case DuplicateInFile = 'duplicate_in_file';
    case Suppressed = 'suppressed';

    public function label(): string
    {
        return match ($this) {
            self::Typo => 'Possible typo',
            self::Disposable => 'Disposable address',
            self::Undeliverable => 'Domain cannot receive mail',
            self::RoleAddress => 'Role address',
            self::DuplicateInFile => 'Repeated in file',
            self::Suppressed => 'Previously bounced or complained',
        };
    }

    /**
     * Choices offered for issues the person importing decides on, with the default first.
     *
     * @return list<string>
     */
    public function actions(): array
    {
        return match ($this) {
            self::Typo => ['fix', 'import', 'skip'],
            self::Disposable, self::Undeliverable => ['skip', 'import'],
            self::RoleAddress => ['import', 'skip'],
            self::DuplicateInFile, self::Suppressed => [],
        };
    }

    /** @return array<string, string> */
    public static function defaultOptions(): array
    {
        $options = [];

        foreach (self::cases() as $issue) {
            if ($issue->actions() !== []) {
                $options[$issue->value] = $issue->actions()[0];
            }
        }

        return $options;
    }
}
