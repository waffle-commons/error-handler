<?php

declare(strict_types=1);

namespace Waffle\Commons\ErrorHandler\Renderer;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Waffle\Commons\Contracts\ErrorHandler\ErrorRendererInterface;
use Waffle\Commons\Contracts\Exception\Validation\ValidationExceptionInterface;
use Waffle\Commons\Contracts\Routing\Exception\MethodNotAllowedExceptionInterface;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundExceptionInterface;

/**
 * Renders exceptions as JSON following RFC 7807 "Problem Details for HTTP APIs".
 */
final readonly class JsonErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private bool $debug = false,
    ) {}

    public function render(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $status = $this->determineStatusCode($e);
        $response = $this->responseFactory->createResponse($status);

        $payload = [
            'type' => 'about:blank', // Should be a URI to documentation in a real app
            'title' => $this->getTitleForStatus($status),
            'status' => $status,
            'detail' => $e->getMessage(),
            'instance' => $request->getUri()->getPath(),
        ];

        // Validation errors carry an optional field name (RFC-011): surface it for clients.
        if ($e instanceof ValidationExceptionInterface) {
            $field = $e->getField();
            if ($field !== null) {
                $payload['field'] = $field;
            }
        }

        // Security: Only expose trace in debug mode
        if ($this->debug) {
            $payload['trace'] = explode("\n", $e->getTraceAsString());
            $payload['file'] = $e->getFile();
            $payload['line'] = $e->getLine();
        }

        // In production, mask generic internal errors to avoid leaking info
        if ($status >= 500 && !$this->debug) {
            $payload['detail'] = 'An internal server error occurred.';
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($json);
        $response->getBody()->rewind();

        $response = $response->withHeader('Content-Type', 'application/problem+json');

        if ($e instanceof MethodNotAllowedExceptionInterface) {
            // Inject the Allow header (RFC 7231 §7.4.1) listing the allowed methods,
            // comma-separated (e.g. "Allow: GET, POST"). Guard against an empty list so
            // a malformed, value-less Allow header is never emitted on the response.
            $allowedMethods = $e->getAllowedMethods();
            if ($allowedMethods !== []) {
                $response = $response->withHeader('Allow', implode(', ', $allowedMethods));
            }
        }

        return $response;
    }

    private function determineStatusCode(Throwable $e): int
    {
        // Validation failures take precedence over the code-based heuristic so a thrown
        // ValidationException is always surfaced as 422, regardless of any custom code.
        if ($e instanceof ValidationExceptionInterface) {
            return 422;
        }

        $code = $e->getCode();

        // If the exception code is a valid HTTP error status, use it.
        if (is_int($code) && $code >= 400 && $code < 600) {
            return $code;
        }

        // Map specific exceptions to their corresponding HTTP status codes
        if ($e instanceof RouteNotFoundExceptionInterface) {
            return 404;
        }

        if ($e instanceof MethodNotAllowedExceptionInterface) {
            return 405;
        }

        if ($e instanceof \InvalidArgumentException) {
            return 400;
        }

        // Default to 500 Internal Server Error
        return 500;
    }

    private function getTitleForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'Unknown Error',
        };
    }
}
