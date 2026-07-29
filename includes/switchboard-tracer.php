<?php

declare(strict_types=1);

/**
 * Switchboard observability tracer — PHP port of the reference tracer.js.
 *
 * This codebase is plain PHP + curl (no Node process handles requests here),
 * so the original tracer.js can't be dropped in as-is; this mirrors its
 * request shape and behavior instead. Server-side only: it reads
 * SWITCHBOARD_TRACER_KEY, so this file must never be served to the browser
 * (nothing here does — every caller is a .php file executed by the server).
 *
 * One deliberate difference from tracer.js: the JS version throws at import
 * time if the tracer env vars are missing, and lets tracer request failures
 * propagate. Here, every call site wrapped with switchboard_traced() is
 * production lead-capture or reporting code, so a misconfigured or
 * unreachable tracer is logged and swallowed rather than allowed to break
 * the underlying AI call — observability going down must degrade to
 * "untraced", never to "broken feature".
 */

require_once __DIR__ . '/env.php';

const SWITCHBOARD_MAX_FIELD_CHARS = 90_000;

function switchboard_configured(): bool
{
    return (bool) getenv('SWITCHBOARD_TRACER_URL') && (bool) getenv('SWITCHBOARD_TRACER_KEY');
}

/**
 * The API rejects inputs/outputs over 100 KB. Clip instead of throwing so a
 * long prompt degrades the trace rather than failing the request.
 */
function switchboard_clip(mixed $value): mixed
{
    if ($value === null || $value === '') {
        return $value;
    }

    $encoded = json_encode($value);
    if ($encoded === false) {
        return ['unserializable' => true];
    }

    if (strlen($encoded) <= SWITCHBOARD_MAX_FIELD_CHARS) {
        return $value;
    }

    $more = strlen($encoded) - SWITCHBOARD_MAX_FIELD_CHARS;

    return ['truncated' => substr($encoded, 0, SWITCHBOARD_MAX_FIELD_CHARS) . "… [{$more} more characters]"];
}

/**
 * @throws Exception on transport failure or a non-2xx response — a 401 here
 *                    means the key, nothing else.
 */
function switchboard_send(string $method, string $path, array $payload): array
{
    $url = rtrim((string) getenv('SWITCHBOARD_TRACER_URL'), '/') . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . getenv('SWITCHBOARD_TRACER_KEY'),
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
        $detail = $curlError !== '' ? $curlError : (string) $response;
        throw new Exception("Tracer {$method} {$path} failed ({$httpCode}): " . substr($detail, 0, 200));
    }

    $decoded = json_decode((string) $response, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Wraps one AI call so it shows up as a run in Switchboard.
 *
 * $call must return ['outputs' => mixed, 'tokenUsage' => array|null] and may
 * throw. On success the run is patched with the outputs/tokenUsage; on
 * failure the run is patched with the error and the exception is rethrown
 * unchanged. Returns $call()'s 'outputs' value directly, so wrapping an
 * existing function doesn't change what its callers receive.
 *
 * $options keys: name (string, required), model (?string), inputs (mixed),
 * runType (string, default 'llm'), tags (string[]), parentRunId (?string).
 */
function switchboard_traced(array $options, callable $call): mixed
{
    $runId = null;

    if (switchboard_configured()) {
        try {
            $run = switchboard_send('POST', '/api/v1/runs', [
                'name' => $options['name'],
                'runType' => $options['runType'] ?? 'llm',
                'model' => $options['model'] ?? null,
                'inputs' => switchboard_clip($options['inputs'] ?? null),
                'tags' => $options['tags'] ?? [],
                'parentRunId' => $options['parentRunId'] ?? null,
            ]);
            $runId = $run['runId'] ?? null;
        } catch (Exception $e) {
            error_log('Switchboard tracer: failed to start run - ' . $e->getMessage());
        }
    }

    try {
        $result = $call();

        if ($runId !== null) {
            try {
                switchboard_send('PATCH', "/api/v1/runs/{$runId}", [
                    'outputs' => switchboard_clip($result['outputs'] ?? null),
                    'tokenUsage' => $result['tokenUsage'] ?? null,
                ]);
            } catch (Exception $e) {
                error_log('Switchboard tracer: failed to update run - ' . $e->getMessage());
            }
        }

        return $result['outputs'] ?? null;
    } catch (Throwable $err) {
        if ($runId !== null) {
            try {
                switchboard_send('PATCH', "/api/v1/runs/{$runId}", ['error' => $err->getMessage()]);
            } catch (Exception $e) {
                error_log('Switchboard tracer: failed to record run error - ' . $e->getMessage());
            }
        }

        throw $err;
    }
}

/**
 * Extracts { input, output, total } token counts from a Gemini
 * generateContent response's usageMetadata, or null if absent.
 */
function switchboard_gemini_token_usage(array $apiResponse): ?array
{
    $usage = $apiResponse['usageMetadata'] ?? null;
    if (!is_array($usage)) {
        return null;
    }

    return [
        'input' => $usage['promptTokenCount'] ?? 0,
        'output' => $usage['candidatesTokenCount'] ?? 0,
        'total' => $usage['totalTokenCount'] ?? 0,
    ];
}
