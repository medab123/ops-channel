<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Medox\OpsChannel\Contracts\HandshakeServiceInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class VerifySignature
{
    public function __construct(
        private HandshakeServiceInterface $handshake,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $verified = $this->handshake->verify(
            sessionId: (string) $request->input('session_id', ''),
            command: (string) $request->input('command', ''),
            timestamp: (int) $request->input('timestamp', 0),
            signature: (string) $request->input('signature', ''),
        );

        if (! $verified) {
            // Flat 404 so an unauthorized caller learns nothing about the route.
            return new JsonResponse(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
