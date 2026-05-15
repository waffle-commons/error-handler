<?php

declare(strict_types=1);

namespace WaffleTests\Commons\ErrorHandler;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Waffle\Commons\Contracts\Exception\Validation\ValidationExceptionInterface;
use Waffle\Commons\ErrorHandler\Renderer\JsonErrorRenderer;

#[CoversClass(JsonErrorRenderer::class)]
#[AllowMockObjectsWithoutExpectations]
final class JsonErrorRendererValidationTest extends TestCase
{
    /**
     * Builds the standard renderer wiring and returns the writable StreamInterface
     * mock so callers can capture the JSON payload it receives.
     *
     * @return array{0: JsonErrorRenderer, 1: MockObject&StreamInterface, 2: ServerRequestInterface}
     */
    private function makeRenderer(int $expectedStatus): array
    {
        $factory = $this->createMock(ResponseFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->method('getBody')->willReturn($stream);
        $response->method('withHeader')->willReturnSelf();

        $factory->expects($this->once())->method('createResponse')->with($expectedStatus)->willReturn($response);

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/api/things');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        return [new JsonErrorRenderer($factory, debug: false), $stream, $request];
    }

    private function exception(string $message, ?string $field = null, int $code = 422): \Throwable
    {
        return new class($message, $field, $code) extends \RuntimeException implements ValidationExceptionInterface {
            public function __construct(
                string $message,
                private readonly ?string $field,
                int $code,
            ) {
                parent::__construct($message, $code);
            }

            #[\Override]
            public function getField(): ?string
            {
                return $this->field;
            }
        };
    }

    public function testValidationExceptionAlwaysProducesStatus422(): void
    {
        [$renderer, $stream, $request] = $this->makeRenderer(expectedStatus: 422);

        // Capture the JSON payload written to the stream.
        $captured = null;
        $stream
            ->method('write')
            ->willReturnCallback(static function (string $json) use (&$captured): int {
                $captured = $json;
                return strlen($json);
            });

        $renderer->render($this->exception('payload invalid'), $request);

        static::assertIsString($captured);
        $payload = json_decode((string) $captured, associative: true);
        static::assertSame(422, $payload['status']);
        static::assertSame('Unprocessable Entity', $payload['title']);
        static::assertSame('payload invalid', $payload['detail']);
    }

    public function testValidationExceptionFieldIsSurfacedInPayload(): void
    {
        [$renderer, $stream, $request] = $this->makeRenderer(expectedStatus: 422);

        $captured = null;
        $stream
            ->method('write')
            ->willReturnCallback(static function (string $json) use (&$captured): int {
                $captured = $json;
                return strlen($json);
            });

        $renderer->render($this->exception('invalid email', 'email'), $request);

        $payload = json_decode((string) $captured, associative: true);
        static::assertSame('email', $payload['field']);
    }

    public function testValidationExceptionWithoutFieldOmitsFieldKey(): void
    {
        [$renderer, $stream, $request] = $this->makeRenderer(expectedStatus: 422);

        $captured = null;
        $stream
            ->method('write')
            ->willReturnCallback(static function (string $json) use (&$captured): int {
                $captured = $json;
                return strlen($json);
            });

        $renderer->render($this->exception('malformed body'), $request);

        $payload = json_decode((string) $captured, associative: true);
        static::assertArrayNotHasKey('field', $payload);
    }

    public function testValidationStatusBeatsExceptionCodeHeuristic(): void
    {
        // Even when the exception code is itself a valid HTTP status (e.g. 400),
        // a ValidationExceptionInterface MUST still surface as 422.
        [$renderer, $stream, $request] = $this->makeRenderer(expectedStatus: 422);

        $stream->method('write')->willReturn(0);

        $renderer->render($this->exception('payload', null, code: 400), $request);
    }
}
