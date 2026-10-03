<?php

namespace App\Enums;

enum ContactImportMergeStrategy: string
{
    case Skip = 'skip';
    case FillBlanks = 'fill_blanks';
}
