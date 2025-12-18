<?php

declare(strict_types=1);

namespace Waffle\Commons\ErrorHandler\Renderer;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Waffle\Commons\Contracts\ErrorHandler\ErrorRendererInterface;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundExceptionInterface;

/**
 * Renders exceptions as JSON following RFC 7807 "Problem Details for HTTP APIs".
 */
final readonly class JsonErrorRenderer implements ErrorRendererInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private bool $debug = false
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

        // Security: Only expose trace in debug mode
        if ($this->debug) {
            $payload['trace'] = explode("\n", $e->getTraceAsString());
            $payload['file'] = $e->getFile();
            $payload['line'] = $e->getLine();
        } else {
            // In production, mask generic internal errors to avoid leaking info
            if ($status >= 500) {
                $payload['detail'] = 'An internal server error occurred.';
            }
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($json);
        $response->getBody()->rewind();

        return $response->withHeader('Content-Type', 'application/problem+json');
    }

    private function determineStatusCode(Throwable $e): int
    {
        if ($e instanceof RouteNotFoundExceptionInterface) {
            return 404;
        }

        if ($e instanceof \InvalidArgumentException) {
            return 400;
        }

        // Logic can be extended here for 403, 405, etc.
        // Default to 500
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
            500 => 'Internal Server Error',
            default => 'Unknown Error',
        };
    }
}
