<?php

declare(strict_types=1);

namespace Kayrah87\DummyJsonUserConnector\Service;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JsonException;
use Kayrah87\DummyJsonUserConnector\Dto\NewUserInput;
use Kayrah87\DummyJsonUserConnector\Dto\User;
use Kayrah87\DummyJsonUserConnector\Dto\UserPage;
use Kayrah87\DummyJsonUserConnector\Exception\ApiException;
use Kayrah87\DummyJsonUserConnector\Exception\InvalidResponseException;
use Kayrah87\DummyJsonUserConnector\Exception\TransportException;
use Kayrah87\DummyJsonUserConnector\Exception\UserNotFoundException;
use Kayrah87\DummyJsonUserConnector\Exception\ValidationException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Public API for the DummyJSON users endpoints.
 *
 * Exposes three operations — {@see self::getUser()}, {@see self::listUsers()},
 * {@see self::createUser()} — over a PSR-18 HTTP client supplied by the consumer.
 * Framework-agnostic: depends only on PSR interfaces (PSR-18, PSR-17, PSR-3) so
 * it drops into Laravel, Drupal, WordPress, or vanilla PHP without coupling.
 *
 * For zero-config instantiation when a PSR-18 client and PSR-17 factories are
 * already installed in the host app, use {@see self::create()}.
 */
class UserService
{
    /**
     * @param ClientInterface         $client         PSR-18 HTTP client used to dispatch requests.
     * @param RequestFactoryInterface $requestFactory PSR-17 factory for building PSR-7 requests.
     * @param StreamFactoryInterface  $streamFactory  PSR-17 factory for building request body streams.
     * @param string                  $baseUri        Root URL for the DummyJSON API, without trailing slash.
     * @param LoggerInterface         $logger         Optional PSR-3 logger; defaults to a null logger (no output).
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private string $baseUri = 'https://dummyjson.com',
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Construct a service using auto-discovered PSR-18 and PSR-17 implementations.
     *
     * Discovery (via `php-http/discovery`) inspects installed packages — Guzzle,
     * Symfony HTTP Client, Nyholm, etc. — and wires the first match. For
     * deterministic control (e.g. to reuse a client that already has
     * consumer-configured middleware), call the main constructor directly.
     *
     * @throws \Http\Discovery\Exception\NotFoundException If no PSR-18 client or PSR-17 factory is installed.
     */
    public static function create(): self
    {
        return new self(
            client: Psr18ClientDiscovery::find(),
            requestFactory: Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: Psr17FactoryDiscovery::findStreamFactory(),
        );
    }

    /**
     * Retrieve a single user by ID.
     *
     * @param int $id Numeric DummyJSON user identifier.
     *
     * @throws UserNotFoundException    If the API responds with 404 for the given ID.
     * @throws TransportException       If the PSR-18 client fails to complete the request (network, DNS, timeout, etc.).
     * @throws ApiException             For any other non-2xx HTTP status.
     * @throws InvalidResponseException If the response body is not valid JSON, not an object, or does not match the expected user shape.
     */
    public function getUser(int $id): User
    {
        $request = $this->requestFactory->createRequest(
            'GET',
            "{$this->baseUri}/users/{$id}",
        );

        $this->logger->debug('GET /users/{id}', ['id' => $id]);

        $response = $this->send($request);

        if ($response->getStatusCode() === 404) {
            $this->logger->info('User not found', ['id' => $id]);
            throw new UserNotFoundException($id, $response);
        }

        $this->assertSuccessful($response, $request);

        return User::fromApiArray($this->decodeJson($response));
    }

    /**
     * Retrieve a paginated list of users.
     *
     * @param int $limit Maximum users per page. Default 30 matches the DummyJSON API default.
     *                   Pass 0 to request all users (API-specific behaviour).
     * @param int $skip  Offset from the start of the full list, for pagination.
     *
     * @throws TransportException       If the PSR-18 client fails to complete the request.
     * @throws ApiException             For any non-2xx HTTP status.
     * @throws InvalidResponseException If the response body is not valid JSON or the envelope shape is malformed.
     */
    public function listUsers(int $limit = 30, int $skip = 0): UserPage
    {
        $uri = "{$this->baseUri}/users?" . \http_build_query([
            'limit' => $limit,
            'skip' => $skip,
        ]);

        $request = $this->requestFactory->createRequest('GET', $uri);

        $this->logger->debug('GET /users', ['limit' => $limit, 'skip' => $skip]);

        $response = $this->send($request);
        $this->assertSuccessful($response, $request);

        return UserPage::fromApiArray($this->decodeJson($response));
    }

    /**
     * Create a new user.
     *
     * Performs client-side validation via {@see NewUserInput} before sending, so
     * obvious input errors fail fast without a wasted HTTP call. Returns the full
     * {@see User} DTO echoed by the API — the new ID is accessible via `$user->id`.
     *
     * **Simulated endpoint caveat**: DummyJSON's `POST /users/add` does not actually
     * persist the user. The returned `id` is sequential (e.g. `209`) but is not
     * retrievable via a later {@see self::getUser()} call. Callers must not assume
     * round-trip.
     *
     * @param string $firstName User's first name. Trimmed; must be non-empty after trim and contain no control characters.
     * @param string $lastName  User's last name. Same rules as $firstName.
     * @param string $email     Email address. Trimmed; must pass `filter_var(..., FILTER_VALIDATE_EMAIL)`.
     *
     * @throws ValidationException      For empty/whitespace-only fields, control characters, invalid email format, or a 400 from the API.
     * @throws TransportException       If the PSR-18 client fails to complete the request.
     * @throws ApiException             For any other non-2xx HTTP status.
     * @throws InvalidResponseException If the response body is not valid JSON or the user shape is malformed.
     * @throws JsonException            If encoding the request body fails — indicates a bug in {@see NewUserInput::jsonSerialize()}; not expected in practice.
     */
    public function createUser(string $firstName, string $lastName, string $email): User
    {
        $input = new NewUserInput($firstName, $lastName, $email);

        $body = \json_encode($input, JSON_THROW_ON_ERROR);

        $request = $this->requestFactory
            ->createRequest('POST', "{$this->baseUri}/users/add")
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        $this->logger->debug('POST /users/add', [
            'fields' => ['firstName', 'lastName', 'email'],
        ]);

        $response = $this->send($request);

        if ($response->getStatusCode() === 400) {
            throw ValidationException::apiRejected((string) $response->getBody());
        }

        $this->assertSuccessful($response, $request);

        return User::fromApiArray($this->decodeJson($response));
    }

    /**
     * Dispatch a request via the PSR-18 client, mapping transport errors to a domain exception.
     *
     * @throws TransportException If the client raises a PSR-18 {@see ClientExceptionInterface}.
     */
    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Transport failure', [
                'method' => $request->getMethod(),
                'uri' => (string) $request->getUri(),
                'error' => $e->getMessage(),
            ]);

            throw TransportException::forRequest(
                $request->getMethod(),
                (string) $request->getUri(),
                $e,
            );
        }
    }

    /**
     * Guard that a response has a 2xx status; otherwise log and throw.
     *
     * @throws ApiException If the response status is outside the 2xx range.
     */
    private function assertSuccessful(ResponseInterface $response, RequestInterface $request): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }

        $this->logger->warning('API error', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'status' => $status,
            'body' => \substr((string) $response->getBody(), 0, 1024),
        ]);

        throw ApiException::forResponse(
            $status,
            $request->getMethod(),
            (string) $request->getUri(),
            $response,
        );
    }

    /**
     * Decode a JSON response body into an associative array.
     *
     * @return array<string, mixed> The top-level JSON object as an associative array.
     *
     * @throws InvalidResponseException If the body is not valid JSON, or the top-level value is not a JSON object.
     */
    private function decodeJson(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        try {
            $decoded = \json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidResponseException::fromJsonException($e, $response);
        }

        if (!\is_array($decoded)) {
            throw InvalidResponseException::notAnObject($response);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
