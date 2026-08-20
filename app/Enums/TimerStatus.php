<?php

namespace App\Enums;

/** Defines the supported timer status values used by WorkIntel. */ enum TimerStatus: string
{
    case Running = 'running';
    case Paused = 'paused';
    case Stopped = 'stopped';
}
