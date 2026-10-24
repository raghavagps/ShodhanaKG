# PHP 5.6 compatibility

The production `api/*.php` and `api/core/*.php` files support PHP 5.6.40 syntax and runtime behavior. PHP 5.6 reached end of upstream support on 2018-12-31; compatibility is not security certification. Prefer a supported runtime when server administration becomes available.

## Requirements

- PHP 5.6.40 or a supported modern PHP runtime; JSON enabled. Native mbstring is preferred; a bundled Symfony polyfill (v1.19.0, MIT, PHP >=5.3.3) provides Unicode operations on legacy hosts using iconv.
- cURL recommended; existing stream/socket database fallbacks are retained. Ollama uses a bounded socket fallback when both cURL and URL-based file reads are unavailable.
- Server-side ARCADE_URL, ARCADE_DB, ARCADE_USER, ARCADE_PASS configuration; database credentials are not frontend build variables.
- A writable private API_STORAGE_DIR and access to the configured database. Optional model assistance needs the configured Ollama service.
- Do not publish `api/tests`, local migration tools, backups, runtime binaries or audit captures in the web root.

## Compatibility behavior

Modern scalar and nullable declarations are implemented as explicit checked arguments/returns in `PhpCompatibility`. JSON operations previously using JSON_THROW_ON_ERROR still reject malformed, too-deep and invalid UTF-8 input/output. Coalescing preserves null-only fallback behavior and evaluates retrieval function calls once. Short destructuring uses `list`; comparison and suffix operations use local helpers. No scientific facts or publication identifiers are changed.

Production catches support both legacy Exceptions and modern Throwables. PHP 5.6 engine-level fatal errors are not catchable as PHP 7+ Error objects; no compatibility helper can eliminate this runtime limitation. Removed private/protected constant modifiers cannot enforce constant visibility on PHP 5.6.

The frozen reference fixtures and pre-existing evaluation tests remain unchanged. Modern PHP runs the independent evaluators, while captures and HTTP responses can come from PHP 5.6. This separates evaluation infrastructure requirements from deployed API requirements.

## Verification

Run with an actual PHP 5.6 binary, not just a newer runtime's lint:

```sh
php api/tests/test_php_compatibility.php
php api/tests/php56_runtime_capture.php api/tests/fixtures/expanded_factual_cases_v4.json /private/new-factual-capture.json
```

Run every production PHP file through `php -l`. Feed the capture to the unchanged independent factual/attribution evaluators using modern PHP/Python. The existing HTTP evaluators accept a dedicated server URL:

```sh
CHATBOT_EVAL_URL=http://127.0.0.1:8086/api/chatbot_response.php php8.2 api/tests/run_evaluation_cases.php --http --fixture=api/tests/fixtures/chatbot_evaluation_cases_v7.json
python api/tests/evaluate_results_presentation.py --capture --url http://127.0.0.1:8086/api/chatbot_response.php --fixture presentation_cases_v5.json --answers /private/answers.json --output /private/presentation.json
python api/tests/evaluate_conversations.py --url http://127.0.0.1:8086/api/chatbot_response.php --fixture conversation_evaluation_cases_v3.json --output /private/conversation.json
```

The PHP built-in server is for isolated local verification only, not production. Back up production before deployment and verify the actual Apache runtime and its extensions independently. Deployment results are recorded separately from the compatibility audit.

## Ollama on restricted Apache hosting

`LLMClient` supports cURL, URL streams, and the bundled `SocketHttp` transport. Socket requests enforce timeouts, response size limits, HTTP status checking and chunk decoding. HTTPS sockets retain certificate validation. When native cURL is unavailable, optional model streaming uses the existing buffered generation fallback; the chatbot's JSON/SSE factual metadata remains unchanged.

`OLLAMA_KEEP_ALIVE` defaults to `5m`. Use `-1` to keep the selected model resident after a request, or `0` to unload it; these two settings are serialized as JSON integers, not duration strings. Retention is a request to Ollama, not a guarantee across service restarts, memory pressure or requests from other applications. Warm the model after an Ollama restart. Deterministic KG answers do not depend on model availability.

## Database-specific reference bindings

Reference catalog `edge_ids` must match the actual deployment database. A re-import may change RIDs even when node contents, directed edges, and properties are identical. Never infer new bindings from a numeric RID offset alone. Compare all vertex properties, then each directed edge with resolved endpoints and complete properties, retaining duplicate multiplicities. Only apply publication namespace metadata to verified equivalent production edges; leave unmatched or inconsistent bindings unresolved. Do not change KG records or benchmark fixtures to force a match. Keep the deployment-specific catalog, reconciliation report, and original catalog backup together.
