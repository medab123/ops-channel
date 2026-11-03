<?php

declare(strict_types=1);

namespace Medox\OpsChannel\DTO;

use JsonSerializable;

/**
 * A freshly issued challenge, as the caller receives it.
 *
 * Hand-serialized rather than built on a DTO library: two response shapes do not
 * justify making every application that installs this package take one on.
 */
final readonly class ChallengeData implements JsonSerializable
{
    public function __construct(
        public string $sessionId,
        public string $challenge,
        public int $expiresIn,
    ) {}

    /**
     * @return array{session_id: string, challenge: string, expires_in: int}
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'challenge' => $this->challenge,
            'expires_in' => $this->expiresIn,
        ];
    }

    /**
     * @return array{session_id: string, challenge: string, expires_in: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
