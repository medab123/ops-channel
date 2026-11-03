<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Contracts;

use Medox\OpsChannel\DTO\CommandResultData;

interface ArtisanRunnerInterface
{
    /**
     * Execute an Artisan command string and capture its result.
     *
     * @param  string  $command  Full command line, e.g. "cache:clear" or "migrate --force"
     */
    public function run(string $command): CommandResultData;
}
