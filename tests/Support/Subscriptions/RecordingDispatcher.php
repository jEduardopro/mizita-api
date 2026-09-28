<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use Illuminate\Contracts\Events\Dispatcher;
use LogicException;
use Tests\Support\FakeTransactionManager;

final class RecordingDispatcher implements Dispatcher
{
    /** @var list<object|string> */
    public array $dispatched = [];

    /** @var list<bool> */
    public array $dispatchedInsideTransaction = [];

    public function __construct(
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function dispatch($event, $payload = [], $halt = false)
    {
        $this->dispatched[] = $event;
        $this->dispatchedInsideTransaction[] = $this->transactions?->isRunning() ?? false;

        return null;
    }

    public function listen($events, $listener = null)
    {
        throw self::unexpected(__FUNCTION__);
    }

    public function hasListeners($eventName)
    {
        throw self::unexpected(__FUNCTION__);
    }

    public function subscribe($subscriber)
    {
        throw self::unexpected(__FUNCTION__);
    }

    public function until($event, $payload = [])
    {
        throw self::unexpected(__FUNCTION__);
    }

    public function push($event, $payload = [])
    {
        throw self::unexpected(__FUNCTION__);
    }

    public function flush($event)
    {
        throw self::unexpected(__FUNCTION__);
    }

    public function forget($event)
    {
        throw self::unexpected(__FUNCTION__);
    }

    public function forgetPushed()
    {
        throw self::unexpected(__FUNCTION__);
    }

    private static function unexpected(string $method): LogicException
    {
        return new LogicException("RecordingDispatcher only records dispatch(); the code under test called {$method}().");
    }
}
