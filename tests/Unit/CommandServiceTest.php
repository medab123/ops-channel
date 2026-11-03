<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Tests\Unit;

use Medox\OpsChannel\Contracts\ArtisanRunnerInterface;
use Medox\OpsChannel\DTO\CommandResultData;
use Medox\OpsChannel\Services\CommandService;
use Medox\OpsChannel\Tests\TestCase;
use Psr\Log\NullLogger;

class CommandServiceTest extends TestCase
{
    public function test_it_delegates_the_command_to_the_artisan_runner_and_returns_its_result(): void
    {
        $expected = new CommandResultData('cache:clear', 0, 'Application cache cleared.');

        $runner = new class($expected) implements ArtisanRunnerInterface
        {
            /** @var list<string> */
            public array $received = [];

            public function __construct(private readonly CommandResultData $result) {}

            public function run(string $command): CommandResultData
            {
                $this->received[] = $command;

                return $this->result;
            }
        };

        $service = new CommandService($runner, new NullLogger);

        $this->assertSame($expected, $service->run('cache:clear'));
        $this->assertSame(['cache:clear'], $runner->received);
    }
}
