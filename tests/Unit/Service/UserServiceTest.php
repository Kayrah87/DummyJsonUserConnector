<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Tests\Unit\Service;

use Http\Mock\Client as MockClient;
use Kayrah87\DummyJsonUserConnector\Dto\User;
use Kayrah87\DummyJsonUserConnector\Dto\UserPage;
use Kayrah87\DummyJsonUserConnector\Exception\ValidationException;
use Kayrah87\DummyJsonUserConnector\Service\UserService;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class UserServiceTest extends TestCase
{
    private const BASE_URI = 'https://api.test';

    private Psr17Factory $psr17;
    private MockClient $mockClient;
    private UserService $service;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->mockClient = new MockClient();
        $this->service = new UserService(
            client: $this->mockClient,
            requestFactory: $this->psr17,
            streamFactory: $this->psr17,
            baseUri: self::BASE_URI,
        );
    }

    #[Test]
    public function getUserReturnsUserDtoOnSuccess(): void
    {
        $this->queueJsonResponse(200, [
            'id' => 1,
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'email' => 'alice@example.com',
        ]);

        $user = $this->service->getUser(1);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(1, $user->id);
        self::assertSame('Alice', $user->firstName);
        self::assertSame('Smith', $user->lastName);
        self::assertSame('alice@example.com', $user->email);
    }

    #[Test]
    public function getUserSendsGetToCorrectUrl(): void
    {
        $this->queueJsonResponse(200, [
            'id' => 5,
            'firstName' => 'A',
            'lastName' => 'B',
            'email' => 'a@b.c',
        ]);

        $this->service->getUser(5);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::BASE_URI . '/users/5', (string) $request->getUri());
    }

    #[Test]
    public function listUsersReturnsUserPageOnSuccess(): void
    {
        $this->queueJsonResponse(200, [
            'users' => [
                ['id' => 1, 'firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.c'],
                ['id' => 2, 'firstName' => 'C', 'lastName' => 'D', 'email' => 'c@d.e'],
            ],
            'total' => 100,
            'skip' => 0,
            'limit' => 30,
        ]);

        $page = $this->service->listUsers();

        self::assertInstanceOf(UserPage::class, $page);
        self::assertSame(100, $page->total);
        self::assertSame(0, $page->skip);
        self::assertSame(30, $page->limit);
        self::assertCount(2, $page->items);
    }

    #[Test]
    public function listUsersAppliesDefaultLimitAndSkip(): void
    {
        $this->queueJsonResponse(200, [
            'users' => [],
            'total' => 0,
            'skip' => 0,
            'limit' => 30,
        ]);

        $this->service->listUsers();

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/users', $request->getUri()->getPath());

        $query = $this->parseQuery($request);
        self::assertSame('30', $query['limit'] ?? null);
        self::assertSame('0', $query['skip'] ?? null);
    }

    #[Test]
    public function listUsersAppliesCustomLimitAndSkip(): void
    {
        $this->queueJsonResponse(200, [
            'users' => [],
            'total' => 0,
            'skip' => 20,
            'limit' => 10,
        ]);

        $this->service->listUsers(limit: 10, skip: 20);

        $query = $this->parseQuery($this->lastRequest());
        self::assertSame('10', $query['limit'] ?? null);
        self::assertSame('20', $query['skip'] ?? null);
    }

    #[Test]
    public function createUserSendsPostWithCamelCaseJsonBody(): void
    {
        $this->queueJsonResponse(201, [
            'id' => 209,
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'email' => 'alice@example.com',
        ]);

        $this->service->createUser('Alice', 'Smith', 'alice@example.com');

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE_URI . '/users/add', (string) $request->getUri());
        self::assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));

        self::assertSame(
            [
                'firstName' => 'Alice',
                'lastName' => 'Smith',
                'email' => 'alice@example.com',
            ],
            $this->decodeJsonBody($request),
        );
    }

    #[Test]
    public function createUserReturnsUserDtoWithIdFromResponse(): void
    {
        $this->queueJsonResponse(201, [
            'id' => 209,
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'email' => 'alice@example.com',
        ]);

        $user = $this->service->createUser('Alice', 'Smith', 'alice@example.com');

        self::assertInstanceOf(User::class, $user);
        self::assertSame(209, $user->id);
        self::assertSame('Alice', $user->firstName);
    }

    #[Test]
    public function createUserTrimsInputBeforeSending(): void
    {
        $this->queueJsonResponse(201, [
            'id' => 210,
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'email' => 'alice@example.com',
        ]);

        $this->service->createUser('  Alice  ', "\tSmith\n", '  alice@example.com  ');

        $body = $this->decodeJsonBody($this->lastRequest());
        self::assertSame('Alice', $body['firstName']);
        self::assertSame('Smith', $body['lastName']);
        self::assertSame('alice@example.com', $body['email']);
    }

    #[Test]
    public function createUserThrowsValidationExceptionOnEmptyFirstName(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createUser('', 'Smith', 'alice@example.com');
    }

    #[Test]
    public function createUserThrowsValidationExceptionOnWhitespaceOnlyLastName(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createUser('Alice', '   ', 'alice@example.com');
    }

    #[Test]
    public function createUserThrowsValidationExceptionOnEmptyEmail(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createUser('Alice', 'Smith', '');
    }

    #[Test]
    public function createUserThrowsValidationExceptionOnInvalidEmail(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createUser('Alice', 'Smith', 'not-an-email');
    }

    #[Test]
    public function createUserThrowsValidationExceptionOnControlCharactersInName(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createUser("Alice\x00injected", 'Smith', 'alice@example.com');
    }

    #[Test]
    public function createUserDoesNotSendRequestWhenValidationFails(): void
    {
        try {
            $this->service->createUser('', 'Smith', 'alice@example.com');
        } catch (ValidationException) {
            // expected
        }

        self::assertSame([], $this->mockClient->getRequests());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function queueJsonResponse(int $status, array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->mockClient->addResponse(
            new Response($status, ['Content-Type' => 'application/json'], $body),
        );
    }

    private function lastRequest(): RequestInterface
    {
        $requests = $this->mockClient->getRequests();
        self::assertNotEmpty($requests, 'expected at least one request to have been sent');

        return end($requests);
    }

    /**
     * @return array<string, string>
     */
    private function parseQuery(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonBody(RequestInterface $request): array
    {
        $decoded = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
