<?php

declare(strict_types=1);

namespace OCA\MPEncrypt\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Util;
use OCP\Files\Events\LoadAdditionalScriptsEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'mpencrypt';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

    public function register(IRegistrationContext $context): void {
        // Register typed event listener for Files app integration
        $context->registerEventListener(
            LoadAdditionalScriptsEvent::class,
            \OCA\MPEncrypt\Listener\LoadAdditionalScriptsListener::class
        );
    }

    public function boot(IBootContext $context): void {
        // Backward-compatible listeners for legacy string events
        $container = $context->getServerContainer();
        /** @var \OCP\EventDispatcher\IEventDispatcher $eventDispatcher */
        $eventDispatcher = $container->get(\OCP\EventDispatcher\IEventDispatcher::class);

        // Legacy Files event names used by some NC versions/themes
        $eventDispatcher->addListener('OCA\\Files::loadAdditionalScripts', function (): void {
            Util::addScript(self::APP_ID, self::APP_ID . '-files');
        });
        $eventDispatcher->addListener('OCP\\Files::loadAdditionalScripts', function (): void {
            Util::addScript(self::APP_ID, self::APP_ID . '-files');
        });

        // Pragmatic fallback: if current request is Files app, inject directly
        /** @var \OCP\IRequest $request */
        $request = $container->get(\OCP\IRequest::class);
        $path = (string) method_exists($request, 'getPathInfo') ? $request->getPathInfo() : '';
        if ($path !== '' && (strpos($path, '/apps/files') !== false)) {
            Util::addScript(self::APP_ID, self::APP_ID . '-files');
        }
    }
}
