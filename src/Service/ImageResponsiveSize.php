<?php

declare(strict_types=1);

namespace App\Service;

enum ImageResponsiveSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Original = 'original';

    public function resolution(): ?array
    {
        return match ($this) {
            self::Small => [150, 150],
            self::Medium => [500, 500],
            self::Large => [1500, 1500],
            default => null,
        };
    }
}