<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Exception;

use Kayrah87\DummyJsonUserConnector\Exception\AbstractResponseException;
use Kayrah87\DummyJsonUserConnector\Exception\ApiException;
use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApiExceptionTest extends TestCase
{
    #[Test]
    public function implementsDummyJsonException(): void
    {
        self::assertInstanceOf(DummyJsonException::class, new ApiException('boom', 500));
    }

    #[Test]
    public function extendsAbstractResponseException(): void
    {
        self::assertInstanceOf(AbstractResponseException::class, new ApiException('boom', 500));
    }

    #[Test]
    public function storesStatusCode(): void
    {
        $exception = new ApiException('boom', 502);

        self::assertSame(502, $exception->getStatusCode());
    }

    #[Test]
    public function storesMessage(): void
    {
        $exception = new ApiException('specific message', 500);

        self::assertSame('specific message', $exception->getMessage());
    }

    #[Test]
    public function carriesResponseWhenProvided(): void
    {
        $response = new Response(500, [], 'upstream body');
        $exception = new ApiException('boom', 500, $response);

        self::assertSame($response, $exception->getResponse());
        self::assertSame('upstream body', $exception->getResponseBody());
    }

    #[Test]
    public function chainsPreviousException(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new ApiException('boom', 500, previous: $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function forResponseFactoryAssemblesMessageAndFields(): void
    {
        $response = new Response(502, [], 'Bad Gateway');
        $exception = ApiException::forResponse(
            statusCode: 502,
            method: 'GET',
            uri: 'https://api.test/users/1',
            response: $response,
        );

        self::assertSame(502, $exception->getStatusCode());
        self::assertSame($response, $exception->getResponse());
        self::assertStringContainsString('502', $exception->getMessage());
        self::assertStringContainsString('GET', $exception->getMessage());
        self::assertStringContainsString('https://api.test/users/1', $exception->getMessage());
    }
}
