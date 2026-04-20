<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Dto;

use Kayrah87\DummyJsonUserConnector\Dto\NewUserInput;
use Kayrah87\DummyJsonUserConnector\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NewUserInputTest extends TestCase
{
    #[Test]
    public function constructorAcceptsValidInput(): void
    {
        $input = new NewUserInput('Alice', 'Smith', 'alice@example.com');

        self::assertSame('Alice', $input->firstName);
        self::assertSame('Smith', $input->lastName);
        self::assertSame('alice@example.com', $input->email);
    }

    #[Test]
    public function constructorTrimsWhitespaceFromAllFields(): void
    {
        $input = new NewUserInput('  Alice  ', "\tSmith\n", '  alice@example.com  ');

        self::assertSame('Alice', $input->firstName);
        self::assertSame('Smith', $input->lastName);
        self::assertSame('alice@example.com', $input->email);
    }

    #[Test]
    public function acceptsUnicodeNames(): void
    {
        $input = new NewUserInput('Zoë', "O'Brien", 'zoe@example.com');

        self::assertSame('Zoë', $input->firstName);
        self::assertSame("O'Brien", $input->lastName);
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function constructorThrowsValidationExceptionOnInvalidInput(
        string $firstName,
        string $lastName,
        string $email,
    ): void {
        $this->expectException(ValidationException::class);

        new NewUserInput($firstName, $lastName, $email);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'empty firstName' => ['', 'Smith', 'a@b.c'];
        yield 'whitespace-only firstName' => ['   ', 'Smith', 'a@b.c'];
        yield 'empty lastName' => ['Alice', '', 'a@b.c'];
        yield 'whitespace-only lastName' => ['Alice', "\t\n", 'a@b.c'];
        yield 'empty email' => ['Alice', 'Smith', ''];
        yield 'whitespace-only email' => ['Alice', 'Smith', '    '];
        yield 'malformed email (no @)' => ['Alice', 'Smith', 'not-an-email'];
        yield 'email without TLD' => ['Alice', 'Smith', 'alice@localhost'];
        yield 'null byte in firstName' => ["Alice\x00injected", 'Smith', 'a@b.c'];
        yield 'newline in lastName' => ['Alice', "Smith\ninjected", 'a@b.c'];
        yield 'tab inside email' => ['Alice', 'Smith', "alice\t@example.com"];
    }

    #[Test]
    public function toArrayEmitsCamelCaseKeys(): void
    {
        $input = new NewUserInput('Alice', 'Smith', 'alice@example.com');

        self::assertSame(
            [
                'firstName' => 'Alice',
                'lastName' => 'Smith',
                'email' => 'alice@example.com',
            ],
            $input->toArray(),
        );
    }

    #[Test]
    public function jsonSerializeMatchesToArray(): void
    {
        $input = new NewUserInput('Alice', 'Smith', 'alice@example.com');

        self::assertSame($input->toArray(), $input->jsonSerialize());
    }

    #[Test]
    public function fromApiArrayBuildsFromValidPayload(): void
    {
        $input = NewUserInput::fromApiArray([
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'email' => 'alice@example.com',
        ]);

        self::assertSame('Alice', $input->firstName);
        self::assertSame('Smith', $input->lastName);
        self::assertSame('alice@example.com', $input->email);
    }

    #[Test]
    public function fromApiArrayThrowsOnMissingField(): void
    {
        $this->expectException(ValidationException::class);

        NewUserInput::fromApiArray(['firstName' => 'Alice', 'lastName' => 'Smith']);
    }

    #[Test]
    public function fromApiArrayThrowsOnNonStringField(): void
    {
        $this->expectException(ValidationException::class);

        NewUserInput::fromApiArray(['firstName' => 42, 'lastName' => 'Smith', 'email' => 'a@b.c']);
    }

    #[Test]
    public function fromApiArrayRunsValidationAfterExtraction(): void
    {
        // Missing field check happens first; empty string would fail validation after.
        $this->expectException(ValidationException::class);

        NewUserInput::fromApiArray(['firstName' => '   ', 'lastName' => 'Smith', 'email' => 'a@b.c']);
    }
}
