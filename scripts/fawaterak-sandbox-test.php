#!/usr/bin/env php
<?php

/**
 * Fawaterak sandbox smoke test (no Laravel required).
 *
 * Usage:
 *   FAWATERAK_API_KEY=xxx php scripts/fawaterak-sandbox-test.php
 *   FAWATERAK_API_KEY=xxx php scripts/fawaterak-sandbox-test.php verify 1001267
 */

declare(strict_types=1);

const DEFAULT_BASE_URL = 'https://staging.fawaterk.com';

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function request(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    $baseUrl = rtrim(env('FAWATERAK_BASE_URL', DEFAULT_BASE_URL), '/');
    $url = $baseUrl . $path;
    $headers = ['Content-Type: application/json', 'Accept: application/json'];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('cURL error: ' . $error);
    }

    $json = json_decode($raw, true);

    return [
        'http_code' => $httpCode,
        'raw' => $raw,
        'json' => is_array($json) ? $json : null,
    ];
}

function printStep(string $title): void
{
    echo "\n=== {$title} ===\n";
}

function printJson($data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

try {
    $apiKey = env('FAWATERAK_API_KEY');
    if (!$apiKey) {
        fwrite(STDERR, "Missing FAWATERAK_API_KEY\n");
        fwrite(STDERR, "Example: FAWATERAK_API_KEY=xxx php scripts/fawaterak-sandbox-test.php\n");
        exit(1);
    }

    $mode = $argv[1] ?? 'create';
    $invoiceId = $argv[2] ?? null;

    if ($mode === 'verify') {
        if (!$invoiceId) {
            throw new InvalidArgumentException('Usage: php scripts/fawaterak-sandbox-test.php verify INVOICE_ID');
        }

        printStep('Get invoice data');
        $response = request('GET', '/api/v2/getInvoiceData/' . urlencode($invoiceId), null, $apiKey);
        printJson($response['json'] ?? $response['raw']);
        exit($response['http_code'] >= 200 && $response['http_code'] < 300 ? 0 : 1);
    }

    printStep('Create invoice link');
    $response = request('POST', '/api/v2/createInvoiceLink', [
        'cartTotal' => env('FAWATERAK_TEST_AMOUNT', '50.00'),
        'currency' => env('FAWATERAK_CURRENCY', 'EGP'),
        'invoice_number' => 'test_' . uniqid(),
        'customer' => [
            'first_name' => 'Sandbox',
            'last_name' => 'Test',
            'email' => env('FAWATERAK_TEST_EMAIL', 'sandbox-test@example.com'),
            'phone' => '01000000000',
            'address' => 'Test address',
        ],
        'cartItems' => [[
            'name' => 'Sandbox payment',
            'price' => env('FAWATERAK_TEST_AMOUNT', '50.00'),
            'quantity' => '1',
        ]],
        'payLoad' => [
            'merchant_reference' => 'sandbox_' . uniqid(),
        ],
        'sendEmail' => false,
        'sendSMS' => false,
    ], $apiKey);

    printJson($response['json'] ?? $response['raw']);

    $invoiceId = data_get($response, 'json.data.invoiceId');
    if ($invoiceId) {
        echo "\nVerify later with:\n";
        echo "  FAWATERAK_API_KEY=... php scripts/fawaterak-sandbox-test.php verify {$invoiceId}\n";
    }

    exit($response['http_code'] >= 200 && $response['http_code'] < 300 ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function data_get($target, ?string $key, $default = null)
{
    if (!is_array($target)) {
        return $default;
    }

    foreach (explode('.', $key ?? '') as $segment) {
        if (!is_array($target) || !array_key_exists($segment, $target)) {
            return $default;
        }
        $target = $target[$segment];
    }

    return $target;
}
