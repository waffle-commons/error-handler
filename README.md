[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/error-handler/require/php)](https://packagist.org/packages/waffle-commons/error-handler)
[![PHP CI](https://github.com/waffle-commons/error-handler/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/error-handler/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/error-handler/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/error-handler)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/error-handler/v)](https://packagist.org/packages/waffle-commons/error-handler)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/error-handler/v/unstable)](https://packagist.org/packages/waffle-commons/error-handler)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/error-handler.svg)](https://packagist.org/packages/waffle-commons/error-handler)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/error-handler)](https://github.com/waffle-commons/error-handler/blob/main/LICENSE.md)

Waffle Error Handler Component
==============================

> **Release:** `0.1.0-beta4` &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md)
> **PSR Compliance:** PSR-15 (middleware), PSR-3 (logging), RFC 7807 (`application/problem+json`), RFC 7231 (`Allow` header on `405`)

The outermost middleware in every Waffle pipeline. Catches `Throwable` thrown deeper in the stack, logs it via the injected PSR-3 logger, and renders an RFC 7807 "Problem Details" JSON response.

## 📦 Installation

```bash
composer require waffle-commons/error-handler
```

## 🧱 Surface

| Class | Role |
| :--- | :--- |
| `Waffle\Commons\ErrorHandler\Middleware\ErrorHandlerMiddleware` | PSR-15 middleware. Wraps `$handler->handle()` in `try/catch(Throwable)`, logs, then delegates to the renderer. |
| `Waffle\Commons\ErrorHandler\Renderer\JsonErrorRenderer` | `final readonly` renderer implementing `ErrorRendererInterface`. Produces RFC 7807 JSON. |

## 🚀 Wiring it up

```php
use Waffle\Commons\ErrorHandler\Middleware\ErrorHandlerMiddleware;
use Waffle\Commons\ErrorHandler\Renderer\JsonErrorRenderer;
use Waffle\Commons\Http\Factory\ResponseFactory;
use Waffle\Commons\Log\StreamLogger;

$renderer = new JsonErrorRenderer(
    responseFactory: new ResponseFactory(),
    debug: $appDebug, // false in production
);

$stack->prepend(new ErrorHandlerMiddleware($renderer, new StreamLogger()));
```

## 📦 RFC 7807 payload

`JsonErrorRenderer::render(Throwable $e, ServerRequestInterface $request)` always emits the canonical RFC 7807 shape, encoded with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES`:

```json
{
  "type":     "about:blank",
  "title":    "Bad Request",
  "status":   400,
  "detail":   "Untrusted Host \"evil.example\".",
  "instance": "/login"
}
```

Extensions added by Waffle:

- If the exception implements `Waffle\Commons\Contracts\Exception\Validation\ValidationExceptionInterface` and `getField()` returns non-null, a `field` key is added to the payload (RFC-011).
- If `$debug = true`, additional `trace`, `file`, `line` keys are added.
- In production (`$debug = false`), any 5xx `detail` is masked to `"An internal server error occurred."` to avoid leaking implementation details.

## 🧩 Status-code resolution

`JsonErrorRenderer::determineStatusCode(Throwable $e)` walks well-known exception interfaces (e.g. `ValidationExceptionInterface` → 422, `RouteNotFoundExceptionInterface` → 404, `MethodNotAllowedExceptionInterface` → 405, `\InvalidArgumentException` → 400) and falls back to `500` for unknown throwables. The matching is interface-based — your application exceptions can opt in by implementing the right contract interface. For a `MethodNotAllowedExceptionInterface`, the renderer also emits an RFC 7231 `Allow` header (e.g. `Allow: GET, HEAD, OPTIONS, POST`).

## 🐘 PHP 8.5 features used

- `final readonly class JsonErrorRenderer` — the renderer holds an injected `ResponseFactoryInterface` and a `bool $debug` flag, both `readonly`.
- Strict-typed constructor + return types.
- `JSON_THROW_ON_ERROR` for fail-fast encoding.

## 🧭 Architectural boundary (`mago guard`)

An active dependency **perimeter** is enforced on every CI run by `vendor/bin/mago guard` (bundled into `composer mago`; zero baselines). The rules live in [`mago.toml`](./mago.toml) under `[guard.perimeter]` — a forbidden `use` statement fails the build, not a reviewer.

Production code under `Waffle\Commons\ErrorHandler` may depend **only** on:

- `Waffle\Commons\ErrorHandler\**` — itself
- `Waffle\Commons\Contracts\**` — the shared contracts package, the **only** Waffle dependency permitted
- `Psr\**` — PSR interfaces (PSR-7 / PSR-15 / PSR-17)
- `@global` + `Psl\**` — PHP core and the PHP Standard Library

Test code under `WaffleTests\Commons\ErrorHandler` is unrestricted (`@all`). Structural rules are guarded too: interfaces must be named `*Interface`, `Exception\**` classes must end in `*Exception`, and any `Enum\**` namespace may hold only `enum` declarations.

Contract-first, component-agnostic by construction: components compose through `waffle-commons/contracts`, never directly through one another.

## 🧪 Testing

```bash
docker exec -w /waffle-commons/error-handler waffle-dev composer tests
```

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).
