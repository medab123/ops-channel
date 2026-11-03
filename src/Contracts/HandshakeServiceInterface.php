<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Contracts;

use Medox\OpsChannel\DTO\ChallengeData;

interface HandshakeServiceInterface
{
    /**
     * Issue a fresh, single-use challenge bound to a new session id.
     *
     * The challenge is random, short-lived, and consumed on first verification
     * attempt. The caller signs it (together with the command and timestamp)
     * using the private key whose public half this application trusts.
     */
    public function issueChallenge(): ChallengeData;

    /**
     * Verify a signed response against a pending challenge and consume it.
     *
     * The challenge is redeemed atomically: whether verification passes or
     * fails, it can never be replayed. Verification succeeds only when the
     * session's challenge exists, the timestamp is fresh, and the signature
     * over "challenge|command|timestamp" validates against the trusted key.
     *
     * @param  string  $sessionId  Session id returned by issueChallenge()
     * @param  string  $command  The command the caller intends to run
     * @param  int  $timestamp  Unix timestamp the caller signed
     * @param  string  $signature  Base64-encoded Ed25519 signature
     */
    public function verify(string $sessionId, string $command, int $timestamp, string $signature): bool;
}
