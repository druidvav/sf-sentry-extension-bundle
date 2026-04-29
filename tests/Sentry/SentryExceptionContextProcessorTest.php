<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Tests\Sentry;

use Druidvav\SentryExtensionBundle\Contract\SentryAwareException;
use Druidvav\SentryExtensionBundle\Sentry\SentryExceptionContextProcessor;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventHint;

class SentryExceptionContextProcessorTest extends TestCase
{
    public function testSentryAwareExceptionAddsExtraToEvent(): void
    {
        $exception = new class('test') extends \RuntimeException implements SentryAwareException {
            public function getSentryContext(): array
            {
                return ['order_id' => 42, 'user' => 'alice'];
            }
        };

        $event = $this->process($exception);

        $this->assertSame(42, $event->getExtra()['order_id']);
        $this->assertSame('alice', $event->getExtra()['user']);
    }

    public function testRegularExceptionLeavesExtraEmpty(): void
    {
        $event = $this->process(new \RuntimeException('boom'));

        $this->assertEmpty($event->getExtra());
    }

    public function testPreviousExceptionIsAlsoInspected(): void
    {
        $inner = new class('inner', 0, null) extends \RuntimeException implements SentryAwareException {
            public function getSentryContext(): array
            {
                return ['source' => 'inner'];
            }
        };
        $outer = new \RuntimeException('outer', 0, $inner);

        $event = $this->process($outer);

        $this->assertSame('inner', $event->getExtra()['source']);
    }

    public function testNullHintExceptionReturnsEventUnchanged(): void
    {
        $hint = new EventHint();
        $hint->exception = null;

        $event = Event::createEvent();
        $processor = new SentryExceptionContextProcessor();
        $result = ($processor)($event, $hint);

        $this->assertSame($event, $result);
        $this->assertEmpty($result->getExtra());
    }

    private function process(\Throwable $exception): Event
    {
        $hint = new EventHint();
        $hint->exception = $exception;

        $processor = new SentryExceptionContextProcessor();
        return ($processor)(Event::createEvent(), $hint);
    }
}
