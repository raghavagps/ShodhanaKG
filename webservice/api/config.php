<?php
require_once __DIR__ . "/core/ModelConfig.php";

// Central configuration for the PHP API.
// Use environment variables in deployment; defaults are for local testing.
ini_set('display_errors', '0');

$ARCADE_URL = getenv('ARCADE_URL') ?: 'http://localhost:2480';
$ARCADE_DB = getenv('ARCADE_DB') ?: 'IntegratedKG';
$ARCADE_USER = getenv('ARCADE_USER') ?: 'username';
$ARCADE_PASS = getenv('ARCADE_PASS') ?: 'password';

$OLLAMA_URL = ModelConfig::baseUrl();
$OLLAMA_MODEL = getenv('OLLAMA_MODEL') ?: 'llama3.2';

// Comma-separated list, or '*' to allow all origins (local dev).
$ALLOWED_ORIGINS = getenv('ALLOWED_ORIGINS') ?: '*';

// Storage directories for caching (create on the server).
$API_STORAGE_DIR = getenv('API_STORAGE_DIR') ?: (__DIR__ . '/storage');
$API_CACHE_DIR = getenv('API_CACHE_DIR') ?: ($API_STORAGE_DIR . '/cache');

// API version string for client display/debug.
$API_VERSION = getenv('API_VERSION') ?: 'v1';
