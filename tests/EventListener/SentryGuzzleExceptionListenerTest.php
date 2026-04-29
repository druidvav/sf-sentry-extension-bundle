<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Tests\EventListener;

use Druidvav\SentryExtensionBundle\EventListener\SentryExceptionListener;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class SentryGuzzleExceptionListenerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(RequestException::class)) {
            $this->markTestSkipped('guzzlehttp/guzzle is not installed');
        }
    }

    public function testGuzzleRequestExceptionEnrichesScope(): void
    {
        $scope = new Scope();
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(function (callable $cb) use ($scope): void {
                $cb($scope);
            });

        $request = new Request('POST', 'https://api.example.com/orders', ['X-Custom' => 'value']);
        $response = new Response(422, ['Content-Type' => 'application/json'], '{"error":"invalid"}');
        $exception = new RequestException('HTTP error', $request, $response);

        $listener = new SentryExceptionListener($hub);
        $listener->onException($exception);

        $event = $scope->applyToEvent(\Sentry\Event::createEvent(), new \Sentry\EventHint());
        $extra = $event->getExtra();

        $this->assertSame('POST', $extra['guzzle.request.method']);
        $this->assertSame('https://api.example.com/orders', $extra['guzzle.request.uri']);
        $this->assertSame(422, $extra['guzzle.response.status_code']);
        $this->assertSame('{"error":"invalid"}', $extra['guzzle.response.body']);
    }

    public function testGuzzleAuthorizationHeaderIsRedacted(): void
    {
        $scope = new Scope();
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(function (callable $cb) use ($scope): void {
                $cb($scope);
            });

        $request = new Request('GET', 'https://api.example.com/me', [
            'Authorization' => 'Bearer secret-token',
        ]);
        $exception = new RequestException('HTTP error', $request);

        $listener = new SentryExceptionListener($hub);
        $listener->onException($exception);

        $event = $scope->applyToEvent(\Sentry\Event::createEvent(), new \Sentry\EventHint());
        $extra = $event->getExtra();

        $this->assertSame('[redacted]', $extra['guzzle.request.headers']['Authorization']);
    }

    public function testGuzzleExceptionWithoutResponseDoesNotSetResponseKeys(): void
    {
        $scope = new Scope();
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(function (callable $cb) use ($scope): void {
                $cb($scope);
            });

        $request = new Request('GET', 'https://api.example.com/ping');
        $exception = new RequestException('Connection refused', $request);

        $listener = new SentryExceptionListener($hub);
        $listener->onException($exception);

        $event = $scope->applyToEvent(\Sentry\Event::createEvent(), new \Sentry\EventHint());
        $extra = $event->getExtra();

        $this->assertArrayHasKey('guzzle.request.method', $extra);
        $this->assertArrayNotHasKey('guzzle.response.status_code', $extra);
    }
}
