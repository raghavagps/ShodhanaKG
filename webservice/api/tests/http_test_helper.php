<?php
/** Reject broken transport before an HTTP assertion can claim a security pass. */
function test_http_json_response($body, array $headers): array {
    if ($body === false || $body === '') throw new RuntimeException('No HTTP response body');
    $status = null; $contentType = '';
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $header, $match)) {
            $status = (int)$match[1]; $contentType = '';
        }
        if (stripos($header, 'Content-Type:') === 0) $contentType = trim(substr($header, 13));
    }
    if (!in_array($status, [200, 400], true)) throw new RuntimeException('Unexpected HTTP status: '.($status ?? 'missing'));
    if (!preg_match('/^application\/json(?:\s*;|$)/i', $contentType)) throw new RuntimeException('Expected JSON content type');
    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || !is_string($decoded['status'] ?? null)) throw new RuntimeException('Missing chatbot status envelope');
    if ($status === 400 && $decoded['status'] !== 'error') throw new RuntimeException('HTTP error/status mismatch');
    return $decoded;
}
