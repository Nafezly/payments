#!/usr/bin/env php
<?php

/**
 * KoraPay checkout redirect smoke test (no Laravel required).
 *
 * Usage:
 *   KORAPAY_SECRET_KEY=sk_live_xxx KORAPAY_PUBLIC_KEY=pk_live_xxx php scripts/korapay-test.php
 *   KORAPAY_SECRET_KEY=sk_live_xxx php scripts/korapay-test.php verify REFERENCE
 */

declare(strict_types=1);

const BASE_URL = 'https://api.korapay.com';

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function request(string $method, string $path, ?array $body = null, ?string $secretKey = null): array
{
    $url = rtrim(BASE_URL, '/') . $path;
    $headers = ['Content-Type: application/json', 'Accept: application/json'];

    if ($secretKey) {
        $headers[] = 'Authorization: Bearer ' . $secretKey;
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

function printJson(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

function toMinorUnits(float $amount, string $currency): int
{
    $currency = strtoupper($currency);

    if (in_array($currency, ['BHD', 'KWD', 'OMR', 'JOD'], true)) {
        return (int) round($amount * 1000);
    }

    if ($currency === 'JPY') {
        return (int) round($amount);
    }

    return (int) round($amount * 100);
}

function createCheckout(string $secretKey): array
{
    printStep('1) Initialize checkout — POST /merchant/api/v1/charges/initialize');

    $reference = 'test_' . date('YmdHis') . '_' . random_int(1000, 9999);
    $currency = env('KORAPAY_CURRENCY', 'USD');
    $amountMajor = (float) env('KORAPAY_TEST_AMOUNT', '2.40');

    $payload = [
        'amount' => toMinorUnits($amountMajor, $currency),
        'currency' => strtoupper($currency),
        'reference' => $reference,
        'redirect_url' => env('KORAPAY_TEST_REDIRECT_URL', 'https://example.com/korapay/callback'),
        'notification_url' => env('KORAPAY_WEBHOOK_URL', 'https://example.com/korapay/webhook'),
        'channels' => ['card'],
        'default_channel' => 'card',
        'customer' => [
            'name' => env('KORAPAY_TEST_NAME', 'Test Customer'),
            'email' => env('KORAPAY_TEST_EMAIL', 'test@example.com'),
        ],
    ];

    echo "Reference: {$reference}\n";
    echo "Amount: {$amountMajor} {$currency} (" . $payload['amount'] . " minor units)\n";

    $response = request('POST', '/merchant/api/v1/charges/initialize', $payload, $secretKey);
    printJson($response['json'] ?? ['raw' => $response['raw'], 'http_code' => $response['http_code']]);

    if (empty($response['json']['data']['checkout_url'])) {
        throw new RuntimeException('Failed to create KoraPay checkout.');
    }

    return $response['json']['data'];
}

function verifyCheckout(string $secretKey, string $reference): array
{
    printStep('2) Verify — GET /merchant/api/v1/charges/{reference}');

    $response = request('GET', '/merchant/api/v1/charges/' . rawurlencode($reference), null, $secretKey);
    printJson($response['json'] ?? ['raw' => $response['raw'], 'http_code' => $response['http_code']]);

    return $response['json']['data'] ?? [];
}

function main(array $argv): int
{
    $secretKey = env('KORAPAY_SECRET_KEY');

    if (!$secretKey) {
        fwrite(STDERR, "Missing KORAPAY_SECRET_KEY.\n\n");
        fwrite(STDERR, "Run:\n");
        fwrite(STDERR, "  KORAPAY_SECRET_KEY=sk_live_xxx php scripts/korapay-test.php\n\n");
        return 1;
    }

    try {
        if (($argv[1] ?? '') === 'verify') {
            $reference = $argv[2] ?? '';
            if ($reference === '') {
                throw new InvalidArgumentException('Usage: php scripts/korapay-test.php verify REFERENCE');
            }

            $result = verifyCheckout($secretKey, $reference);
            $status = $result['status'] ?? 'UNKNOWN';
            $success = $status === 'success';

            echo $success ? "\n✅ Payment successful (status: {$status})\n" : "\n❌ Payment not successful (status: {$status})\n";

            return $success ? 0 : 2;
        }

        $checkout = createCheckout($secretKey);
        $reference = $checkout['reference'];
        $checkoutUrl = $checkout['checkout_url'];

        printStep('Pay link');
        echo "Reference: {$reference}\n";
        echo "Checkout URL:\n{$checkoutUrl}\n\n";
        echo "Open the checkout URL in your browser to complete payment.\n";
        echo "After payment, verify with:\n";
        echo "  KORAPAY_SECRET_KEY=... php scripts/korapay-test.php verify {$reference}\n";

        return 0;
    } catch (Throwable $e) {
        fwrite(STDERR, "\nError: {$e->getMessage()}\n");
        return 1;
    }
}

exit(main($argv));
