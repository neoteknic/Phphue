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

    #[\Override]
    public function sendRequest(string $address, string $method = self::METHOD_GET, array|object|null $body = null): array
    {
        $this->lastWarnings = [];

        $response = $this->getJsonResponse($address, $method, $body);

        if (! is_object($response)) {
            throw new ConnectionException('Unexpected response from bridge');
        }

        // CLIP v2 envelope: { "errors": [...], "data": [...] }.
        // getJsonResponse() already turned every non 2xx status into an
        // exception, so any envelope errors reaching this point came back with
        // a 2xx status: the bridge accepted the request but is flagging a soft
        // issue (e.g. a Zigbee device that "may not have received" the command).
        // These are warnings - they do not block unless explicitly requested.
        if (isset($response->errors) && is_array($response->errors) && count($response->errors) > 0) {
            $warnings = $this->describeErrors($response->errors);

            if ($warnings === []) {
                $warnings = ['Unknown error'];
            }

            if ($this->throwOnWarnings) {
                throw new HueException(implode('; ', $warnings));
            }

            $this->lastWarnings = $warnings;

            if ($this->warningHandler !== null) {
                ($this->warningHandler)($warnings);
            }
        }

        if (isset($response->data) && is_array($response->data)) {
            return $response->data;
        }

        return [];
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
            if (is_object($error) && isset($error->description)) {
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
