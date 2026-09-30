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
 *
 * The log severity follows the status the client actually receives. A server fault (5xx) is logged at
 * `critical` with the full stack trace. A client error (4xx — validation, unknown route, wrong method,
 * denied access) is an expected event: it is logged at `notice`, without the trace. Logging every client
 * error at `critical` with a trace let any client grow the log at will and made alerting on `critical`
 * meaningless.
 */
final readonly class ErrorHandlerMiddleware implements MiddlewareInterface
{
    /** The first server-fault status: from here up, the entry is `critical` and carries the trace. */
    private const int SERVER_ERROR = 500;

    private LoggerInterface $logger;

    public function __construct(
        private ErrorRendererInterface $renderer,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Execute the rest of the application
            return $handler->handle($request);
        } catch (Throwable $e) {
            // Catch ANY error/exception and delegate rendering
            return $this->renderAndLog($e, $request);
        }
    }

    /**
     * Renders first, because the rendered status decides how loud the log entry is. If the renderer
     * itself fails, the original fault is still logged as a server fault before that failure propagates.
     */
    private function renderAndLog(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $rendered = false;

        try {
            $response = $this->renderer->render($e, $request);
            $rendered = true;
        } finally {
            if (!$rendered) {
                $this->logServerFault($e, $request, self::SERVER_ERROR);
            }
        }

        $status = $response->getStatusCode();
        if ($status >= self::SERVER_ERROR) {
            $this->logServerFault($e, $request, $status);
        } else {
            $this->logClientError($e, $request, $status);
        }

        return $response;
    }

    private function logServerFault(Throwable $e, ServerRequestInterface $request, int $status): void
    {
        $this->logger->critical(message: $e->getMessage(), context: [
            'exception' => get_class($e),
            'status' => $status,
            // OBS-02: full stack trace for server-side forensics — logged ONLY,
            // never serialised into the client response (LEAK-03 masks the client).
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
        ]);
    }

    private function logClientError(Throwable $e, ServerRequestInterface $request, int $status): void
    {
        $this->logger->notice(message: $e->getMessage(), context: [
            'exception' => get_class($e),
            'status' => $status,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
        ]);
    }
}
