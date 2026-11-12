<?php

namespace App\Enums;

/**
 * The built-in task tags, in the prototype's order. Each value is the stored lower-case word. A user's own tags
 * (App\Models\CustomTag) follow them, and User::tagNames() gives the full list. Tasks store the tag as a plain
 * string, so they can carry a tag of the user's own.
 */
enum TaskTag: string
{
    case School = 'school';
    case Home = 'home';
    case Internship = 'internship';
}
