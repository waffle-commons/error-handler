<?php

declare(strict_types=1);

namespace WaffleTests\Commons\ErrorHandler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Waffle\Commons\ErrorHandler\Renderer\JsonErrorRenderer;
use Waffle\Commons\Contracts\Routing\Exception\RouteNotFoundExceptionInterface;

#[CoversClass(JsonErrorRenderer::class)]
#[AllowMockObjectsWithoutExpectations]
final class JsonErrorRendererTest extends TestCase
{
    public function testRenderFormatsRfc7807Json(): void
    {
        // 1. Setup
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);
        $request = $this->createStub(ServerRequestInterface::class);
        $uri = $this->createStub(UriInterface::class);

        // 2. Expectations
        $uri->method('getPath')->willReturn('/api/test');
        $request->method('getUri')->willReturn($uri);

        $response->method('getBody')->willReturn($stream);
        $response
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/problem+json')
            ->willReturnSelf();

        $factory->expects($this->once())->method('createResponse')->with(500)->willReturn($response);

        // 3. Execution
        $renderer = new JsonErrorRenderer($factory, debug: false);
        $exception = new RuntimeException('Boom!');

        $renderer->render($exception, $request);
    }

    public function testRenderIncludesTraceInDebugMode(): void
    {
        // Setup
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $request = $this->createStub(ServerRequestInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $uri->method('getPath')->willReturn('/debug');
        $request->method('getUri')->willReturn($uri);

        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnSelf();
        $factory->method('createResponse')->willReturn($response);

        // Expect write called with json containing trace
        $stream->expects($this->once())->method('write')->willReturnCallback(function($json) {
            $data = json_decode($json, true);
            $this->assertArrayHasKey('trace', $data);
            $this->assertArrayHasKey('file', $data);
            $this->assertArrayHasKey('line', $data);
            $this->assertEquals('Boom!', $data['detail']);
            return strlen($json);
        });

        $renderer = new JsonErrorRenderer($factory, debug: true);
        $exception = new RuntimeException('Boom!');

        $renderer->render($exception, $request);
    }

    public function testRenderMasksUseInternalErrorInProdMode(): void
    {
        // Setup
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $request = $this->createStub(ServerRequestInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $uri->method('getPath')->willReturn('/prod');
        $request->method('getUri')->willReturn($uri);

        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnSelf();
        $factory->method('createResponse')->willReturn($response);

        // Expect write called with json containing generic error message
        $stream->expects($this->once())->method('write')->willReturnCallback(function($json) {
            $data = json_decode($json, true);
            $this->assertArrayNotHasKey('trace', $data);
            $this->assertEquals('An internal server error occurred.', $data['detail']);
            return strlen($json);
        });

        $renderer = new JsonErrorRenderer($factory, debug: false);
        $exception = new RuntimeException('Secret DB Error');

        $renderer->render($exception, $request);
    }

    public function testRenderHandlesRouteNotFoundException(): void
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
         // We need the stream mock otherwise getBody()->write() calls on null
        $stream = $this->createStub(StreamInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnSelf();

        // Expect 404 status
        $factory->expects($this->once())->method('createResponse')->with(404)->willReturn($response);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($this->createStub(UriInterface::class));

        $renderer = new JsonErrorRenderer($factory, debug: false);
        
        $exception = new class extends RuntimeException implements RouteNotFoundExceptionInterface {};

        $renderer->render($exception, $request);
    }

    public function testRenderHandlesInvalidArgumentException(): void
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $stream = $this->createStub(StreamInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnSelf();

        // Expect 400 status
        $factory->expects($this->once())->method('createResponse')->with(400)->willReturn($response);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($this->createStub(UriInterface::class));

        $renderer = new JsonErrorRenderer($factory, debug: false);
        
        $exception = new \InvalidArgumentException('Bad input');

        $renderer->render($exception, $request);
    }
}
