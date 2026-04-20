<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Base class for exceptions that carry the offending HTTP response.
 *
 * Provides typed access to both the PSR-7 {@see ResponseInterface} via
 * {@see self::getResponse()} and a pre-read body string via
 * {@see self::getResponseBody()}.
 *
 * The body is read once at construction because {@see \Psr\Http\Message\StreamInterface}
 * is not guaranteed to be rewindable — consumers who catch this exception may
 * need the body, and we can't rely on being able to read the stream later.
 */
abstract class AbstractResponseException extends RuntimeException implements DummyJsonException
{
    private readonly ?string $responseBody;

    public function __construct(
        string $message,
        private readonly ?ResponseInterface $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);

        $this->responseBody = $response !== null ? (string) $response->getBody() : null;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
