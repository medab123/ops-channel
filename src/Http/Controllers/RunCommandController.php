<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Medox\OpsChannel\Contracts\CommandServiceInterface;
use Medox\OpsChannel\Http\Requests\RunCommandRequest;

final readonly class RunCommandController
{
    public function __construct(
        private CommandServiceInterface $commandService,
    ) {}

    public function __invoke(RunCommandRequest $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'OK',
            'data' => $this->commandService->run((string) $request->validated('command')),
        ]);
    }
}
