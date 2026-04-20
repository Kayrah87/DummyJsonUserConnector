<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Exception;

use JsonException;
use Kayrah87\DummyJsonUserConnector\Exception\AbstractResponseException;
use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Kayrah87\DummyJsonUserConnector\Exception\InvalidResponseException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InvalidResponseExceptionTest extends TestCase
{
    #[Test]
    public function implementsDummyJsonException(): void
    {
        self::assertInstanceOf(DummyJsonException::class, InvalidResponseException::notAnObject());
    }

    #[Test]
    public function extendsAbstractResponseException(): void
    {
        self::assertInstanceOf(AbstractResponseException::class, InvalidResponseException::notAnObject());
    }

    #[Test]
    public function fromJsonExceptionChainsPreviousAndCarriesResponse(): void
    {
        $response = new Response(200, [], 'not json at all');
        $json = new JsonException('syntax error, position 0');

        $exception = InvalidResponseException::fromJsonException($json, $response);

        self::assertSame($response, $exception->getResponse());
        self::assertSame($json, $exception->getPrevious());
        self::assertStringContainsString('not valid JSON', $exception->getMessage());
    }

    #[Test]
    public function fromJsonExceptionWorksWithoutResponse(): void
    {
        $json = new JsonException('syntax error');

        $exception = InvalidResponseException::fromJsonException($json);

        self::assertNull($exception->getResponse());
        self::assertSame($json, $exception->getPrevious());
    }

    #[Test]
    public function notAnObjectCarriesResponse(): void
    {
        $response = new Response(200, [], '[1,2,3]');

        $exception = InvalidResponseException::notAnObject($response);

        self::assertSame($response, $exception->getResponse());
        self::assertStringContainsString('not an object', $exception->getMessage());
    }

    #[Test]
    public function notAnObjectDefaultsResponseToNull(): void
    {
        $exception = InvalidResponseException::notAnObject();

        self::assertNull($exception->getResponse());
    }

    #[Test]
    public function invalidFieldProducesConsistentMessage(): void
    {
        $exception = InvalidResponseException::invalidField('User', 'id', 'non-integer');

        self::assertSame(
            'User payload: missing or non-integer "id"',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function invalidFieldCarriesResponseWhenProvided(): void
    {
        $response = new Response(200, [], 'bad shape');

        $exception = InvalidResponseException::invalidField('UserPage', 'total', 'non-integer', $response);

        self::assertSame($response, $exception->getResponse());
    }

    #[Test]
    public function malformedPassesMessageThrough(): void
    {
        $exception = InvalidResponseException::malformed('UserPage payload: "users" contains a non-array entry');

        self::assertSame(
            'UserPage payload: "users" contains a non-array entry',
            $exception->getMessage(),
        );
    }
}
