<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Tests\EventListener;

use Druidvav\SentryExtensionBundle\EventListener\SentryExceptionListener;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sentry\EventHint;
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

    public function testRequestAndResponseBodiesAreLoggedWhenUnder1KB(): void
    {
        $scope = new Scope();
        $hub = $this->mockHub($scope);

        $requestBody = '{"id":1}';
        $responseBody = '{"error":"invalid"}';

        $request = new Request('POST', 'https://api.example.com/orders', [], $requestBody);
        $response = new Response(422, [], $responseBody);
        $exception = new RequestException('HTTP error', $request, $response);

        (new SentryExceptionListener($hub))->onException($exception);

        $extra = $this->extra($scope);
        $this->assertSame('POST', $extra['guzzle.request.method']);
        $this->assertSame('https://api.example.com/orders', $extra['guzzle.request.uri']);
        $this->assertSame($requestBody, $extra['guzzle.request.body']);
        $this->assertSame(422, $extra['guzzle.response.status_code']);
        $this->assertSame($responseBody, $extra['guzzle.response.body']);
    }

    public function testRequestBodyLargerThan1KBIsNotLogged(): void
    {
        $scope = new Scope();
        $hub = $this->mockHub($scope);

        $request = new Request('POST', 'https://api.example.com/upload', [], str_repeat('x', 1025));
        $exception = new RequestException('HTTP error', $request);

        (new SentryExceptionListener($hub))->onException($exception);

        $extra = $this->extra($scope);
        $this->assertArrayNotHasKey('guzzle.request.body', $extra);
    }

    public function testResponseBodyLargerThan1KBIsNotLogged(): void
    {
        $scope = new Scope();
        $hub = $this->mockHub($scope);

        $request = new Request('GET', 'https://api.example.com/data');
        $response = new Response(200, [], str_repeat('y', 1025));
        $exception = new RequestException('HTTP error', $request, $response);

        (new SentryExceptionListener($hub))->onException($exception);

        $extra = $this->extra($scope);
        $this->assertArrayNotHasKey('guzzle.response.body', $extra);
    }

    public function testBodyExactly1KBIsLogged(): void
    {
        $scope = new Scope();
        $hub = $this->mockHub($scope);

        $body = str_repeat('z', 1024);
        $request = new Request('POST', 'https://api.example.com/data', [], $body);
        $exception = new RequestException('HTTP error', $request);

        (new SentryExceptionListener($hub))->onException($exception);

        $extra = $this->extra($scope);
        $this->assertSame($body, $extra['guzzle.request.body']);
    }

    public function testAuthorizationHeaderIsRedacted(): void
    {
        $scope = new Scope();
        $hub = $this->mockHub($scope);

        $request = new Request('GET', 'https://api.example.com/me', ['Authorization' => 'Bearer secret']);
        $exception = new RequestException('HTTP error', $request);

        (new SentryExceptionListener($hub))->onException($exception);

        $extra = $this->extra($scope);
        $this->assertSame('[redacted]', $extra['guzzle.request.headers']['Authorization']);
    }

    public function testMissingResponseDoesNotSetResponseKeys(): void
    {
        $scope = new Scope();
        $hub = $this->mockHub($scope);

        $request = new Request('GET', 'https://api.example.com/ping');
        $exception = new RequestException('Connection refused', $request);

        (new SentryExceptionListener($hub))->onException($exception);

        $extra = $this->extra($scope);
        $this->assertArrayHasKey('guzzle.request.method', $extra);
        $this->assertArrayNotHasKey('guzzle.response.status_code', $extra);
    }

    private function mockHub(Scope $scope): HubInterface
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(static function (callable $cb) use ($scope): void {
                $cb($scope);
            });
        return $hub;
    }

    private function extra(Scope $scope): array
    {
        return $scope->applyToEvent(\Sentry\Event::createEvent(), new EventHint())->getExtra();
    }
}
