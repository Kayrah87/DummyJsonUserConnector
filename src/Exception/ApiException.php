<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Exception;

use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Catch-all for non-2xx API responses that aren't otherwise classified
 * (e.g. not a 404, not a 400 validation failure).
 *
 * Carries the HTTP status code in addition to the full response via
 * {@see AbstractResponseException::getResponse()}.
 */
class ApiException extends AbstractResponseException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        ?ResponseInterface $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function forResponse(
        int $statusCode,
        string $method,
        string $uri,
        ResponseInterface $response,
    ): self {
        return new self(
            \sprintf('API returned status %d for %s %s', $statusCode, $method, $uri),
            $statusCode,
            $response,
        );
    }
}
