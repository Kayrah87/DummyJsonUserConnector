<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Dto;

use JsonSerializable;

class NewUserInput implements JsonSerializable
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
    ) {
        // construct
    }

    public function jsonSerialize(): array
    {
        //Serialize
    }

    public static function fromApiArray(array $apiArray): self
    {
        //Implement
    }
}
