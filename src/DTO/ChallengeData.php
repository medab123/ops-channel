<?php

declare(strict_types=1);

namespace Medox\OpsChannel\DTO;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class ChallengeData extends Data
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $challenge,
        public readonly int $expiresIn,
    ) {}
}
