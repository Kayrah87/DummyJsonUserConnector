<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Exception;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Thrown when the API returns 404 for a `GET /users/{id}` request.
 *
 * Carries the looked-up user ID in a typed property so consumers can branch
 * on `$e->getUserId()` without re-parsing the exception message. The 404
 * response is preserved via {@see AbstractResponseException::getResponse()}.
 */
class UserNotFoundException extends AbstractResponseException
{
    public function __construct(
        private readonly int $userId,
        ?ResponseInterface $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct("User not found: id={$userId}", $response, $previous);
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
