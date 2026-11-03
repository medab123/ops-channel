<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Services;

use Medox\OpsChannel\Contracts\ArtisanRunnerInterface;
use Medox\OpsChannel\Contracts\CommandServiceInterface;
use Medox\OpsChannel\DTO\CommandResultData;
use Psr\Log\LoggerInterface;

final readonly class CommandService implements CommandServiceInterface
{
    public function __construct(
        private ArtisanRunnerInterface $runner,
        private LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function run(string $command): CommandResultData
    {
        // Audit logging of the executed command is added in a follow-up step.
        return $this->runner->run($command);
    }
}
