<?php

declare(strict_types=1);

namespace Waffle\Commons\ErrorHandler\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Waffle\Commons\Contracts\ErrorHandler\ErrorRendererInterface;

/**
 * Catches all exceptions thrown by subsequent middlewares and renders them using the configured renderer.
 * This middleware should be the very first one in the stack.
 */
final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private ErrorRendererInterface $renderer,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Execute the rest of the application
            return $handler->handle($request);
        } catch (Throwable $e) {
            $this->logException($e, $request);
            // Catch ANY error/exception and delegate rendering
            return $this->renderer->render($e, $request);
        }
    }

    private function logException(Throwable $e, ServerRequestInterface $request): void
    {
        $this->logger->critical(
            message: $e->getMessage(),
            context: [
            'exception' => get_class($e),
            // 'trace' => $e->getTrace(), // Uncomment for stack trace
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
        ]);
    }
}
