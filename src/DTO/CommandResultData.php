<?php

declare(strict_types=1);

namespace Medox\OpsChannel\DTO;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class CommandResultData extends Data
{
    public function __construct(
        public readonly string $command,
        public readonly int $exitCode,
        public readonly string $output,
    ) {}
}
