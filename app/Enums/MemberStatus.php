<?php

namespace App\Enums;

/** Defines the supported member status values used by WorkIntel. */ enum MemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Invited = 'invited';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
