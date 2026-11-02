<?php

namespace App\Enums;

/**
 * The task priorities, highest first. Each value is the stored lower-case word.
 */
enum TaskPriority: string
{
    case High = 'high';
    case Med = 'med';
    case Low = 'low';
}
