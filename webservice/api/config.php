<?php

/**
 * Loads environment variables from a .env file into PHP environment (putenv, $_ENV, $_SERVER)
 * without overriding any variables already set in the operating system environment.
 * Fully compatible with PHP 5.6+.
 */
function shodhana_load_env($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eqPos));
        $val = trim(substr($line, $eqPos + 1));
        if ($key === '') {
            continue;
        }
        $len = strlen($val);
        if ($len >= 2) {
            $first = $val[0];
            $last = $val[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $val);
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = (getenv($key) !== false ? getenv($key) : $val);
        }
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = (getenv($key) !== false ? getenv($key) : $val);
        }
    }
    return true;
}

$envCandidates = array(
    getenv('SHODHANA_ENV_FILE'),
    dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . '.env',
    dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
    __DIR__ . DIRECTORY_SEPARATOR . '.env'
);

foreach ($envCandidates as $candidate) {
    if ($candidate && file_exists($candidate) && is_file($candidate)) {
        shodhana_load_env($candidate);
        break;
    }
}

require_once __DIR__ . "/core/ModelConfig.php";

// Central configuration for the PHP API.
ini_set('display_errors', '0');

// Server host and port
$API_HOST = getenv('API_HOST') ?: '127.0.0.1';
$API_PORT = getenv('API_PORT') ?: '8081';

// ArcadeDB Connection
$ARCADE_HOST = getenv('ARCADE_HOST') ?: 'localhost';
$ARCADE_PORT = getenv('ARCADE_PORT') ?: '2480';
$ARCADE_URL = getenv('ARCADE_URL') ?: ('http://' . $ARCADE_HOST . ':' . $ARCADE_PORT);
$ARCADE_DB = getenv('ARCADE_DB') ?: 'IntegratedKG';
$ARCADE_USER = getenv('ARCADE_USER') ?: 'readonly';
$ARCADE_PASS = getenv('ARCADE_PASS') ?: 'readonly';

if (getenv('ARCADE_URL') === false) { putenv('ARCADE_URL=' . $ARCADE_URL); $_ENV['ARCADE_URL'] = $ARCADE_URL; }
if (getenv('ARCADE_DB') === false) { putenv('ARCADE_DB=' . $ARCADE_DB); $_ENV['ARCADE_DB'] = $ARCADE_DB; }
if (getenv('ARCADE_USER') === false) { putenv('ARCADE_USER=' . $ARCADE_USER); $_ENV['ARCADE_USER'] = $ARCADE_USER; }
if (getenv('ARCADE_PASS') === false) { putenv('ARCADE_PASS=' . $ARCADE_PASS); $_ENV['ARCADE_PASS'] = $ARCADE_PASS; }

// Ollama / LLM
$OLLAMA_HOST = getenv('OLLAMA_HOST') ?: '127.0.0.1';
$OLLAMA_PORT = getenv('OLLAMA_PORT') ?: '11434';
if (getenv('OLLAMA_URL') === false && getenv('OLLAMA_BASE_URL') === false && !isset($_ENV['OLLAMA_URL']) && !isset($_ENV['OLLAMA_BASE_URL'])) {
    $constructedOllamaUrl = 'http://' . $OLLAMA_HOST . ':' . $OLLAMA_PORT;
    putenv('OLLAMA_URL=' . $constructedOllamaUrl);
    $_ENV['OLLAMA_URL'] = $constructedOllamaUrl;
}

$OLLAMA_URL = ModelConfig::baseUrl();
$OLLAMA_MODEL = getenv('OLLAMA_MODEL') ?: 'llama3.2';
$OLLAMA_KEEP_ALIVE = getenv('OLLAMA_KEEP_ALIVE') ?: '5m';

// Comma-separated list, or '*' to allow all origins (local dev).
$ALLOWED_ORIGINS = getenv('ALLOWED_ORIGINS') ?: '*';

// Storage directories for caching (create on the server).
$rawStorage = getenv('API_STORAGE_DIR');
if ($rawStorage && trim($rawStorage) !== '') {
    $rawStorage = trim($rawStorage);
    $isAbsolute = ($rawStorage[0] === '/' || $rawStorage[0] === '\\' || (strlen($rawStorage) > 1 && $rawStorage[1] === ':'));
    if ($isAbsolute) {
        $API_STORAGE_DIR = $rawStorage;
    } else {
        $repoRoot = dirname(dirname(__DIR__));
        $resolved = $repoRoot . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $rawStorage);
        $API_STORAGE_DIR = (is_dir($resolved) || is_dir(dirname($resolved))) ? $resolved : (__DIR__ . DIRECTORY_SEPARATOR . 'storage');
    }
} else {
    $API_STORAGE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
}

$rawCache = getenv('API_CACHE_DIR');
if ($rawCache && trim($rawCache) !== '') {
    $rawCache = trim($rawCache);
    $isAbsolute = ($rawCache[0] === '/' || $rawCache[0] === '\\' || (strlen($rawCache) > 1 && $rawCache[1] === ':'));
    if ($isAbsolute) {
        $API_CACHE_DIR = $rawCache;
    } else {
        $repoRoot = dirname(dirname(__DIR__));
        $resolved = $repoRoot . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $rawCache);
        $API_CACHE_DIR = (is_dir($resolved) || is_dir(dirname($resolved))) ? $resolved : ($API_STORAGE_DIR . DIRECTORY_SEPARATOR . 'cache');
    }
} else {
    $API_CACHE_DIR = $API_STORAGE_DIR . DIRECTORY_SEPARATOR . 'cache';
}

putenv('API_STORAGE_DIR=' . $API_STORAGE_DIR);
$_ENV['API_STORAGE_DIR'] = $API_STORAGE_DIR;
putenv('API_CACHE_DIR=' . $API_CACHE_DIR);
$_ENV['API_CACHE_DIR'] = $API_CACHE_DIR;

// API version string for client display/debug.
$API_VERSION = getenv('API_VERSION') ?: 'v1';
