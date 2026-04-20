<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Exception;

use Kayrah87\DummyJsonUserConnector\Exception\AbstractResponseException;
use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Kayrah87\DummyJsonUserConnector\Exception\UserNotFoundException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function implementsDummyJsonException(): void
    {
        self::assertInstanceOf(DummyJsonException::class, new UserNotFoundException(42));
    }

    #[Test]
    public function extendsAbstractResponseException(): void
    {
        self::assertInstanceOf(AbstractResponseException::class, new UserNotFoundException(42));
    }

    #[Test]
    public function storesUserId(): void
    {
        $exception = new UserNotFoundException(42);

        self::assertSame(42, $exception->getUserId());
    }

    #[Test]
    public function buildsMessageFromUserId(): void
    {
        $exception = new UserNotFoundException(42);

        self::assertSame('User not found: id=42', $exception->getMessage());
    }

    #[Test]
    public function carriesResponseWhenProvided(): void
    {
        $response = new Response(404, [], '{"message":"User with id 42 not found"}');
        $exception = new UserNotFoundException(42, $response);

        self::assertSame($response, $exception->getResponse());
        self::assertSame('{"message":"User with id 42 not found"}', $exception->getResponseBody());
    }

    #[Test]
    public function defaultsResponseToNull(): void
    {
        $exception = new UserNotFoundException(42);

        self::assertNull($exception->getResponse());
    }

    #[Test]
    public function chainsPreviousException(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new UserNotFoundException(42, null, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
