<?php

declare(strict_types=1);

namespace App\Enums;

enum GameListAttachStatusEnum: string
{
    case Attached = 'attached';
    case AlreadyOnList = 'already_on_list';
    case GameNotFound = 'game_not_found';
}
