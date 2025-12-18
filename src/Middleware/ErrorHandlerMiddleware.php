<?php

declare(strict_types=1);

namespace Waffle\Commons\ErrorHandler\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Waffle\Commons\Contracts\ErrorHandler\ErrorRendererInterface;

/**
 * Catches all exceptions thrown by subsequent middlewares and renders them using the configured renderer.
 * This middleware should be the very first one in the stack.
 */
final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ErrorRendererInterface $renderer,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Execute the rest of the application
            return $handler->handle($request);
        } catch (Throwable $e) {
            // Catch ANY error/exception and delegate rendering
            return $this->renderer->render($e, $request);
        }
    }
}
