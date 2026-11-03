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

    public function boot(): void
    {
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
