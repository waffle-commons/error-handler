# Changelog — waffle-commons/error-handler

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta6] — 2026-08-22

**Theme: worker-safety audit coverage.**

### Changed
- **Worker-safety audit coverage.** This component was never audited by `wfl igor`: it had no `igor-php/igor-php` in `require-dev`, so the ecosystem runner silently skipped it for four releases. It now ships `igor.json`, the `composer igor` script, and the dev dependency, and is part of the 0-KO gate.

### Documentation
- The README now links into the central Diátaxis documentation tree (DOC-02).

## [0.1.0-beta5] — 2026-07-08

**Theme: fail-safe error masking & forensics.**

### Security
- **LEAK-03** — `JsonErrorRenderer::render()` now masks every exception message by default: `detail` falls back to the RFC 7807 `title` instead of echoing `Throwable::getMessage()`. The real message is surfaced **only** in debug mode or for a fixed allow-list of client-safe exception types — `ValidationExceptionInterface` (field messages), `RouteNotFoundExceptionInterface`, and `MethodNotAllowedExceptionInterface` — regardless of HTTP status. A `403` (or any non-5xx) no longer leaks the controller FQCN/method or other internal detail to the client. Covered by `testRenderMasksLeakySecurityMessageInProd` and `testRenderSurfacesClientSafeRouteNotFoundMessageInProd`.
- Removed the previous status-driven masking (`status >= 500 && !debug → "An internal server error occurred."`); masking is now type-driven and applies at every status, closing the gap where 4xx leaks slipped through.

### Changed
- **OBS-02** — `ErrorHandlerMiddleware` restores full server-side stack-trace capture: the critical log entry now records `getTraceAsString()` for forensics. The trace is logged only and is never serialised into the client response (the renderer masks the client per LEAK-03).
- Enabled the `cyclomatic-complexity` Mago lint rule with a `threshold = 50`.

## [0.1.0-beta4] — 2026-06-13

### Changed
- Lockstep version bump with the Beta-4 wave (security hardening, worker-mode diagnostics, and DX tooling landed in sibling components). No behavioural changes in this component since `0.1.0-beta3`.

## [0.1.0-beta3] — 2026-06-07

**Theme: identity federation & stateless persistence (ecosystem wave).**

### Changed
- Lockstep version bump; `composer.lock` refreshed with the beta-3 dependency wave.

## [0.1.0-beta2.1] — 2026-05-30

### Changed
- Lockstep re-tag of `0.1.0-beta2` (umbrella housekeeping patch) — no source changes in this component.

## [0.1.0-beta2] — 2026-05-29

**Theme: RFC 7231 conformance — `405 Method Not Allowed` mapping with `Allow` header.**

### Added
- `JsonErrorRenderer::determineStatusCode()` explicitly maps any `Waffle\Commons\Contracts\Routing\Exception\MethodNotAllowedExceptionInterface` to HTTP status code **405**.
- `JsonErrorRenderer::render()` injects a comma-separated `Allow` header populated from the exception's `getAllowedMethods()` when the methods list is non-empty.
- README documents the 405 mapping and the conditional `Allow` header emission behaviour.

### Fixed
- `Allow` header is now omitted entirely when the allowed-methods list is empty, preventing a malformed, value-less header. Covered by `testRenderOmitsAllowHeaderWhenAllowedMethodsAreEmpty`.

### Tests
- `testRenderHandlesMethodNotAllowedExceptionAndSetsAllowHeader` verifies the 405 status code + populated `Allow` header round-trip.
- `testRenderOmitsAllowHeaderWhenAllowedMethodsAreEmpty` verifies the empty-list edge case.

### Dependencies
- `composer.json` bumped `waffle-commons/contracts` to the Beta-2 line.
- `composer.lock` refreshed (`psr/http-client` added; PHPUnit + Symfony polyfills bumped).

## [0.1.0-beta1]

See the umbrella [CHANGELOG](../CHANGELOG.md#010-beta1) for the full Beta-1 narrative.
