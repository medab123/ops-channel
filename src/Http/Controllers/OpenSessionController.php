<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Medox\OpsChannel\Contracts\HandshakeServiceInterface;

final readonly class OpenSessionController
{
    public function __construct(
        private HandshakeServiceInterface $handshake,
    ) {}

    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'OK',
            'data' => $this->handshake->issueChallenge(),
        ]);
    }
}
