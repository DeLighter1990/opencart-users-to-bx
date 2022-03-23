<?php

/**
 * регистрирует автозапуск
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'Delight') === 0) {
        $arClassPath = explode('\\', $class);
        foreach ($arClassPath as &$classPathPart) {
            $classPathPart = strtolower(substr($classPathPart, 0, 1)) . substr($classPathPart, 1);
        }
        $classPath = __DIR__ . '/' . implode('/', $arClassPath) . '/' . $arClassPath[count($arClassPath) - 1] . '.php';
        include($classPath);
    }
});