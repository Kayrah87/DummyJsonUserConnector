<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Exception;

use Kayrah87\DummyJsonUserConnector\Exception\AbstractResponseException;
use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class AbstractResponseExceptionTest extends TestCase
{
    #[Test]
    public function classIsAbstract(): void
    {
        $reflection = new ReflectionClass(AbstractResponseException::class);

        self::assertTrue($reflection->isAbstract());
    }

    #[Test]
    public function extendsRuntimeException(): void
    {
        $exception = $this->makeConcrete('msg');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }

    #[Test]
    public function implementsDummyJsonException(): void
    {
        $exception = $this->makeConcrete('msg');

        self::assertInstanceOf(DummyJsonException::class, $exception);
    }

    #[Test]
    public function getResponseReturnsNullWhenNoResponseProvided(): void
    {
        $exception = $this->makeConcrete('msg');

        self::assertNull($exception->getResponse());
    }

    #[Test]
    public function getResponseBodyReturnsNullWhenNoResponseProvided(): void
    {
        $exception = $this->makeConcrete('msg');

        self::assertNull($exception->getResponseBody());
    }

    #[Test]
    public function getResponseReturnsTheConstructedResponse(): void
    {
        $response = new Response(500, [], 'oops');
        $exception = $this->makeConcrete('msg', $response);

        self::assertSame($response, $exception->getResponse());
    }

    #[Test]
    public function getResponseBodyReadsBodyAtConstruction(): void
    {
        $response = new Response(500, [], 'server error body');
        $exception = $this->makeConcrete('msg', $response);

        self::assertSame('server error body', $exception->getResponseBody());
    }

    #[Test]
    public function getResponseBodyReturnsSameValueAcrossRepeatedCalls(): void
    {
        $response = new Response(500, [], 'cached body');
        $exception = $this->makeConcrete('msg', $response);

        self::assertSame($exception->getResponseBody(), $exception->getResponseBody());
    }

    #[Test]
    public function previousExceptionIsChainedThrough(): void
    {
        $previous = new RuntimeException('cause');
        $exception = $this->makeConcrete('msg', null, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function messageIsPassedToParentConstructor(): void
    {
        $exception = $this->makeConcrete('specific message');

        self::assertSame('specific message', $exception->getMessage());
    }

    private function makeConcrete(
        string $message,
        ?Response $response = null,
        ?Throwable $previous = null,
    ): AbstractResponseException {
        return new class ($message, $response, $previous) extends AbstractResponseException {};
    }
}
