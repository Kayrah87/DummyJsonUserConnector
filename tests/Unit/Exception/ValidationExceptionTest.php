<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Exception;

use InvalidArgumentException;
use Kayrah87\DummyJsonUserConnector\Exception\AbstractResponseException;
use Kayrah87\DummyJsonUserConnector\Exception\DummyJsonException;
use Kayrah87\DummyJsonUserConnector\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ValidationExceptionTest extends TestCase
{
    #[Test]
    public function implementsDummyJsonException(): void
    {
        self::assertInstanceOf(DummyJsonException::class, new ValidationException('msg'));
    }

    #[Test]
    public function extendsInvalidArgumentException(): void
    {
        self::assertInstanceOf(InvalidArgumentException::class, new ValidationException('msg'));
    }

    #[Test]
    public function doesNotExtendAbstractResponseException(): void
    {
        self::assertNotInstanceOf(AbstractResponseException::class, new ValidationException('msg'));
    }

    #[Test]
    public function defaultsFieldToNull(): void
    {
        $exception = new ValidationException('msg');

        self::assertNull($exception->getField());
    }

    #[Test]
    public function storesField(): void
    {
        $exception = new ValidationException('msg', 'email');

        self::assertSame('email', $exception->getField());
    }

    #[Test]
    public function chainsPreviousException(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new ValidationException('msg', null, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function emptyFieldFactoryBuildsMessageAndField(): void
    {
        $exception = ValidationException::emptyField('firstName');

        self::assertSame('firstName must not be empty', $exception->getMessage());
        self::assertSame('firstName', $exception->getField());
    }

    #[Test]
    public function controlCharactersFactoryBuildsMessageAndField(): void
    {
        $exception = ValidationException::controlCharacters('lastName');

        self::assertSame('lastName contains control characters', $exception->getMessage());
        self::assertSame('lastName', $exception->getField());
    }

    #[Test]
    public function invalidEmailFactoryUsesEmailFieldLabel(): void
    {
        $exception = ValidationException::invalidEmail();

        self::assertSame('email is not a valid address', $exception->getMessage());
        self::assertSame('email', $exception->getField());
    }

    #[Test]
    public function missingFieldFactoryBuildsMessageAndField(): void
    {
        $exception = ValidationException::missingField('email');

        self::assertSame('email must be a non-empty string', $exception->getMessage());
        self::assertSame('email', $exception->getField());
    }

    #[Test]
    public function apiRejectedFactoryLeavesFieldNull(): void
    {
        $exception = ValidationException::apiRejected('bad input from API');

        self::assertStringContainsString('bad input from API', $exception->getMessage());
        self::assertNull($exception->getField());
    }
}
