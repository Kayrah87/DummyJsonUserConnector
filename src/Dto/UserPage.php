<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Dto;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Kayrah87\DummyJsonUserConnector\Exception\InvalidResponseException;
use Traversable;

/**
 * Paginated page of {@see User} DTOs mirroring DummyJSON's
 * `{ users, total, skip, limit }` envelope.
 *
 * Implements {@see Countable} and {@see IteratorAggregate} for ergonomic
 * consumption: `count($page)` returns the number of items on this page, and
 * `foreach ($page as $user)` yields each {@see User} in order.
 *
 * When serialised to JSON, the page uses the API's envelope key `users`
 * (not the PHP-side property name `items`) so the output round-trips against
 * DummyJSON's wire shape.
 *
 * @implements IteratorAggregate<int, User>
 */
class UserPage implements JsonSerializable, Countable, IteratorAggregate
{
    /**
     * @param list<User> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $skip,
        public int $limit,
    ) {
    }

    /**
     * Construct a UserPage from a decoded DummyJSON list response.
     *
     * @param array<string, mixed> $apiArray Raw decoded JSON envelope: `{ users, total, skip, limit }`.
     *
     * @throws InvalidResponseException If the envelope is missing fields, types are wrong, or any user entry is malformed.
     */
    public static function fromApiArray(array $apiArray): self
    {
        if (!isset($apiArray['users']) || !\is_array($apiArray['users'])) {
            throw InvalidResponseException::invalidField('UserPage', 'users', 'non-array');
        }
        if (!isset($apiArray['total']) || !\is_int($apiArray['total'])) {
            throw InvalidResponseException::invalidField('UserPage', 'total', 'non-integer');
        }
        if (!isset($apiArray['skip']) || !\is_int($apiArray['skip'])) {
            throw InvalidResponseException::invalidField('UserPage', 'skip', 'non-integer');
        }
        if (!isset($apiArray['limit']) || !\is_int($apiArray['limit'])) {
            throw InvalidResponseException::invalidField('UserPage', 'limit', 'non-integer');
        }

        $items = [];
        foreach ($apiArray['users'] as $rawUser) {
            if (!\is_array($rawUser)) {
                throw InvalidResponseException::malformed('UserPage payload: "users" contains a non-array entry');
            }
            /** @var array<string, mixed> $rawUser */
            $items[] = User::fromApiArray($rawUser);
        }

        return new self(
            items: $items,
            total: $apiArray['total'],
            skip: $apiArray['skip'],
            limit: $apiArray['limit'],
        );
    }

    /**
     * @return array{users: list<array{id: int, firstName: string, lastName: string, email: string}>, total: int, skip: int, limit: int}
     */
    public function toArray(): array
    {
        return [
            'users' => \array_map(static fn (User $u): array => $u->toArray(), $this->items),
            'total' => $this->total,
            'skip' => $this->skip,
            'limit' => $this->limit,
        ];
    }

    /**
     * @return array{users: list<array{id: int, firstName: string, lastName: string, email: string}>, total: int, skip: int, limit: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * @return Traversable<int, User>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
