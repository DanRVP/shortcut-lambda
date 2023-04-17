<?php declare(strict_types=1);


require __DIR__ . '/vendor/autoload.php';

use Shortcut\Shortcut;
use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;

class Handler extends EventBridgeHandler
{
    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        $shortcut = new Shortcut();
        $iteration_id = $shortcut->getCurrentIterationId();
        $shortcut->uploadReport($iteration_id, $shortcut->generateReport($iteration_id));
    }
}

return new Handler();
