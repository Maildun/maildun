<?php

namespace App\Enums;

enum SubscribeFormFieldMode: string
{
    case Hidden = 'hidden';
    case Optional = 'optional';
    case Required = 'required';
}
