<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use Medox\OpsChannel\Contracts\HandshakeServiceInterface;
use Medox\OpsChannel\DTO\ChallengeData;
use Psr\Log\LoggerInterface;
use SodiumException;

final readonly class HandshakeService implements HandshakeServiceInterface
{
    private const MESSAGE_DELIMITER = '|';

    private const CHALLENGE_BYTES = 32;

    public function __construct(
        private CacheRepository $cache,
        private LoggerInterface $logger,
        private string $publicKey,
        private int $challengeTtl,
        private int $timestampTolerance,
        private string $cachePrefix,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function issueChallenge(): ChallengeData
    {
        $sessionId = (string) Str::uuid();
        $challenge = base64_encode(random_bytes(self::CHALLENGE_BYTES));

        $this->cache->put($this->cacheKey($sessionId), $challenge, $this->challengeTtl);

        return new ChallengeData(
            sessionId: $sessionId,
            challenge: $challenge,
            expiresIn: $this->challengeTtl,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function verify(string $sessionId, string $command, int $timestamp, string $signature): bool
    {
        // Redeem the challenge atomically: it is single-use regardless of outcome.
        $challenge = $this->cache->pull($this->cacheKey($sessionId));

        if (! is_string($challenge) || $challenge === '') {
            return false;
        }

        if (abs(time() - $timestamp) > $this->timestampTolerance) {
            return false;
        }

        $message = $challenge.self::MESSAGE_DELIMITER.$command.self::MESSAGE_DELIMITER.$timestamp;

        return $this->isSignatureValid($message, $signature);
    }

    /**
     * Validate an Ed25519 signature against the trusted public key.
     */
    private function isSignatureValid(string $message, string $signature): bool
    {
        $publicKey = base64_decode($this->publicKey, true);
        $rawSignature = base64_decode($signature, true);

        if ($publicKey === false || $rawSignature === false) {
            return false;
        }

        // An unconfigured or malformed trust anchor verifies nothing. Checked
        // here rather than left to libsodium so the common case — no key set —
        // is a plain refusal instead of an exception on every call.
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($rawSignature, $message, $publicKey);
        } catch (SodiumException $e) {
            // Malformed signature length — treat as a failed verification.
            $this->logger->warning('Ops channel signature verification could not run', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function cacheKey(string $sessionId): string
    {
        return $this->cachePrefix.$sessionId;
    }
}
