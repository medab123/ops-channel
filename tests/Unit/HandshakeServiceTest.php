<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Medox\OpsChannel\DTO\ChallengeData;
use Medox\OpsChannel\Services\HandshakeService;
use Medox\OpsChannel\Tests\TestCase;
use Psr\Log\NullLogger;

class HandshakeServiceTest extends TestCase
{
    private HandshakeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->makeService($this->publicKey);
    }

    private function makeService(string $publicKey, int $ttl = 30, int $tolerance = 30): HandshakeService
    {
        return new HandshakeService(
            cache: new Repository(new ArrayStore),
            logger: new NullLogger,
            publicKey: $publicKey,
            challengeTtl: $ttl,
            timestampTolerance: $tolerance,
            cachePrefix: 'ops-channel:handshake:',
        );
    }

    private function signChallenge(ChallengeData $challenge, string $command, int $timestamp, ?string $secretKey = null): string
    {
        return $this->sign($challenge->challenge, $command, $timestamp, $secretKey);
    }

    public function test_it_issues_a_challenge_bound_to_a_session_id(): void
    {
        $challenge = $this->service->issueChallenge();

        $this->assertNotSame('', $challenge->sessionId);
        $this->assertNotSame('', $challenge->challenge);
        $this->assertSame(30, $challenge->expiresIn);
    }

    public function test_it_verifies_a_correctly_signed_response(): void
    {
        $challenge = $this->service->issueChallenge();
        $timestamp = time();
        $command = 'cache:clear';

        $this->assertTrue($this->service->verify(
            $challenge->sessionId,
            $command,
            $timestamp,
            $this->signChallenge($challenge, $command, $timestamp),
        ));
    }

    public function test_it_consumes_the_challenge_so_it_cannot_be_replayed(): void
    {
        $challenge = $this->service->issueChallenge();
        $timestamp = time();
        $command = 'cache:clear';
        $signature = $this->signChallenge($challenge, $command, $timestamp);

        $this->assertTrue($this->service->verify($challenge->sessionId, $command, $timestamp, $signature));
        $this->assertFalse($this->service->verify($challenge->sessionId, $command, $timestamp, $signature));
    }

    public function test_it_rejects_an_unknown_session(): void
    {
        $this->assertFalse($this->service->verify('missing', 'cache:clear', time(), 'sig'));
    }

    public function test_it_rejects_a_signature_over_a_different_command(): void
    {
        $challenge = $this->service->issueChallenge();
        $timestamp = time();
        $signature = $this->signChallenge($challenge, 'cache:clear', $timestamp);

        $this->assertFalse($this->service->verify($challenge->sessionId, 'migrate:fresh', $timestamp, $signature));
    }

    public function test_it_rejects_a_stale_timestamp(): void
    {
        $challenge = $this->service->issueChallenge();
        $timestamp = time() - 3600;
        $command = 'cache:clear';

        $this->assertFalse($this->service->verify(
            $challenge->sessionId,
            $command,
            $timestamp,
            $this->signChallenge($challenge, $command, $timestamp),
        ));
    }

    public function test_it_rejects_a_signature_made_with_the_wrong_key(): void
    {
        $otherKeypair = sodium_crypto_sign_keypair();
        $otherSecret = sodium_crypto_sign_secretkey($otherKeypair);

        $challenge = $this->service->issueChallenge();
        $timestamp = time();
        $command = 'cache:clear';

        $this->assertFalse($this->service->verify(
            $challenge->sessionId,
            $command,
            $timestamp,
            $this->signChallenge($challenge, $command, $timestamp, $otherSecret),
        ));
    }

    public function test_it_rejects_everything_when_no_key_is_configured(): void
    {
        $service = $this->makeService('');

        $challenge = $service->issueChallenge();
        $timestamp = time();
        $command = 'cache:clear';

        $this->assertFalse($service->verify(
            $challenge->sessionId,
            $command,
            $timestamp,
            $this->signChallenge($challenge, $command, $timestamp),
        ));
    }

    public function test_it_rejects_a_trust_anchor_that_is_not_an_ed25519_key(): void
    {
        $service = $this->makeService(base64_encode('too-short'));

        $challenge = $service->issueChallenge();
        $timestamp = time();
        $command = 'cache:clear';

        $this->assertFalse($service->verify(
            $challenge->sessionId,
            $command,
            $timestamp,
            $this->signChallenge($challenge, $command, $timestamp),
        ));
    }
}
