<?php

declare(strict_types=1);

namespace App\Enum;

enum ContributorRole: string
{
    case AUTHOR = 'AUTHOR';
    case ILLUSTRATOR = 'ILLUSTRATOR';
    case TRANSLATOR = 'TRANSLATOR';
    case DIRECTOR = 'DIRECTOR';
    case OTHER = 'OTHER';
}
