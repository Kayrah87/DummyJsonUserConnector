<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Integration\Service;

use GuzzleHttp\Client as GuzzleClient;
use Kayrah87\DummyJsonUserConnector\Dto\User;
use Kayrah87\DummyJsonUserConnector\Dto\UserPage;
use Kayrah87\DummyJsonUserConnector\Exception\UserNotFoundException;
use Kayrah87\DummyJsonUserConnector\Service\UserService;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests against the real DummyJSON API at https://dummyjson.com.
 *
 * Opt-in suite — only runs via `composer test:integration`, never on the
 * default `composer test` pass. Requires network access.
 *
 * Scope is intentionally narrow: one test per public service method plus one
 * 404 path. This verifies wire-format assumptions (field names, envelope shape,
 * status codes) without trying to cover every branch — unit tests already do that.
 */
#[Group('integration')]
final class UserServiceIntegrationTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        $psr17 = new Psr17Factory();

        $this->service = new UserService(
            client: new GuzzleClient([
                'timeout' => 10,
                'connect_timeout' => 5,
                'http_errors' => false,
            ]),
            requestFactory: $psr17,
            streamFactory: $psr17,
        );
    }

    #[Test]
    public function getUserReturnsUserOneFromLiveApi(): void
    {
        $user = $this->service->getUser(1);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(1, $user->id);
        self::assertNotSame('', $user->firstName);
        self::assertNotSame('', $user->lastName);
        self::assertMatchesRegularExpression('/^[^@\s]+@[^@\s]+$/', $user->email);
    }

    #[Test]
    public function listUsersReturnsPaginatedPageFromLiveApi(): void
    {
        $page = $this->service->listUsers(limit: 5, skip: 0);

        self::assertInstanceOf(UserPage::class, $page);
        self::assertCount(5, $page);
        self::assertSame(0, $page->skip);
        self::assertSame(5, $page->limit);
        self::assertGreaterThan(5, $page->total);

        foreach ($page as $user) {
            self::assertInstanceOf(User::class, $user);
            self::assertGreaterThan(0, $user->id);
        }
    }

    #[Test]
    public function createUserPostsToSimulatedEndpointAndReturnsNewId(): void
    {
        $user = $this->service->createUser(
            firstName: 'Integration',
            lastName: 'Test',
            email: 'integration-test@example.com',
        );

        self::assertInstanceOf(User::class, $user);
        self::assertGreaterThan(0, $user->id);
        self::assertSame('Integration', $user->firstName);
        self::assertSame('Test', $user->lastName);
        self::assertSame('integration-test@example.com', $user->email);
    }

    #[Test]
    public function getUserThrowsUserNotFoundForUnknownIdOnLiveApi(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->service->getUser(999_999_999);
    }
}
