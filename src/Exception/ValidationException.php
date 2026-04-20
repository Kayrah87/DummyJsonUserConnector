<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Exception;

use InvalidArgumentException;

class ValidationException extends InvalidArgumentException implements DummyJsonException
{
  
}
