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

    public function __construct(protected Client $client)
    {
    }

    /**
     * Get adapter, creating a default cURL adapter when none is set.
     */
    public function getAdapter(): AdapterInterface
    {
        if (! $this->adapter) {
            $this->setAdapter(new DefaultAdapter($this->client->getSslVerify()));
        }

        return $this->adapter;
    }

    public function setAdapter(AdapterInterface $adapter): static
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function sendRequest(string $address, string $method = self::METHOD_GET, array|object|null $body = null): array
    {
        $response = $this->getJsonResponse($address, $method, $body);

        if (! is_object($response)) {
            throw new ConnectionException('Unexpected response from bridge');
        }

        // CLIP v2 envelope: { "errors": [...], "data": [...] }
        if (isset($response->errors) && is_array($response->errors) && count($response->errors) > 0) {
            $descriptions = array_map(
                static fn ($error) => is_object($error) && isset($error->description)
                    ? (string) $error->description
                    : 'Unknown error',
                $response->errors
            );

            throw new HueException(implode('; ', $descriptions));
        }

        if (isset($response->data) && is_array($response->data)) {
            return $response->data;
        }

        return [];
    }

    /**
     * @inheritdoc
     */
    public function sendRaw(string $address, string $method = self::METHOD_GET, array|object|null $body = null): mixed
    {
        $url = $this->buildRequestUrl($address);

        $this->getAdapter()->open();
        $results = $this->getAdapter()->send(
            $url,
            $method,
            $this->encodeBody($body),
            $this->buildHeaders()
        );
        $this->getAdapter()->close();

        return json_decode((string) $results);
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

        $this->getAdapter()->open();
        $results = $this->getAdapter()->send(
            $url,
            $method,
            $this->encodeBody($body),
            $this->buildHeaders()
        );
        $status = $this->getAdapter()->getHttpStatusCode();
        $this->getAdapter()->close();

        if ($status === 0) {
            throw new ConnectionException('Could not connect to the Hue bridge');
        }

        $decoded = json_decode((string) $results);

        // Map non success HTTP statuses to dedicated exceptions.
        if ($status < 200 || $status >= 300) {
            $description = $this->extractErrorDescription($decoded) ?? "HTTP {$status}";
            $exceptionClass = static::$statusExceptionMap[$status] ?? HueException::class;

            throw new $exceptionClass($description, $status);
        }

        return $decoded;
    }

    /**
     * Pull a human readable error description out of a decoded error body.
     */
    protected function extractErrorDescription(mixed $decoded): ?string
    {
        if (is_object($decoded) && isset($decoded->errors) && is_array($decoded->errors)) {
            $descriptions = [];
            foreach ($decoded->errors as $error) {
                if (is_object($error) && isset($error->description)) {
                    $descriptions[] = (string) $error->description;
                }
            }

            if ($descriptions) {
                return implode('; ', $descriptions);
            }
        }

        return null;
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

        return (string) json_encode($body);
    }

    protected function buildRequestUrl(string $address): string
    {
        $host = rtrim($this->client->getHost(), '/');

        if (preg_match('#^https?://#i', $host) === 1) {
            return "{$host}{$address}";
        }

        return "https://{$host}{$address}";
    }
}
