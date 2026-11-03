<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Tests\Feature;

use Medox\OpsChannel\Tests\TestCase;

/**
 * Installing the package must not, on its own, open a command endpoint.
 */
class RouteRegistrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        // Deliberately no public key: the shipped default.
        $app['config']->set('ops-channel.public_key', null);
    }

    public function test_no_routes_are_registered_without_a_trusted_key(): void
    {
        $this->postJson('/api/v1/internal/system/session')->assertNotFound();
        $this->postJson('/api/v1/internal/system/sync')->assertNotFound();
    }
}
