<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\EventListener;

use Druidvav\SentryExtensionBundle\Contract\SentryAwareException;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Options;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;

/**
 * Enriches Sentry events with context from SentryAwareException and Guzzle exceptions.
 */
class SentryExceptionListener
{
    private HubInterface $hub;
    private bool $guzzleAvailable;

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
        $this->guzzleAvailable = interface_exists(\GuzzleHttp\Exception\RequestException::class)
            || class_exists(\GuzzleHttp\Exception\RequestException::class);
    }

    public function onException(Throwable $exception): void
    {
        $this->hub->configureScope(function (Scope $scope) use ($exception): void {
            $this->enrichScope($scope, $exception);
        });
    }

    private function enrichScope(Scope $scope, Throwable $exception): void
    {
        if ($exception instanceof SentryAwareException) {
            foreach ($exception->getSentryContext() as $key => $value) {
                $scope->setExtra($key, $value);
            }
        }

        if ($this->guzzleAvailable) {
            $this->enrichFromGuzzle($scope, $exception);
        }

        if ($exception->getPrevious() !== null) {
            $this->enrichScope($scope, $exception->getPrevious());
        }
    }

    private function enrichFromGuzzle(Scope $scope, Throwable $exception): void
    {
        if (!$exception instanceof \GuzzleHttp\Exception\RequestException) {
            return;
        }

        $request = $exception->getRequest();
        $scope->setExtra('guzzle.request.method', $request->getMethod());
        $scope->setExtra('guzzle.request.uri', (string) $request->getUri());
        $scope->setExtra('guzzle.request.headers', $this->sanitizeHeaders($request->getHeaders()));

        $response = $exception->getResponse();
        if ($response !== null) {
            $scope->setExtra('guzzle.response.status_code', $response->getStatusCode());
            $scope->setExtra('guzzle.response.headers', $this->sanitizeHeaders($response->getHeaders()));

            $body = (string) $response->getBody();
            if ($body !== '') {
                $scope->setExtra('guzzle.response.body', mb_substr($body, 0, 4096));
            }
        }
    }

    /**
     * @param array<string, string[]> $headers
     * @return array<string, string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $result = [];
        $sensitive = ['authorization', 'x-api-key', 'cookie', 'set-cookie'];

        foreach ($headers as $name => $values) {
            $lower = strtolower($name);
            $result[$name] = in_array($lower, $sensitive, true)
                ? '[redacted]'
                : implode(', ', $values);
        }

        return $result;
    }
}
