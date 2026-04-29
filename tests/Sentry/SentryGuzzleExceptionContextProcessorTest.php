<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Tests\Sentry;

use Druidvav\SentryExtensionBundle\Sentry\SentryExceptionContextProcessor;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventHint;

class SentryGuzzleExceptionContextProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(RequestException::class)) {
            $this->markTestSkipped('guzzlehttp/guzzle is not installed');
        }
    }

    public function testRequestAndResponseBodiesLoggedWhenUnder1KB(): void
    {
        $extra = $this->process(
            new Request('POST', 'https://api.example.com/orders', [], '{"id":1}'),
            new Response(422, [], '{"error":"invalid"}'),
        );

        $this->assertSame('POST', $extra['guzzle.request.method']);
        $this->assertSame('https://api.example.com/orders', $extra['guzzle.request.uri']);
        $this->assertSame('{"id":1}', $extra['guzzle.request.body']);
        $this->assertSame(422, $extra['guzzle.response.status_code']);
        $this->assertSame('{"error":"invalid"}', $extra['guzzle.response.body']);
    }

    public function testRequestBodyOver1KBIsNotLogged(): void
    {
        $extra = $this->process(new Request('POST', 'https://api.example.com/upload', [], str_repeat('x', 1025)));

        $this->assertArrayNotHasKey('guzzle.request.body', $extra);
    }

    public function testResponseBodyOver1KBIsNotLogged(): void
    {
        $extra = $this->process(
            new Request('GET', 'https://api.example.com/data'),
            new Response(200, [], str_repeat('y', 1025)),
        );

        $this->assertArrayNotHasKey('guzzle.response.body', $extra);
    }

    public function testBodyExactly1KBIsLogged(): void
    {
        $body = str_repeat('z', 1024);
        $extra = $this->process(new Request('POST', 'https://api.example.com/data', [], $body));

        $this->assertSame($body, $extra['guzzle.request.body']);
    }

    public function testAuthorizationHeaderIsRedacted(): void
    {
        $extra = $this->process(
            new Request('GET', 'https://api.example.com/me', ['Authorization' => 'Bearer secret']),
        );

        $this->assertSame('[redacted]', $extra['guzzle.request.headers']['Authorization']);
    }

    public function testMissingResponseDoesNotSetResponseKeys(): void
    {
        $extra = $this->process(new Request('GET', 'https://api.example.com/ping'));

        $this->assertArrayHasKey('guzzle.request.method', $extra);
        $this->assertArrayNotHasKey('guzzle.response.status_code', $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function process(Request $request, ?Response $response = null): array
    {
        $hint = new EventHint();
        $hint->exception = new RequestException('HTTP error', $request, $response);

        $processor = new SentryExceptionContextProcessor();
        return ($processor)(Event::createEvent(), $hint)->getExtra();
    }
}
