<?php

declare(strict_types=1);

namespace Druidvav\SentryExtensionBundle\Tests\EventListener;

use Druidvav\SentryExtensionBundle\Contract\SentryAwareException;
use Druidvav\SentryExtensionBundle\EventListener\SentryExceptionListener;
use PHPUnit\Framework\TestCase;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class SentryExceptionListenerTest extends TestCase
{
    public function testSentryAwareExceptionEnrichesScope(): void
    {
        $scope = new Scope();
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(function (callable $cb) use ($scope): void {
                $cb($scope);
            });

        $exception = new class('test') extends \RuntimeException implements SentryAwareException {
            public function getSentryContext(): array
            {
                return ['order_id' => 42, 'user' => 'alice'];
            }
        };

        $listener = new SentryExceptionListener($hub);
        $listener->onException($exception);

        $event = $scope->applyToEvent(\Sentry\Event::createEvent(), new \Sentry\EventHint());
        $this->assertSame(42, $event->getExtra()['order_id']);
        $this->assertSame('alice', $event->getExtra()['user']);
    }

    public function testRegularExceptionDoesNothing(): void
    {
        $scope = new Scope();
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(function (callable $cb) use ($scope): void {
                $cb($scope);
            });

        $listener = new SentryExceptionListener($hub);
        $listener->onException(new \RuntimeException('boom'));

        $event = $scope->applyToEvent(\Sentry\Event::createEvent(), new \Sentry\EventHint());
        $this->assertEmpty($event->getExtra());
    }

    public function testPreviousExceptionIsAlsoInspected(): void
    {
        $scope = new Scope();
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->willReturnCallback(function (callable $cb) use ($scope): void {
                $cb($scope);
            });

        $inner = new class('inner', 0, null) extends \RuntimeException implements SentryAwareException {
            public function getSentryContext(): array
            {
                return ['source' => 'inner'];
            }
        };
        $outer = new \RuntimeException('outer', 0, $inner);

        $listener = new SentryExceptionListener($hub);
        $listener->onException($outer);

        $event = $scope->applyToEvent(\Sentry\Event::createEvent(), new \Sentry\EventHint());
        $this->assertSame('inner', $event->getExtra()['source']);
    }
}
