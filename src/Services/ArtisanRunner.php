<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Services;

use Illuminate\Contracts\Console\Kernel as ArtisanKernel;
use Medox\OpsChannel\Contracts\ArtisanRunnerInterface;
use Medox\OpsChannel\DTO\CommandResultData;

final readonly class ArtisanRunner implements ArtisanRunnerInterface
{
    public function __construct(
        private ArtisanKernel $artisan,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function run(string $command): CommandResultData
    {
        $exitCode = $this->artisan->call($command);
        $output = $this->artisan->output();

        return new CommandResultData(
            command: $command,
            exitCode: $exitCode,
            output: $output,
        );
    }
}
