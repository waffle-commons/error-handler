# Changelog — waffle-commons/error-handler

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [Unreleased] — targeting `0.1.0-beta3`

**Theme: identity federation & stateless persistence (ecosystem wave).**

### Changed
- Lockstep version bump; `composer.lock` refreshed with the beta-3 dependency wave.

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
