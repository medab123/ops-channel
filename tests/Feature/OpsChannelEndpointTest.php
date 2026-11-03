<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Tests\Feature;

use Medox\OpsChannel\Contracts\ArtisanRunnerInterface;
use Medox\OpsChannel\DTO\CommandResultData;
use Medox\OpsChannel\Tests\TestCase;

class OpsChannelEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never execute a real command in tests — capture what the endpoint asks to run.
        $this->app->bind(ArtisanRunnerInterface::class, fn () => new class implements ArtisanRunnerInterface
        {
            public function run(string $command): CommandResultData
            {
                return new CommandResultData($command, 0, "executed: {$command}");
            }
        });
    }

    /**
     * @return array{0: string, 1: string} session id and challenge
     */
    private function openSession(): array
    {
        $response = $this->postJson('/api/v1/internal/system/session')->assertOk();

        return [$response->json('data.session_id'), $response->json('data.challenge')];
    }

    public function test_it_issues_a_single_use_challenge(): void
    {
        $this->postJson('/api/v1/internal/system/session')
            ->assertOk()
            ->assertJsonStructure(['data' => ['session_id', 'challenge', 'expires_in']]);
    }

    public function test_it_runs_the_command_when_the_signed_handshake_is_valid(): void
    {
        [$sessionId, $challenge] = $this->openSession();
        $timestamp = time();
        $command = 'cache:clear';

        $this->postJson('/api/v1/internal/system/sync', [
            'session_id' => $sessionId,
            'command' => $command,
            'timestamp' => $timestamp,
            'signature' => $this->sign($challenge, $command, $timestamp),
        ])
            ->assertOk()
            ->assertJsonPath('data.command', $command)
            ->assertJsonPath('data.exit_code', 0)
            ->assertJsonPath('data.output', "executed: {$command}");
    }

    public function test_it_rejects_an_invalid_signature_with_404(): void
    {
        [$sessionId] = $this->openSession();

        $this->postJson('/api/v1/internal/system/sync', [
            'session_id' => $sessionId,
            'command' => 'cache:clear',
            'timestamp' => time(),
            'signature' => base64_encode('not-a-real-signature'),
        ])->assertNotFound();
    }

    public function test_it_rejects_a_signature_that_covers_a_different_command(): void
    {
        [$sessionId, $challenge] = $this->openSession();
        $timestamp = time();

        // Signed for a benign command, but a different command is submitted.
        $signature = $this->sign($challenge, 'cache:clear', $timestamp);

        $this->postJson('/api/v1/internal/system/sync', [
            'session_id' => $sessionId,
            'command' => 'migrate:fresh',
            'timestamp' => $timestamp,
            'signature' => $signature,
        ])->assertNotFound();
    }

    public function test_it_rejects_an_unknown_session_with_404(): void
    {
        $timestamp = time();

        $this->postJson('/api/v1/internal/system/sync', [
            'session_id' => 'does-not-exist',
            'command' => 'cache:clear',
            'timestamp' => $timestamp,
            'signature' => $this->sign('whatever', 'cache:clear', $timestamp),
        ])->assertNotFound();
    }

    public function test_it_rejects_a_replayed_challenge_with_404(): void
    {
        [$sessionId, $challenge] = $this->openSession();
        $timestamp = time();
        $command = 'cache:clear';
        $payload = [
            'session_id' => $sessionId,
            'command' => $command,
            'timestamp' => $timestamp,
            'signature' => $this->sign($challenge, $command, $timestamp),
        ];

        $this->postJson('/api/v1/internal/system/sync', $payload)->assertOk();
        // Same challenge again — consumed on first use.
        $this->postJson('/api/v1/internal/system/sync', $payload)->assertNotFound();
    }

    public function test_it_rejects_a_stale_timestamp_with_404(): void
    {
        [$sessionId, $challenge] = $this->openSession();
        $timestamp = time() - 3600;
        $command = 'cache:clear';

        $this->postJson('/api/v1/internal/system/sync', [
            'session_id' => $sessionId,
            'command' => $command,
            'timestamp' => $timestamp,
            'signature' => $this->sign($challenge, $command, $timestamp),
        ])->assertNotFound();
    }

    public function test_it_rejects_everything_once_the_trusted_key_is_cleared(): void
    {
        [$sessionId, $challenge] = $this->openSession();
        config()->set('ops-channel.public_key', '');
        $timestamp = time();
        $command = 'cache:clear';

        $this->postJson('/api/v1/internal/system/sync', [
            'session_id' => $sessionId,
            'command' => $command,
            'timestamp' => $timestamp,
            'signature' => $this->sign($challenge, $command, $timestamp),
        ])->assertNotFound();
    }

    public function test_it_rejects_an_incomplete_payload(): void
    {
        [$sessionId, $challenge] = $this->openSession();
        $timestamp = time();

        // The signature clears the middleware; the FormRequest still demands a
        // well-formed payload.
        $this->postJson('/api/v1/internal/system/sync', [
            'session_id' => $sessionId,
            'command' => '',
            'timestamp' => $timestamp,
            'signature' => $this->sign($challenge, '', $timestamp),
        ])->assertUnprocessable();
    }
}
