<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Exception;

use JsonException;
use Psr\Http\Message\ResponseInterface;

/**
 * Thrown when the API response cannot be decoded or does not match the expected shape.
 *
 * Covers: non-JSON bodies, JSON that decodes to something other than an object,
 * and decoded objects that are missing required fields or have wrong field types.
 * Carries the original response via {@see AbstractResponseException::getResponse()}
 * for debugging.
 */
class InvalidResponseException extends AbstractResponseException
{
    public static function fromJsonException(
        JsonException $previous,
        ?ResponseInterface $response = null,
    ): self {
        return new self('Response body is not valid JSON', $response, $previous);
    }

    public static function notAnObject(?ResponseInterface $response = null): self
    {
        return new self('Decoded JSON is not an object', $response);
    }

    /**
     * Shape-mismatch factory — field is missing or the wrong type.
     *
     * @param string $payloadType Label for the shape being parsed, e.g. "User" or "UserPage".
     * @param string $field       Name of the problem field.
     * @param string $expected    Short phrase describing the mismatch, e.g. "non-integer" or "non-string".
     */
    public static function invalidField(
        string $payloadType,
        string $field,
        string $expected,
        ?ResponseInterface $response = null,
    ): self {
        return new self(
            \sprintf('%s payload: missing or %s "%s"', $payloadType, $expected, $field),
            $response,
        );
    }

    public static function malformed(string $detail, ?ResponseInterface $response = null): self
    {
        return new self($detail, $response);
    }
}
