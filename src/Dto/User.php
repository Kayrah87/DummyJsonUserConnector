<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Dto;

use JsonSerializable;
use Kayrah87\DummyJsonUserConnector\Exception\InvalidResponseException;

/**
 * Representation of a DummyJSON user.
 *
 * Carries only the four fields relevant to this package — id, firstName,
 * lastName, email — and ignores the rest of the DummyJSON payload (age,
 * phone, password, etc.).
 *
 * Field naming matches the source API (camelCase). Consumers that need
 * snake_case or other conventions are responsible for translating into
 * their own stack.
 */
class User implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {
    }

    /**
     * Construct a User from a decoded DummyJSON API payload.
     *
     * Extra fields in $apiArray (age, phone, password, etc.) are silently ignored.
     *
     * @param array<string, mixed> $apiArray Raw decoded JSON payload.
     *
     * @throws InvalidResponseException If any required field is missing or has the wrong type.
     */
    public static function fromApiArray(array $apiArray): self
    {
        if (!isset($apiArray['id']) || !\is_int($apiArray['id'])) {
            throw InvalidResponseException::invalidField('User', 'id', 'non-integer');
        }
        if (!isset($apiArray['firstName']) || !\is_string($apiArray['firstName'])) {
            throw InvalidResponseException::invalidField('User', 'firstName', 'non-string');
        }
        if (!isset($apiArray['lastName']) || !\is_string($apiArray['lastName'])) {
            throw InvalidResponseException::invalidField('User', 'lastName', 'non-string');
        }
        if (!isset($apiArray['email']) || !\is_string($apiArray['email'])) {
            throw InvalidResponseException::invalidField('User', 'email', 'non-string');
        }

        return new self(
            id: $apiArray['id'],
            firstName: $apiArray['firstName'],
            lastName: $apiArray['lastName'],
            email: $apiArray['email'],
        );
    }

    /**
     * @return array{id: int, firstName: string, lastName: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
        ];
    }

    /**
     * @return array{id: int, firstName: string, lastName: string, email: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
