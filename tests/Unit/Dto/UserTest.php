<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Dto;

use Kayrah87\DummyJsonUserConnector\Dto\User;
use Kayrah87\DummyJsonUserConnector\Exception\InvalidResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    #[Test]
    public function constructorAssignsProperties(): void
    {
        $user = new User(1, 'Alice', 'Smith', 'alice@example.com');

        self::assertSame(1, $user->id);
        self::assertSame('Alice', $user->firstName);
        self::assertSame('Smith', $user->lastName);
        self::assertSame('alice@example.com', $user->email);
    }

    #[Test]
    public function fromApiArrayBuildsUserFromValidPayload(): void
    {
        $user = User::fromApiArray([
            'id' => 1,
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'email' => 'alice@example.com',
        ]);

        self::assertSame(1, $user->id);
        self::assertSame('Alice', $user->firstName);
        self::assertSame('Smith', $user->lastName);
        self::assertSame('alice@example.com', $user->email);
    }

    #[Test]
    public function fromApiArrayIgnoresExtraApiFields(): void
    {
        $user = User::fromApiArray([
            'id' => 1,
            'firstName' => 'A',
            'lastName' => 'B',
            'email' => 'a@b.c',
            'age' => 30,
            'phone' => '+1234567890',
            'password' => 'should-be-ignored',
        ]);

        self::assertSame(1, $user->id);
        self::assertSame('A', $user->firstName);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Test]
    #[DataProvider('invalidPayloads')]
    public function fromApiArrayThrowsInvalidResponseOnBadShape(array $payload): void
    {
        $this->expectException(InvalidResponseException::class);

        User::fromApiArray($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): iterable
    {
        $valid = [
            'id' => 1,
            'firstName' => 'A',
            'lastName' => 'B',
            'email' => 'a@b.c',
        ];

        yield 'missing id' => [array_diff_key($valid, ['id' => null])];
        yield 'missing firstName' => [array_diff_key($valid, ['firstName' => null])];
        yield 'missing lastName' => [array_diff_key($valid, ['lastName' => null])];
        yield 'missing email' => [array_diff_key($valid, ['email' => null])];
        yield 'non-integer id' => [[...$valid, 'id' => 'one']];
        yield 'non-string firstName' => [[...$valid, 'firstName' => 42]];
        yield 'non-string lastName' => [[...$valid, 'lastName' => true]];
        yield 'null email' => [[...$valid, 'email' => null]];
    }

    #[Test]
    public function toArrayEmitsCamelCaseKeys(): void
    {
        $user = new User(1, 'Alice', 'Smith', 'alice@example.com');

        self::assertSame(
            [
                'id' => 1,
                'firstName' => 'Alice',
                'lastName' => 'Smith',
                'email' => 'alice@example.com',
            ],
            $user->toArray(),
        );
    }

    #[Test]
    public function jsonSerializeMatchesToArray(): void
    {
        $user = new User(1, 'Alice', 'Smith', 'alice@example.com');

        self::assertSame($user->toArray(), $user->jsonSerialize());
    }

    #[Test]
    public function jsonEncodeProducesCamelCaseJson(): void
    {
        $user = new User(1, 'Alice', 'Smith', 'alice@example.com');

        $json = json_encode($user, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(
            [
                'id' => 1,
                'firstName' => 'Alice',
                'lastName' => 'Smith',
                'email' => 'alice@example.com',
            ],
            $decoded,
        );
    }
}
