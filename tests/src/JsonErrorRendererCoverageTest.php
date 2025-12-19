<?php

declare(strict_types=1);

namespace WaffleTests\Commons\ErrorHandler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Waffle\Commons\ErrorHandler\Renderer\JsonErrorRenderer;

#[CoversClass(JsonErrorRenderer::class)]
#[AllowMockObjectsWithoutExpectations]
class JsonErrorRendererCoverageTest extends TestCase
{
    #[DataProvider('statusCodeProvider')]
    public function testGetTitleForStatus(int $statusCode, string $expectedTitle): void
    {
        // Reflection check or just mock exceptions that map to these?
        // determineStatusCode is private.
        // But render() calls it.
        // And render calls getTitleForStatus(status).
        // BUT determining status is hard-coded for specific exceptions currently.
        // 400: InvalidArgumentException
        // 404: RouteNotFoundExceptionInterface
        // 500: others
        
        // I can't currently trigger 401, 403, 405 because `determineStatusCode` doesn't support them yet.
        // However, I can use reflection to test private `getTitleForStatus` or mock `determineStatusCode`?
        // No, I can't mock private method of class under test.
        // I can use Reflection to invoke private method `getTitleForStatus`.
        
        $renderer = new JsonErrorRenderer($this->createMock(ResponseFactoryInterface::class));
        $reflection = new \ReflectionClass($renderer);
        $method = $reflection->getMethod('getTitleForStatus');
        // $method->setAccessible(true); // Deprecated in PHP 8.5, no longer needed.
        
        $title = $method->invoke($renderer, $statusCode);
        $this->assertEquals($expectedTitle, $title);
    }

    public static function statusCodeProvider(): array
    {
        return [
            [400, 'Bad Request'],
            [401, 'Unauthorized'],
            [403, 'Forbidden'],
            [404, 'Not Found'],
            [405, 'Method Not Allowed'],
            [500, 'Internal Server Error'],
            [418, 'Unknown Error'], // Default case
        ];
    }
}
