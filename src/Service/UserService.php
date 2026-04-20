<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Service;

use Kayrah87\DummyJsonUserConnector\Dto\User;
use Kayrah87\DummyJsonUserConnector\Dto\UserPage;

class UserService
{
    public function __construct(
        private string $baseUri = 'https://dummyjson.com',
    ) {
    }

    public static function create(): self
    {
        //TODO: implement function
    }

    public function getUser(int $id): User
    {
        //TODO: implement function
    }

    public function listUsers(int $limit = 30, int $skip = 0): UserPage
    {
        //TODO: implement function
    }

    public function createUser(string $firstName, string $lastName, string $email): User
    {
        //TODO: implement function
    }
}
