<?php

namespace App\Enums;

enum SubscribeFormArtworkType: string
{
    case Upload = 'upload';
    case ImagePreset = 'image-preset';
    case BackgroundPreset = 'background-preset';
}
