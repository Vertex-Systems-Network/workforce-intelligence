<?php

namespace App\Enums;

/** Defines the supported time source values used by WorkIntel. */ enum TimeSource: string
{
    case Web = 'web';
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Manual = 'manual';
    case Api = 'api';
}
