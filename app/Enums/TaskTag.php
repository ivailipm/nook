<?php

namespace App\Enums;

/**
 * The task tags, in the prototype's order. Each value is the stored lower-case word.
 */
enum TaskTag: string
{
    case School = 'school';
    case Home = 'home';
    case Internship = 'internship';
}
