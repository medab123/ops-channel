<?php

declare(strict_types=1);

namespace Medox\OpsChannel\Tests\Feature;

use Medox\OpsChannel\Tests\TestCase;

class RoutePrefixTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ops-channel.routes.prefix', 'ops/channel');
    }

    public function test_the_endpoints_move_with_the_configured_prefix(): void
    {
        $this->postJson('/ops/channel/session')->assertOk();
        $this->postJson('/api/v1/internal/system/session')->assertNotFound();
    }
}
