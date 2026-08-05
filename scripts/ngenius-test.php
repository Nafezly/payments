#!/usr/bin/env php
<?php

/**
 * N-Genius hosted checkout smoke test (no Laravel required).
 *
 * Usage:
 *   NGENIUS_API_KEY=xxx NGENIUS_OUTLET_ID=xxx php scripts/ngenius-test.php
 *   NGENIUS_API_KEY=xxx NGENIUS_OUTLET_ID=xxx php scripts/ngenius-test.php verify ORDER_ID
 */

declare(strict_types=1);

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function request(string $method, string $url, ?array $body = null, array $headers = []): array
{
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

    return [
        'http_code' => $httpCode,
        'raw' => $raw,
        'json' => json_decode($raw, true),
    ];
}

function accessToken(string $gatewayUrl, string $apiKey, string $realm): string
{
    $response = request('POST', rtrim($gatewayUrl, '/') . '/identity/auth/access-token', [
        'realmName' => $realm,
    ], [
        'Accept: application/vnd.ni-identity.v1+json',
        'Authorization: Basic ' . $apiKey,
        'Content-Type: application/vnd.ni-identity.v1+json',
    ]);

    $token = data_get($response, 'json.access_token');

    if (!$token) {
        fwrite(STDERR, "Token failed (HTTP {$response['http_code']}): {$response['raw']}\n");
        exit(1);
    }

    return $token;
}

function data_get($target, ?string $key, $default = null)
{
    if (!is_array($target) || $key === null) {
        return $default;
    }

    foreach (explode('.', $key) as $segment) {
        if (!is_array($target) || !array_key_exists($segment, $target)) {
            return $default;
        }
        $target = $target[$segment];
    }

    return $target;
}

$apiKey = env('NGENIUS_API_KEY');
$outletId = env('NGENIUS_OUTLET_ID');
$realm = env('NGENIUS_REALM', 'NetworkInternational');
$gatewayUrl = env('NGENIUS_GATEWAY_URL', 'https://api-gateway.ngenius-payments.com');
$currency = strtoupper(env('NGENIUS_CURRENCY', 'AED'));

if (!$apiKey || !$outletId) {
    fwrite(STDERR, "Set NGENIUS_API_KEY and NGENIUS_OUTLET_ID.\n");
    exit(1);
}

$command = $argv[1] ?? 'pay';

if ($command === 'verify') {
    $orderId = $argv[2] ?? null;
    if (!$orderId) {
        fwrite(STDERR, "Usage: php scripts/ngenius-test.php verify ORDER_ID\n");
        exit(1);
    }

    $token = accessToken($gatewayUrl, $apiKey, $realm);
    $response = request(
        'GET',
        rtrim($gatewayUrl, '/') . '/transactions/outlets/' . $outletId . '/orders/' . $orderId,
        null,
        [
            'Authorization: Bearer ' . $token,
            'Accept: application/vnd.ni-payment.v2+json',
        ]
    );

    echo json_encode($response['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit($response['http_code'] >= 200 && $response['http_code'] < 300 ? 0 : 1);
}

$amount = (float) env('NGENIUS_TEST_AMOUNT', '10.00');
$email = env('NGENIUS_TEST_EMAIL', 'test@example.com');
$orderId = 'tpy_test_' . time();
$token = accessToken($gatewayUrl, $apiKey, $realm);

$response = request(
    'POST',
    rtrim($gatewayUrl, '/') . '/transactions/outlets/' . $outletId . '/orders',
    [
        'action' => 'PURCHASE',
        'amount' => [
            'currencyCode' => $currency,
            'value' => (int) round($amount * 100),
        ],
        'emailAddress' => $email,
        'merchantAttributes' => [
            'redirectUrl' => 'https://example.com/payments/verify/ngenius?payment_id=' . $orderId,
        ],
    ],
    [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/vnd.ni-payment.v2+json',
        'Accept: application/vnd.ni-payment.v2+json',
    ]
);

echo "HTTP {$response['http_code']}\n";
echo json_encode($response['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

$paymentUrl = data_get($response['json'], '_links.payment.href');
if ($paymentUrl) {
    echo "\nPayment URL: {$paymentUrl}\n";
    echo "Order ID: " . data_get($response['json'], 'reference', $orderId) . "\n";
}

exit($response['http_code'] >= 200 && $response['http_code'] < 300 ? 0 : 1);
