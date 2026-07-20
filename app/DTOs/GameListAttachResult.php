<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\GameListAttachStatusEnum;
use App\Models\Game;

readonly class GameListAttachResult
{
    public function __construct(
        public GameListAttachStatusEnum $status,
        public ?Game $game = null,
    ) {}

    public function wasAttached(): bool
    {
        return $this->status === GameListAttachStatusEnum::Attached;
    }
}
