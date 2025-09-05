<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\MPEncrypt\AppInfo\Application::APP_ID, OCA\MPEncrypt\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\MPEncrypt\AppInfo\Application::APP_ID, OCA\MPEncrypt\AppInfo\Application::APP_ID . '-main');

?>

<div id="mpencrypt"></div>
