<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Tests;

use Medox\OpsChannel\OpsChannelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** Base64-encoded public half of the key pair this test application trusts. */
    protected string $publicKey;

    /** Raw private half, used by tests to sign as the trusted caller. */
    protected string $secretKey;

    protected function setUp(): void
    {
        // Generated before the application boots: the provider only registers
        // routes when a key is configured, and defineEnvironment() runs inside
        // parent::setUp().
        $keypair = sodium_crypto_sign_keypair();
        $this->publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            OpsChannelServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ops-channel.public_key', $this->publicKey);
    }

    /**
     * Sign the "challenge|command|timestamp" message as the trusted caller.
     */
    protected function sign(string $challenge, string $command, int $timestamp, ?string $secretKey = null): string
    {
        return base64_encode(
            sodium_crypto_sign_detached("{$challenge}|{$command}|{$timestamp}", $secretKey ?? $this->secretKey)
        );
    }
}
