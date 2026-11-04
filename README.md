# OpsChannel

Run an Artisan command on a deployed Laravel application over a signed, single-use
challenge–response handshake.

The application stores only the **public** half of an Ed25519 key pair. The private half
stays with the one caller allowed to use the channel, so nothing in the repository or the
environment is a usable credential on its own.

## Installation

```bash
composer require medox/ops-channel
```

The package registers **no routes** until you name a key to trust, so installing it — or
picking it up as a transitive dependency — grants nothing.

```bash
php artisan vendor:publish --tag=ops-channel-config
```

```dotenv
OPS_CHANNEL_PUBLIC_KEY="base64-encoded 32-byte Ed25519 public key"
```

Generate a pair wherever the private half will live:

```php
$keypair = sodium_crypto_sign_keypair();

// Keep this secret, outside the application.
base64_encode(sodium_crypto_sign_secretkey($keypair));

// This goes in OPS_CHANNEL_PUBLIC_KEY.
base64_encode(sodium_crypto_sign_publickey($keypair));
```

## The protocol

Two requests. The first issues a challenge, the second answers it.

**1. Open a session**

```
POST {prefix}/session
→ 200 {"message":"OK","data":{"session_id":"…","challenge":"…","expires_in":30}}
```

**2. Run a command**

Sign `challenge|command|timestamp` — the three values joined by `|`, no spaces — with the
private key, base64 the detached signature, and post it back:

```
POST {prefix}/sync
{
    "session_id": "…",
    "command": "cache:clear",
    "timestamp": 1760000000,
    "signature": "…"
}
→ 200 {"message":"OK","data":{"command":"cache:clear","exit_code":0,"output":"…"}}
```

The default prefix is `api/v1/internal/system`.

### What the handshake guarantees

- **Single use.** The challenge is redeemed on the first verification attempt, pass or
  fail, so a captured request cannot be replayed.
- **Bound to the command.** The command is inside the signed message, so an intercepted
  signature cannot be moved to a different command.
- **Fresh.** A timestamp outside the tolerance window is refused.
- **Silent.** Every rejection is a flat `404` with no detail, so an unauthorized caller
  cannot tell a wrong signature from a route that does not exist.

### What it does not do

The signature is the whole authorization. Any Artisan command the application has is in
reach of whoever holds the private key — there is no allow-list. Treat that key the way
you would treat shell access to the host.

## Configuration

| Key | Env | Default | |
| --- | --- | --- | --- |
| `public_key` | `OPS_CHANNEL_PUBLIC_KEY` | — | Base64 Ed25519 public key. Unset means no routes. |
| `handshake.challenge_ttl` | `OPS_CHANNEL_CHALLENGE_TTL` | `30` | Seconds a challenge stays redeemable. |
| `handshake.timestamp_tolerance` | `OPS_CHANNEL_TIMESTAMP_TOLERANCE` | `30` | Allowed clock drift, seconds. |
| `handshake.cache_prefix` | `OPS_CHANNEL_CACHE_PREFIX` | `ops-channel:handshake:` | Cache key namespace. |
| `handshake.cache_store` | `OPS_CHANNEL_CACHE_STORE` | default store | Where pending challenges live. |
| `routes.enabled` | `OPS_CHANNEL_ROUTES_ENABLED` | `true` | Turn the endpoints off without unsetting the key. |
| `routes.prefix` | `OPS_CHANNEL_ROUTE_PREFIX` | `api/v1/internal/system` | |
| `routes.session_throttle` | `OPS_CHANNEL_SESSION_THROTTLE` | `30,1` | Rate limit on the challenge endpoint. |
| `routes.middleware` | — | `[]` | Extra middleware for both endpoints, e.g. an IP allow-list. |

Overriding one key of a nested group is enough — naming `routes.prefix` alone keeps
`enabled`, `session_throttle` and `middleware` on their defaults rather than dropping them.

**The cache store must survive between two requests.** The challenge is written when the
session opens and read back when the signature arrives. An `array` store refuses every
call, and a per-worker store refuses any call whose two requests land on different
workers.

## Operational notes

A command runs inside an HTTP request, so it is bound by the server's
`max_execution_time` and by any gateway timeout in front of it. A command that outlives
either one is killed with the client still waiting on an open socket — which looks exactly
like slow progress. Keep each call short and drive long work as a loop of small calls from
the caller's side.

## Overriding behaviour

Each piece is bound to an interface and can be swapped in your own service provider:

- `Medox\OpsChannel\Contracts\HandshakeServiceInterface` — issuing and verifying challenges
- `Medox\OpsChannel\Contracts\CommandServiceInterface` — what happens to a verified command
- `Medox\OpsChannel\Contracts\ArtisanRunnerInterface` — how the command is executed

Binding your own `CommandServiceInterface` is the seam for an audit log or an allow-list.

## Testing

```bash
composer install
./vendor/bin/phpunit
```

## License

MIT.
