#!/usr/bin/env php
<?php

/**
 * Payzink sandbox smoke test (no Laravel required).
 *
 * Usage:
 *   PAYZINK_PUBLISHABLE_KEY=pk_test_xxx PAYZINK_SECRET_KEY=sk_test_xxx php scripts/payzink-sandbox-test.php
 *   PAYZINK_PUBLISHABLE_KEY=pk_test_xxx PAYZINK_SECRET_KEY=sk_test_xxx php scripts/payzink-sandbox-test.php verify REFERENCE_UUID
 */

declare(strict_types=1);

const BASE_URL = 'https://merchant-dev.payzink.com';

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
    $url = rtrim(BASE_URL, '/') . $path;
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

function printJson(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

function getAccessToken(string $publishableKey, string $secretKey): string
{
    printStep('1) Auth — POST /api/v1/auth/access-token');

    $response = request('POST', '/api/v1/auth/access-token', [
        'publishableKey' => $publishableKey,
        'secretKey' => $secretKey,
    ]);

    printJson($response['json'] ?? ['raw' => $response['raw']]);

    $token = $response['json']['result']['accessToken'] ?? null;
    if (!$token) {
        throw new RuntimeException('Failed to obtain access token. Check sandbox credentials.');
    }

    echo "Access token obtained (expires in {$response['json']['result']['expiresIn']}s)\n";

    return $token;
}

function createHostedPayment(string $token): array
{
    printStep('2) Create hosted payment — POST /api/v1/payment/hosted');

    $orderId = 'test-' . date('YmdHis') . '-' . random_int(1000, 9999);
    $payload = [
        'order' => [
            'action' => env('PAYZINK_ACTION', 'PURCHASE'),
            'amount' => [
                'currencyCode' => env('PAYZINK_CURRENCY', 'USD'),
                'value' => (int) env('PAYZINK_TEST_AMOUNT', '2500'),
            ],
        ],
        'customer' => [
            'email' => env('PAYZINK_TEST_EMAIL', 'sandbox-test@example.com'),
        ],
        'extra' => [
            'orderId' => $orderId,
        ],
        '_links' => [
            'notificationUrl' => env('PAYZINK_WEBHOOK_URL', 'https://example.com/webhooks/payzink'),
        ],
    ];

    echo "Order ID: {$orderId}\n";

    $response = request('POST', '/api/v1/payment/hosted', $payload, $token);
    printJson($response['json'] ?? ['raw' => $response['raw']]);

    $result = $response['json']['result'] ?? null;
    if (!$result || empty($result['reference']) || empty($result['_links']['payment']['href'])) {
        throw new RuntimeException('Failed to create hosted payment.');
    }

    return $result;
}

function verifyPayment(string $token, string $reference): array
{
    printStep('3) Verify — GET /api/v1/payment/transaction/{reference}/info');

    $response = request('GET', '/api/v1/payment/transaction/' . $reference . '/info', null, $token);
    printJson($response['json'] ?? ['raw' => $response['raw']]);

    return $response['json']['result'] ?? [];
}

function main(array $argv): int
{
    $publishableKey = env('PAYZINK_PUBLISHABLE_KEY');
    $secretKey = env('PAYZINK_SECRET_KEY');

    if (!$publishableKey || !$secretKey) {
        fwrite(STDERR, "Missing credentials.\n\n");
        fwrite(STDERR, "Get sandbox keys from https://console-dev.payzink.com → Settings → API Credentials\n\n");
        fwrite(STDERR, "Run:\n");
        fwrite(STDERR, "  PAYZINK_PUBLISHABLE_KEY=pk_test_xxx PAYZINK_SECRET_KEY=sk_test_xxx php scripts/payzink-sandbox-test.php\n\n");
        return 1;
    }

    try {
        $token = getAccessToken($publishableKey, $secretKey);

        if (($argv[1] ?? '') === 'verify') {
            $reference = $argv[2] ?? '';
            if ($reference === '') {
                throw new InvalidArgumentException('Usage: php scripts/payzink-sandbox-test.php verify REFERENCE_UUID');
            }

            $result = verifyPayment($token, $reference);
            $state = $result['state'] ?? 'UNKNOWN';
            $success = in_array($state, ['PURCHASED', 'CAPTURED', 'AUTHORISED'], true);

            echo $success ? "\n✅ Payment successful (state: {$state})\n" : "\n❌ Payment not successful (state: {$state})\n";

            return $success ? 0 : 2;
        }

        $payment = createHostedPayment($token);
        $reference = $payment['reference'];
        $checkoutUrl = $payment['_links']['payment']['href'];

        printStep('Next steps');
        echo "Reference: {$reference}\n";
        echo "Checkout URL:\n{$checkoutUrl}\n\n";
        echo "1. Open the checkout URL in your browser\n";
        echo "2. Pay with sandbox test card, e.g. Visa 4111 1111 1111 1111 (exp 12/27, CVV 123)\n";
        echo "   For 3DS challenge: 4000 0000 0000 3220 (OTP: Checkout1!)\n";
        echo "3. After redirect, verify with:\n";
        echo "   PAYZINK_PUBLISHABLE_KEY=... PAYZINK_SECRET_KEY=... php scripts/payzink-sandbox-test.php verify {$reference}\n";

        return 0;
    } catch (Throwable $e) {
        fwrite(STDERR, "\nError: {$e->getMessage()}\n");
        return 1;
    }
}

exit(main($argv));
