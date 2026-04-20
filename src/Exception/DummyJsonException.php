<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Exception;

/**
 * Marker interface implemented by every exception thrown from this package.
 *
 * Consumers can `catch (DummyJsonException $e)` to trap any failure from
 * the connector without being tied to a specific concrete class.
 */
interface DummyJsonException
{
}
