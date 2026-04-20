<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Dto;

use JsonSerializable;

class User implements JsonSerializable
{
    public function __construct(
        public int $id,
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
}
