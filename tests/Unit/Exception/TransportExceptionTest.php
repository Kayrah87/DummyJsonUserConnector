<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Exception;

use Kayrah87\DummyJsonUserConnector\Exception\AbstractResponseException;
use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Kayrah87\DummyJsonUserConnector\Exception\TransportException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class TransportExceptionTest extends TestCase
{
    #[Test]
    public function implementsDummyJsonException(): void
    {
        $exception = new TransportException('msg', 'GET', 'https://api.test');

        self::assertInstanceOf(DummyJsonException::class, $exception);
    }

    #[Test]
    public function extendsRuntimeException(): void
    {
        $exception = new TransportException('msg', 'GET', 'https://api.test');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }

    #[Test]
    public function doesNotExtendAbstractResponseException(): void
    {
        $exception = new TransportException('msg', 'GET', 'https://api.test');

        self::assertNotInstanceOf(AbstractResponseException::class, $exception);
    }

    #[Test]
    public function storesRequestMethod(): void
    {
        $exception = new TransportException('msg', 'POST', 'https://api.test/users/add');

        self::assertSame('POST', $exception->getRequestMethod());
    }

    #[Test]
    public function storesRequestUri(): void
    {
        $exception = new TransportException('msg', 'POST', 'https://api.test/users/add');

        self::assertSame('https://api.test/users/add', $exception->getRequestUri());
    }

    #[Test]
    public function chainsClientExceptionAsPrevious(): void
    {
        $cause = $this->makeClientException('DNS resolution failure');
        $exception = new TransportException('msg', 'GET', 'https://api.test', $cause);

        self::assertSame($cause, $exception->getPrevious());
    }

    #[Test]
    public function forRequestFactoryAssemblesMessageWithUpstreamError(): void
    {
        $cause = $this->makeClientException('DNS resolution failure');

        $exception = TransportException::forRequest('GET', 'https://api.test/users/1', $cause);

        self::assertSame('GET', $exception->getRequestMethod());
        self::assertSame('https://api.test/users/1', $exception->getRequestUri());
        self::assertSame($cause, $exception->getPrevious());
        self::assertStringContainsString('GET', $exception->getMessage());
        self::assertStringContainsString('https://api.test/users/1', $exception->getMessage());
        self::assertStringContainsString('DNS resolution failure', $exception->getMessage());
    }

    private function makeClientException(string $message): ClientExceptionInterface
    {
        return new class ($message) extends RuntimeException implements ClientExceptionInterface {};
    }
}
