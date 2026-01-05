<?php

declare(strict_types=1);

namespace WaffleTests\Commons\ErrorHandler\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Waffle\Commons\Contracts\ErrorHandler\ErrorRendererInterface;
use Waffle\Commons\ErrorHandler\Middleware\ErrorHandlerMiddleware;

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
}
