<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Dto;

use Kayrah87\DummyJsonUserConnector\Dto\User;
use Kayrah87\DummyJsonUserConnector\Dto\UserPage;
use Kayrah87\DummyJsonUserConnector\Exception\InvalidResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserPageTest extends TestCase
{
    #[Test]
    public function constructorAssignsProperties(): void
    {
        $users = [new User(1, 'A', 'B', 'a@b.c')];
        $page = new UserPage(items: $users, total: 100, skip: 0, limit: 30);

        self::assertSame($users, $page->items);
        self::assertSame(100, $page->total);
        self::assertSame(0, $page->skip);
        self::assertSame(30, $page->limit);
    }

    #[Test]
    public function fromApiArrayBuildsPageFromValidEnvelope(): void
    {
        $page = UserPage::fromApiArray([
            'users' => [
                ['id' => 1, 'firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.c'],
                ['id' => 2, 'firstName' => 'C', 'lastName' => 'D', 'email' => 'c@d.e'],
            ],
            'total' => 100,
            'skip' => 0,
            'limit' => 30,
        ]);

        self::assertSame(100, $page->total);
        self::assertSame(0, $page->skip);
        self::assertSame(30, $page->limit);
        self::assertCount(2, $page->items);
        self::assertContainsOnlyInstancesOf(User::class, $page->items);
        self::assertSame(1, $page->items[0]->id);
        self::assertSame(2, $page->items[1]->id);
    }

    #[Test]
    public function fromApiArrayAcceptsEmptyUsersList(): void
    {
        $page = UserPage::fromApiArray([
            'users' => [],
            'total' => 0,
            'skip' => 0,
            'limit' => 30,
        ]);

        self::assertSame([], $page->items);
        self::assertSame(0, $page->total);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[Test]
    #[DataProvider('invalidEnvelopes')]
    public function fromApiArrayThrowsInvalidResponseOnBadEnvelope(array $payload): void
    {
        $this->expectException(InvalidResponseException::class);

        UserPage::fromApiArray($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidEnvelopes(): iterable
    {
        $valid = [
            'users' => [],
            'total' => 0,
            'skip' => 0,
            'limit' => 30,
        ];

        yield 'missing users' => [array_diff_key($valid, ['users' => null])];
        yield 'missing total' => [array_diff_key($valid, ['total' => null])];
        yield 'missing skip' => [array_diff_key($valid, ['skip' => null])];
        yield 'missing limit' => [array_diff_key($valid, ['limit' => null])];
        yield 'users not array' => [[...$valid, 'users' => 'oops']];
        yield 'non-integer total' => [[...$valid, 'total' => '100']];
        yield 'user entry not array' => [[...$valid, 'users' => ['not-a-map']]];
    }

    #[Test]
    public function toArrayEmitsUsersKeyNotItems(): void
    {
        $page = new UserPage(
            items: [new User(1, 'A', 'B', 'a@b.c')],
            total: 1,
            skip: 0,
            limit: 30,
        );

        $array = $page->toArray();

        self::assertArrayHasKey('users', $array);
        self::assertArrayNotHasKey('items', $array);
        self::assertSame(1, $array['total']);
        self::assertSame(0, $array['skip']);
        self::assertSame(30, $array['limit']);
    }

    #[Test]
    public function jsonSerializeMatchesToArray(): void
    {
        $page = new UserPage([new User(1, 'A', 'B', 'a@b.c')], 1, 0, 30);

        self::assertSame($page->toArray(), $page->jsonSerialize());
    }

    #[Test]
    public function jsonEncodeUsesApiEnvelopeShape(): void
    {
        $page = new UserPage([new User(1, 'A', 'B', 'a@b.c')], 1, 0, 30);

        $decoded = json_decode(
            json_encode($page, JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);
        self::assertArrayHasKey('users', $decoded);
        self::assertArrayHasKey('total', $decoded);
        self::assertArrayHasKey('skip', $decoded);
        self::assertArrayHasKey('limit', $decoded);
    }

    #[Test]
    public function countReturnsNumberOfItems(): void
    {
        $page = new UserPage(
            items: [
                new User(1, 'A', 'B', 'a@b.c'),
                new User(2, 'C', 'D', 'c@d.e'),
            ],
            total: 100,
            skip: 0,
            limit: 30,
        );

        self::assertCount(2, $page);
    }

    #[Test]
    public function iteratesOverItemsInOrder(): void
    {
        $users = [
            new User(1, 'A', 'B', 'a@b.c'),
            new User(2, 'C', 'D', 'c@d.e'),
        ];
        $page = new UserPage($users, 2, 0, 30);

        $collected = [];
        foreach ($page as $user) {
            $collected[] = $user;
        }

        self::assertSame($users, $collected);
    }
}
