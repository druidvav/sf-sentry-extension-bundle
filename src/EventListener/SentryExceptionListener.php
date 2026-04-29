<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\EventListener;

use Druidvav\SentryExtensionBundle\Contract\SentryAwareException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;

class SentryExceptionListener
{
    private const BODY_SIZE_LIMIT = 1024;

    private bool $guzzleAvailable;

    public function __construct(private readonly HubInterface $hub)
    {
        $this->guzzleAvailable = class_exists(GuzzleRequestException::class);
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

        if ($this->guzzleAvailable && $exception instanceof GuzzleRequestException) {
            $this->enrichFromGuzzle($scope, $exception);
        }

        if ($exception->getPrevious() !== null) {
            $this->enrichScope($scope, $exception->getPrevious());
        }
    }

    private function enrichFromGuzzle(Scope $scope, GuzzleRequestException $exception): void
    {
        $request = $exception->getRequest();
        $scope->setExtra('guzzle.request.method', $request->getMethod());
        $scope->setExtra('guzzle.request.uri', (string) $request->getUri());
        $scope->setExtra('guzzle.request.headers', $this->sanitizeHeaders($request->getHeaders()));

        $requestBody = $this->readBody((string) $request->getBody());
        if ($requestBody !== null) {
            $scope->setExtra('guzzle.request.body', $requestBody);
        }

        $response = $exception->getResponse();
        if ($response !== null) {
            $scope->setExtra('guzzle.response.status_code', $response->getStatusCode());
            $scope->setExtra('guzzle.response.headers', $this->sanitizeHeaders($response->getHeaders()));

            $responseBody = $this->readBody((string) $response->getBody());
            if ($responseBody !== null) {
                $scope->setExtra('guzzle.response.body', $responseBody);
            }
        }
    }

    private function readBody(string $body): ?string
    {
        if ($body === '') {
            return null;
        }
        if (strlen($body) > self::BODY_SIZE_LIMIT) {
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
