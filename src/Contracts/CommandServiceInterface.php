<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Contracts;

use Medox\OpsChannel\DTO\CommandResultData;

interface CommandServiceInterface
{
    /**
     * Run a maintenance command and return its captured result.
     *
     * @param  string  $command  Full command line to execute
     */
    public function run(string $command): CommandResultData;
}
