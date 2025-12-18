<?php

declare(strict_types=1);

namespace WaffleTests\Commons\ErrorHandler;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Waffle\Commons\ErrorHandler\Renderer\JsonErrorRenderer;

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
}
