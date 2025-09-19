<?php

declare(strict_types=1);

namespace DjWeb\Payments\Contracts;

use DjWeb\Payments\DTOs\WebhookEvent;

interface WebhookHandlerContract
{
    /**
     * Handle webhook event
     */
    public function handle(WebhookEvent $event): void;

    /**
     * Check if handler supports the event type
     */
    public function supports(string $eventType): bool;
}
