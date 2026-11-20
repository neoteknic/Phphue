<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Transport;

use Phphue\Client;
use Phphue\Transport\Adapter\AdapterInterface;
use Phphue\Transport\Adapter\Curl as DefaultAdapter;
use Phphue\Transport\Exception\BridgeBusyException;
use Phphue\Transport\Exception\ConnectionException;
use Phphue\Transport\Exception\ForbiddenException;
use Phphue\Transport\Exception\HueException;
use Phphue\Transport\Exception\NotFoundException;
use Phphue\Transport\Exception\RateLimitException;
use Phphue\Transport\Exception\UnauthorizedException;

/**
 * Http transport for the CLIP v2 API.
 */
class Http implements TransportInterface
{
    protected ?AdapterInterface $adapter = null;

    protected bool $throwOnWarnings = false;

    /**
     * Descriptions of the warnings returned by the most recent request.
     *
     * @var array<int,string>
     */
    protected array $lastWarnings = [];

    /**
     * @var (callable(array<int,string>):void)|null
     */
    protected $warningHandler = null;

    /**
     * Map of HTTP status codes to dedicated exceptions.
     *
     * @var array<int,class-string<HueException>>
     */
    public static array $statusExceptionMap = [
        401 => UnauthorizedException::class,
        403 => ForbiddenException::class,
        404 => NotFoundException::class,
        429 => RateLimitException::class,
        503 => BridgeBusyException::class,
    ];

    public function __construct(protected readonly Client $client)
    {
    }

    /**
     * Get adapter, creating a default cURL adapter when none is set.
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter ??= new DefaultAdapter($this->client->getSslVerify());
    }

    public function setAdapter(AdapterInterface $adapter): static
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function setSslVerify(bool $sslVerify): void
    {
        if ($this->adapter instanceof DefaultAdapter) {
            $this->adapter->setSslVerify($sslVerify);
        } elseif ($this->adapter !== null) {
            throw new \LogicException('Configure TLS verification on the custom adapter directly');
        }
    }

    #[\Override]
    public function sendRequest(string $address, string $method = self::METHOD_GET, array|object|null $body = null): array
    {
        $this->lastWarnings = [];

        $response = $this->getJsonResponse($address, $method, $body);

        if (! is_object($response)
            || ! isset($response->errors, $response->data)
            || ! is_array($response->errors) || ! array_is_list($response->errors)
            || ! is_array($response->data) || ! array_is_list($response->data)
        ) {
            throw new ConnectionException('Invalid CLIP v2 response envelope from bridge');
        }

        foreach ([$response->errors, $response->data] as $items) {
            foreach ($items as $item) {
                if (! $item instanceof \stdClass) {
                    throw new ConnectionException('Invalid CLIP v2 response item from bridge');
                }
            }
        }

        // Errors alongside returned data can describe a partial success.
        // Errors with no data must never be reported as a successful command.
        if ($response->errors !== []) {
            $warnings = $this->describeErrors($response->errors);

            if ($warnings === []) {
                $warnings = ['Unknown error'];
            }

            $this->lastWarnings = $warnings;

            if ($this->throwOnWarnings || $response->data === []) {
                throw new HueException(implode('; ', $warnings));
            }

            if ($this->warningHandler !== null) {
                ($this->warningHandler)($warnings);
            }
        }

        return $response->data;
    }

    #[\Override]
    public function setThrowOnWarnings(bool $throw): static
    {
        $this->throwOnWarnings = $throw;

        return $this;
    }

    /**
     * @return array<int,string>
     */
    #[\Override]
    public function getLastWarnings(): array
    {
        return $this->lastWarnings;
    }

    #[\Override]
    public function setWarningHandler(?callable $handler): static
    {
        $this->warningHandler = $handler;

        return $this;
    }

    #[\Override]
    public function sendRaw(string $address, string $method = self::METHOD_GET, array|object|null $body = null): mixed
    {
        $this->lastWarnings = [];

        return $this->getJsonResponse($address, $method, $body);
    }

    /**
     * Send a request, validate the HTTP status and decode the JSON body.
     *
     * @param array<string,mixed>|object|null $body
     *
     * @throws HueException
     */
    protected function getJsonResponse(string $address, string $method, array|object|null $body): mixed
    {
        $url = $this->buildRequestUrl($address);
        $encodedBody = $this->encodeBody($body);
        $adapter = $this->getAdapter();

        $adapter->open();
        try {
            $results = $adapter->send($url, $method, $encodedBody, $this->buildHeaders());
            $status = $adapter->getHttpStatusCode();
        } finally {
            $adapter->close();
        }

        if ($results === false || $status === 0) {
            throw new ConnectionException('Could not connect to the Hue bridge');
        }

        $decoded = json_decode((string) $results);

        // Map non success HTTP statuses to dedicated exceptions.
        if ($status < 200 || $status >= 300) {
            $description = $this->extractErrorDescription($decoded) ?? "HTTP {$status}";
            $exceptionClass = static::$statusExceptionMap[$status] ?? HueException::class;

            throw new $exceptionClass($description, $status);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConnectionException('Invalid JSON response from bridge: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Pull a human readable error description out of a decoded error body.
     */
    protected function extractErrorDescription(mixed $decoded): ?string
    {
        if (! is_object($decoded)) {
            return null;
        }

        $descriptions = $this->describeErrors($decoded->errors ?? null);

        return $descriptions === [] ? null : implode('; ', $descriptions);
    }

    /**
     * Collect the human readable descriptions from a CLIP v2 `errors` array.
     *
     * @return array<int,string>
     */
    protected function describeErrors(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $descriptions = [];

        foreach ($errors as $error) {
            if (is_object($error) && isset($error->description) && is_scalar($error->description)) {
                $descriptions[] = (string) $error->description;
            }
        }

        return $descriptions;
    }

    /**
     * @return array<int,string>
     */
    protected function buildHeaders(): array
    {
        $headers = [];

        if ($this->client->getApplicationKey() !== null) {
            $headers[] = 'hue-application-key: ' . $this->client->getApplicationKey();
        }

        return $headers;
    }

    /**
     * @param array<string,mixed>|object|null $body
     */
    protected function encodeBody(array|object|null $body): ?string
    {
        if ($body === null) {
            return null;
        }

        return json_encode($body, JSON_THROW_ON_ERROR);
    }

    protected function buildRequestUrl(string $address): string
    {
        return $this->client->getBaseUrl() . $address;
    }
}
