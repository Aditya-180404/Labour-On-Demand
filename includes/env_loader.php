<?php
/**
 * Simple Environment Loader
 * Loads variables from .env file into constants and $_ENV
 */

if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

function loadEnv($path) {
    if (!file_exists($path) || !is_readable($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return false;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Strip surrounding quotes
        if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }

        if (!defined($name)) {
            define($name, $value);
        }
        $_ENV[$name] = $value;
        if (function_exists('putenv')) {
            @putenv("$name=$value");
        }
    }
    return true;
}

// Use a more reliable absolute path calculation
$env_path = dirname(dirname(__FILE__)) . '/.env';
loadEnv($env_path);
?>
