<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Dto;

use JsonSerializable;

class UserPage implements JsonSerializable
{
    public function __construct(
        public array $items,
        public int $total,
        public int $skip,
        public int $limit,
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
