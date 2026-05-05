<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Tests\Sentry;

use Druidvav\SentryExtensionBundle\Sentry\EventProcessorRegistry;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\State\Scope;

class EventProcessorRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new \ReflectionProperty(Scope::class, 'globalEventProcessors');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(Scope::class, 'globalEventProcessors');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    public function testRegisterAddsProcessorToGlobalScope(): void
    {
        $called = false;
        $processor = function (Event $event, EventHint $hint) use (&$called): Event {
            $called = true;
            return $event;
        };

        $registry = new EventProcessorRegistry([$processor]);
        $registry->register();

        (new Scope())->applyToEvent(Event::createEvent(), new EventHint());

        $this->assertTrue($called);
    }

    public function testRegisterIsIdempotentForEmptyProcessors(): void
    {
        $registry = new EventProcessorRegistry([]);
        $registry->register();

        $event = (new Scope())->applyToEvent(Event::createEvent(), new EventHint());
        $this->assertNotNull($event);
    }

    public function testMultipleProcessorsAreAllRegistered(): void
    {
        $log = [];
        $registry = new EventProcessorRegistry([
            function (Event $event, EventHint $hint) use (&$log): Event { $log[] = 'a'; return $event; },
            function (Event $event, EventHint $hint) use (&$log): Event { $log[] = 'b'; return $event; },
        ]);
        $registry->register();

        (new Scope())->applyToEvent(Event::createEvent(), new EventHint());

        $this->assertSame(['a', 'b'], $log);
    }
}
