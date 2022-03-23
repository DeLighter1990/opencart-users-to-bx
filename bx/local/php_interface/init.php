<?php

use Bitrix\Main\EventManager;

if (file_exists(Bitrix\Main\Application::getDocumentRoot() . '/local/php_interface/include/delight.php')) {
    require_once(Bitrix\Main\Application::getDocumentRoot() . '/local/php_interface/include/delight.php');
}

// Events
$obDelightUsers = new \Delight\Users();
EventManager::getInstance()->addEventHandler("main", "OnBeforeUserLogin", array($obDelightUsers, "OnBeforeUserLoginHandler"));