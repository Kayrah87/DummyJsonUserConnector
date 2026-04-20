<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Dto;

use JsonSerializable;
use Kayrah87\DummyJsonUserConnector\Exception\ValidationException;

/**
 * Validated input DTO for {@see \Kayrah87\DummyJsonUserConnector\Service\UserService::createUser()}.
 *
 * Rules enforced at construction (each throws {@see ValidationException}):
 * - `firstName`, `lastName`, `email`: trimmed and must be non-empty after trim.
 * - All three fields: no control characters (`\x00-\x1F\x7F`) — defends against
 *   null-byte / newline injection into logs, downstream DBs, or the API.
 * - `email`: must pass `filter_var(..., FILTER_VALIDATE_EMAIL)` after trim.
 *
 * Because validation runs in the constructor, any instance is guaranteed to
 * carry clean values — no further checks needed at the call site.
 */
class NewUserInput implements JsonSerializable
{
    /**
     * @throws ValidationException If any field is empty/whitespace-only, contains control characters, or (for email) is not a valid address.
     */
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {
        $this->firstName = self::normaliseString('firstName', $firstName);
        $this->lastName = self::normaliseString('lastName', $lastName);
        $this->email = self::normaliseEmail($email);
    }

    /**
     * Construct from an associative array (e.g. a decoded form submission).
     *
     * @param array<string, mixed> $apiArray
     *
     * @throws ValidationException If any required field is missing, is the wrong type, or fails validation.
     */
    public static function fromApiArray(array $apiArray): self
    {
        foreach (['firstName', 'lastName', 'email'] as $field) {
            if (!isset($apiArray[$field]) || !\is_string($apiArray[$field])) {
                throw ValidationException::missingField($field);
            }
        }

        /** @var string $firstName */
        $firstName = $apiArray['firstName'];
        /** @var string $lastName */
        $lastName = $apiArray['lastName'];
        /** @var string $email */
        $email = $apiArray['email'];

        return new self($firstName, $lastName, $email);
    }

    /**
     * @return array{firstName: string, lastName: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
        ];
    }

    /**
     * @return array{firstName: string, lastName: string, email: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @throws ValidationException
     */
    private static function normaliseString(string $field, string $value): string
    {
        $trimmed = \trim($value);
        if ($trimmed === '') {
            throw ValidationException::emptyField($field);
        }
        if (\preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            throw ValidationException::controlCharacters($field);
        }

        return $trimmed;
    }

    /**
     * @throws ValidationException
     */
    private static function normaliseEmail(string $value): string
    {
        $trimmed = self::normaliseString('email', $value);
        if (\filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::invalidEmail();
        }

        return $trimmed;
    }
}
