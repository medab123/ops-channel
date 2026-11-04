<?php

declare(strict_types=1);

namespace Medox\OpsChannel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Medox\OpsChannel\Contracts\ArtisanRunnerInterface;
use Medox\OpsChannel\Contracts\CommandServiceInterface;
use Medox\OpsChannel\Contracts\HandshakeServiceInterface;
use Medox\OpsChannel\Services\ArtisanRunner;
use Medox\OpsChannel\Services\CommandService;
use Medox\OpsChannel\Services\HandshakeService;
use Psr\Log\LoggerInterface;

final class OpsChannelServiceProvider extends ServiceProvider
{
    /**
     * The container bindings for this package.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ArtisanRunnerInterface::class => ArtisanRunner::class,
        CommandServiceInterface::class => CommandService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ops-channel.php', 'ops-channel');

        // Closure-bound because every dependency but the cache is a config value.
        $this->app->bind(HandshakeServiceInterface::class, function (Application $app): HandshakeService {
            $config = $app['config'];

            return new HandshakeService(
                cache: $app->make(CacheFactory::class)->store($config->get('ops-channel.handshake.cache_store')),
                logger: $app->make(LoggerInterface::class),
                publicKey: (string) $config->get('ops-channel.public_key', ''),
                challengeTtl: (int) $config->get('ops-channel.handshake.challenge_ttl', 30),
                timestampTolerance: (int) $config->get('ops-channel.handshake.timestamp_tolerance', 30),
                cachePrefix: (string) $config->get('ops-channel.handshake.cache_prefix', 'ops-channel:handshake:'),
            );
        });
    }

    /**
     * Merge this package's nested config defaults one level deeper than
     * mergeConfigFrom() reaches.
     *
     * mergeConfigFrom() merges top-level keys only. An application that names
     * `routes` to pin its own prefix therefore replaces the whole array and
     * loses `enabled`, `session_throttle` and `middleware` with it — and since
     * those fall back to hardcoded defaults where they are read, a dropped
     * `enabled` reads as true. OPS_CHANNEL_ROUTES_ENABLED then stops working:
     * documented, apparently configured, and silently inert. The same applies
     * to `handshake`.
     *
     * The application's own keys still win; only the ones it did not name are
     * filled in.
     *
     * Called from boot() rather than register() on purpose: it has to run after
     * everything that sets this config, and in a test harness that is later than
     * register(). Everything that reads these values — shouldRegisterRoutes()
     * below, and the routes file — runs after it here.
     */
    private function mergeNestedDefaults(): void
    {
        $config = $this->app['config'];

        /** @var array<string, mixed> $defaults */
        $defaults = require __DIR__.'/../config/ops-channel.php';

        foreach ($defaults as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $config->set("ops-channel.{$key}", array_merge(
                $value,
                (array) $config->get("ops-channel.{$key}", []),
            ));
        }
    }

    public function boot(): void
    {
        $this->mergeNestedDefaults();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ops-channel.php' => $this->app->configPath('ops-channel.php'),
            ], 'ops-channel-config');
        }

        if ($this->shouldRegisterRoutes()) {
            $this->registerRoutes();
        }
    }

    /**
     * Whether this application has opted in to exposing the channel.
     *
     * No trusted key means no endpoint at all: installing the package grants
     * nothing until an application names the key it trusts, so a dependency
     * picked up transitively can never open a command endpoint on its own.
     */
    private function shouldRegisterRoutes(): bool
    {
        $config = $this->app['config'];

        return (bool) $config->get('ops-channel.routes.enabled', true)
            && is_string($config->get('ops-channel.public_key'))
            && $config->get('ops-channel.public_key') !== '';
    }

    private function registerRoutes(): void
    {
        $config = $this->app['config'];

        Route::prefix((string) $config->get('ops-channel.routes.prefix', 'api/v1/internal/system'))
            ->middleware((array) $config->get('ops-channel.routes.middleware', []))
            ->name('ops-channel.')
            ->group(__DIR__.'/../routes/ops-channel.php');
    }
}
