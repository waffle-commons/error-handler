<?php

declare(strict_types=1);

namespace WaffleTests\Commons\ErrorHandler\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Waffle\Commons\Contracts\ErrorHandler\ErrorRendererInterface;
use Waffle\Commons\ErrorHandler\Middleware\ErrorHandlerMiddleware;
use WaffleTests\Commons\ErrorHandler\Helper\RecordingLogger;

#[CoversClass(ErrorHandlerMiddleware::class)]
#[AllowMockObjectsWithoutExpectations]
final class ErrorHandlerMiddlewareTest extends TestCase
{
    public function testProcessReturnsResponseFromHandlerOnSuccess(): void
    {
        $renderer = $this->createMock(ErrorRendererInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $handler->expects($this->once())->method('handle')->with($request)->willReturn($response);

        $renderer->expects($this->never())->method('render');

        $middleware = new ErrorHandlerMiddleware($renderer);
        $result = $middleware->process($request, $handler);

        static::assertSame($response, $result);
    }

    public function testProcessCatchesExceptionAndDelegatesToRenderer(): void
    {
        $renderer = $this->createMock(ErrorRendererInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $exception = new RuntimeException('Test Exception');

        $handler->expects($this->once())->method('handle')->with($request)->willThrowException($exception);

        $renderer->expects($this->once())->method('render')->with($exception, $request)->willReturn($response);

        $middleware = new ErrorHandlerMiddleware($renderer);
        $result = $middleware->process($request, $handler);

        static::assertSame($response, $result);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function serverFaultStatuses(): iterable
    {
        yield 'internal server error' => [500];
        yield 'bad gateway' => [502];
        yield 'service unavailable' => [503];
    }

    #[DataProvider('serverFaultStatuses')]
    public function testServerFaultIsLoggedAsCriticalWithTheStackTrace(int $status): void
    {
        $logger = new RecordingLogger();
        $exception = new RuntimeException('Database unreachable');

        $this->process($exception, $this->renderedWith($status), $logger);

        static::assertCount(1, $logger->records);
        $record = $logger->records[0];
        static::assertSame(LogLevel::CRITICAL, $record['level']);
        static::assertSame('Database unreachable', $record['message']);
        static::assertSame($status, $record['context']['status']);
        static::assertSame(RuntimeException::class, $record['context']['exception']);
        static::assertArrayHasKey('trace', $record['context']);
        static::assertIsString($record['context']['trace']);
        static::assertNotSame('', $record['context']['trace']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function clientErrorStatuses(): iterable
    {
        yield 'bad request' => [400];
        yield 'forbidden' => [403];
        yield 'not found' => [404];
        yield 'unprocessable content' => [422];
        yield 'last client status' => [499];
    }

    #[DataProvider('clientErrorStatuses')]
    public function testClientErrorIsLoggedAsNoticeWithoutTheStackTrace(int $status): void
    {
        $logger = new RecordingLogger();
        $exception = new RuntimeException('No route for /wp-login.php');

        $this->process($exception, $this->renderedWith($status), $logger);

        static::assertCount(1, $logger->records);
        $record = $logger->records[0];
        static::assertSame(LogLevel::NOTICE, $record['level']);
        static::assertSame('No route for /wp-login.php', $record['message']);
        static::assertSame($status, $record['context']['status']);
        static::assertSame(RuntimeException::class, $record['context']['exception']);
        static::assertArrayNotHasKey('trace', $record['context']);
    }

    public function testRendererFailureStillLogsTheOriginalFaultAndPropagates(): void
    {
        $logger = new RecordingLogger();
        $original = new RuntimeException('Original fault');
        $renderingFailure = new RuntimeException('Renderer broke');

        $renderer = $this->createStub(ErrorRendererInterface::class);
        $renderer->method('render')->willThrowException($renderingFailure);

        try {
            $this->process($original, $renderer, $logger);
            static::fail('The rendering failure must propagate.');
        } catch (RuntimeException $caught) {
            static::assertSame($renderingFailure, $caught);
        }

        static::assertCount(1, $logger->records);
        $record = $logger->records[0];
        static::assertSame(LogLevel::CRITICAL, $record['level']);
        static::assertSame('Original fault', $record['message']);
        static::assertSame(500, $record['context']['status']);
        static::assertArrayHasKey('trace', $record['context']);
    }

    private function renderedWith(int $status): ErrorRendererInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        $renderer = $this->createStub(ErrorRendererInterface::class);
        $renderer->method('render')->willReturn($response);

        return $renderer;
    }

    private function process(
        RuntimeException $exception,
        ErrorRendererInterface $renderer,
        RecordingLogger $logger,
    ): ResponseInterface {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($exception);

        $middleware = new ErrorHandlerMiddleware($renderer, $logger);

        return $middleware->process($this->createStub(ServerRequestInterface::class), $handler);
    }
}
