<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Tests\Feature;

use Medox\OpsChannel\Tests\TestCase;

/**
 * An application that overrides one nested key must not lose its siblings.
 *
 * Laravel's mergeConfigFrom() merges top-level keys only, so naming `routes` to
 * pin a prefix would otherwise drop `enabled` with the rest of the array — and
 * a dropped `enabled` reads as true wherever it is defaulted, which turns
 * OPS_CHANNEL_ROUTES_ENABLED into a switch that does nothing.
 */
class NestedConfigDefaultsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Exactly what a consuming application writes to pin its own path.
        $app['config']->set('ops-channel.routes', ['prefix' => 'ops/custom']);
        $app['config']->set('ops-channel.handshake', ['challenge_ttl' => 90]);
    }

    public function test_overriding_one_route_key_keeps_the_others(): void
    {
        $this->assertSame('ops/custom', config('ops-channel.routes.prefix'));
        $this->assertTrue(config('ops-channel.routes.enabled'));
        $this->assertSame('30,1', config('ops-channel.routes.session_throttle'));
        $this->assertSame([], config('ops-channel.routes.middleware'));
    }

    public function test_overriding_one_handshake_key_keeps_the_others(): void
    {
        $this->assertSame(90, config('ops-channel.handshake.challenge_ttl'));
        $this->assertSame(30, config('ops-channel.handshake.timestamp_tolerance'));
        $this->assertSame('ops-channel:handshake:', config('ops-channel.handshake.cache_prefix'));
    }

    public function test_the_partially_overridden_prefix_still_routes(): void
    {
        $this->postJson('/ops/custom/session')->assertOk();
    }

    public function test_the_overridden_challenge_ttl_reaches_the_service(): void
    {
        $this->postJson('/ops/custom/session')->assertOk()->assertJsonPath('data.expires_in', 90);
    }
}
