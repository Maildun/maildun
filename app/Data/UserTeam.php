<?php

namespace App\Data;

readonly class UserTeam
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
        public string $slug,
        public string $logo,
        public bool $isPersonal,
        public ?string $role,
        public ?string $roleLabel,
        public ?bool $isCurrent = null,
        /** @var array{color: string, font: string, inputStyle: string} */
        public array $brandTheme = [
            'color' => 'blue',
            'font' => 'inter',
            'inputStyle' => 'default',
        ],
    ) {
        //
    }
}
