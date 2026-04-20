<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * Thrown when input fails validation.
 *
 * Covers both sources:
 * - Client-side pre-flight checks in {@see \Kayrah87\DummyJsonUserConnector\Dto\NewUserInput}
 *   (empty fields, control characters, invalid email format).
 * - Server-side 400 responses surfaced from
 *   {@see \Kayrah87\DummyJsonUserConnector\Service\UserService::createUser()}.
 *
 * Extends {@see InvalidArgumentException} — semantically this is a "caller gave
 * us bad input" error, not a runtime surprise. Does not carry a response.
 */
class ValidationException extends InvalidArgumentException implements DummyJsonException
{
    public function __construct(
        string $message,
        private readonly ?string $field = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Name of the field that failed validation, if known. Null for server-side
     * (API 400) failures where we don't know which specific field was rejected.
     */
    public function getField(): ?string
    {
        return $this->field;
    }

    public static function emptyField(string $field): self
    {
        return new self(\sprintf('%s must not be empty', $field), $field);
    }

    public static function controlCharacters(string $field): self
    {
        return new self(\sprintf('%s contains control characters', $field), $field);
    }

    public static function invalidEmail(): self
    {
        return new self('email is not a valid address', 'email');
    }

    public static function missingField(string $field): self
    {
        return new self(\sprintf('%s must be a non-empty string', $field), $field);
    }

    public static function apiRejected(string $responseBody): self
    {
        return new self(\sprintf('API rejected request: %s', $responseBody));
    }
}
