<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\Listener;

use OCA\MPEncrypt\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\LoadAdditionalScriptsEvent;
use OCP\Util;

/**
 * Injects the Files integration script when the Files app asks for additional scripts.
 */
class LoadAdditionalScriptsListener implements IEventListener {
    /**
     * @param Event $event
     */
    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalScriptsEvent) {
            return;
        }

        Util::addScript(Application::APP_ID, Application::APP_ID . '-files');
    }
}

