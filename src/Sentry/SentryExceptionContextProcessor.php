<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Sentry;

use Druidvav\SentryExtensionBundle\Contract\SentryAwareException;
use GuzzleHttp\Exception\RequestException;
use Sentry\Event;
use Sentry\EventHint;
use Throwable;

class SentryExceptionContextProcessor
{
    private const BODY_SIZE_LIMIT = 1024;

    private bool $guzzleAvailable;

    public function __construct()
    {
        $this->guzzleAvailable = class_exists(RequestException::class);
    }

    public function __invoke(Event $event, EventHint $hint): ?Event
    {
        if ($hint->exception === null) {
            return $event;
        }

        $extra = $event->getExtra();
        $this->collectFromChain($hint->exception, $extra);

        if ($extra !== []) {
            $event->setExtra($extra);
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function collectFromChain(Throwable $exception, array &$extra): void
    {
        if ($exception instanceof SentryAwareException) {
            foreach ($exception->getSentryContext() as $key => $value) {
                $extra[$key] = $value;
            }
        }

        if ($this->guzzleAvailable && $exception instanceof RequestException) {
            $this->collectFromGuzzle($exception, $extra);
        }

        if ($exception->getPrevious() !== null) {
            $this->collectFromChain($exception->getPrevious(), $extra);
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function collectFromGuzzle(RequestException $exception, array &$extra): void
    {
        $request = $exception->getRequest();
        $extra['guzzle.request.method'] = $request->getMethod();
        $extra['guzzle.request.uri'] = (string) $request->getUri();
        $extra['guzzle.request.headers'] = $this->sanitizeHeaders($request->getHeaders());

        $requestBody = $this->readBody((string) $request->getBody());
        if ($requestBody !== null) {
            $extra['guzzle.request.body'] = $requestBody;
        }

        $response = $exception->getResponse();
        if ($response !== null) {
            $extra['guzzle.response.status_code'] = $response->getStatusCode();
            $extra['guzzle.response.headers'] = $this->sanitizeHeaders($response->getHeaders());

            $responseBody = $this->readBody((string) $response->getBody());
            if ($responseBody !== null) {
                $extra['guzzle.response.body'] = $responseBody;
            }
        }
    }

    private function readBody(string $body): ?string
    {
        if ($body === '' || strlen($body) > self::BODY_SIZE_LIMIT) {
            return null;
        }
        return $body;
    }

    /**
     * @param array<string, string[]> $headers
     * @return array<string, string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'x-api-key', 'cookie', 'set-cookie'];
        $result = [];

        foreach ($headers as $name => $values) {
            $result[$name] = in_array(strtolower($name), $sensitive, true)
                ? '[redacted]'
                : implode(', ', $values);
        }

        return $result;
    }
}
