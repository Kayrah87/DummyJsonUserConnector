<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * Thrown when the PSR-18 client fails to complete the request — network error,
 * DNS failure, connection timeout, TLS handshake failure, etc.
 *
 * The underlying {@see ClientExceptionInterface} is chained as the previous
 * exception, so standard tooling (loggers, Sentry) picks up the full error
 * chain via `getPrevious()`.
 *
 * Carries the request method and URI that failed to dispatch. Does not carry
 * a response — there is no response when the transport itself fails.
 */
class TransportException extends RuntimeException implements DummyJsonException
{
    public function __construct(
        string $message,
        private readonly string $requestMethod,
        private readonly string $requestUri,
        ?ClientExceptionInterface $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    public function getRequestUri(): string
    {
        return $this->requestUri;
    }

    public static function forRequest(
        string $method,
        string $uri,
        ClientExceptionInterface $previous,
    ): self {
        return new self(
            \sprintf('Transport error during %s %s: %s', $method, $uri, $previous->getMessage()),
            $method,
            $uri,
            $previous,
        );
    }
}
