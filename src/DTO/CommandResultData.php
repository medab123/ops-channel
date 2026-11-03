<?php

declare(strict_types=1);

namespace Medox\OpsChannel\DTO;

use JsonSerializable;

/**
 * What running one command produced.
 *
 * Hand-serialized rather than built on a DTO library: two response shapes do not
 * justify making every application that installs this package take one on.
 */
final readonly class CommandResultData implements JsonSerializable
{
    public function __construct(
        public string $command,
        public int $exitCode,
        public string $output,
    ) {}

    /**
     * @return array{command: string, exit_code: int, output: string}
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'exit_code' => $this->exitCode,
            'output' => $this->output,
        ];
    }

    /**
     * @return array{command: string, exit_code: int, output: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
