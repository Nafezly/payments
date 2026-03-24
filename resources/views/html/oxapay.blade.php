<!DOCTYPE html>
<html lang="{{ $language === 'ar' ? 'ar' : 'en' }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('nafezly::messages.OXAPAY_PAYMENT_INSTRUCTIONS') }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #111827 55%, #1f2937 100%);
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 760px;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28);
        }

        .header {
            padding: 28px 28px 18px;
            background: linear-gradient(135deg, #0ea5e9, #2563eb);
            color: #ffffff;
        }

        .header h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }

        .header p {
            margin: 0;
            opacity: 0.92;
            line-height: 1.6;
        }

        .body {
            padding: 28px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .qr-card,
        .details-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 22px;
        }

        .qr-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .qr-card img {
            width: 220px;
            max-width: 100%;
            border-radius: 18px;
            background: #ffffff;
            padding: 14px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        .type-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .amount {
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 18px;
        }

        .amount-subtitle {
            margin-top: 8px;
            font-size: 14px;
            color: #64748b;
        }

        .detail-row {
            padding: 14px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            display: block;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .detail-value {
            font-size: 15px;
            color: #0f172a;
            word-break: break-word;
            line-height: 1.6;
        }

        .address-box {
            display: flex;
            gap: 10px;
            align-items: stretch;
            margin-top: 10px;
        }

        .address-box code {
            flex: 1;
            padding: 12px 14px;
            border-radius: 14px;
            background: #0f172a;
            color: #f8fafc;
            font-size: 13px;
            line-height: 1.6;
            word-break: break-all;
        }

        .button {
            border: 0;
            border-radius: 14px;
            padding: 12px 16px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .button:hover {
            transform: translateY(-1px);
            opacity: 0.96;
        }

        .button-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .button-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .empty-qr {
            width: 220px;
            max-width: 100%;
            min-height: 220px;
            border-radius: 18px;
            border: 2px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            background: #ffffff;
            padding: 20px;
            text-align: center;
            line-height: 1.6;
        }

        @media (max-width: 720px) {
            .body {
                grid-template-columns: 1fr;
            }

            .header,
            .body {
                padding: 20px;
            }

            .address-box {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
@php
    $payAmount = $payment_data['pay_amount'] ?? null;
    $payCurrency = $payment_data['pay_currency'] ?? null;
    $amount = $payment_data['amount'] ?? null;
    $currency = $payment_data['currency'] ?? null;
    $address = $payment_data['address'] ?? null;
    $network = $payment_data['network'] ?? null;
    $trackId = $payment_data['track_id'] ?? null;
    $orderId = $payment_data['order_id'] ?? null;
    $createdAt = isset($payment_data['date']) ? date('Y-m-d H:i:s', (int) $payment_data['date']) : null;
    $expiresAt = isset($payment_data['expired_at']) ? date('Y-m-d H:i:s', (int) $payment_data['expired_at']) : null;
    $qrCode = $payment_data['qr_code'] ?? null;
@endphp

<div class="card">
    <div class="header">
        <div class="type-badge">{{ strtoupper(str_replace('_', ' ', $type)) }}</div>
        <h1>{{ __('nafezly::messages.OXAPAY_PAYMENT_INSTRUCTIONS') }}</h1>
        <p>{{ __('nafezly::messages.OXAPAY_PAYMENT_HELP') }}</p>
    </div>

    <div class="body">
        <div class="qr-card">
            @if($qrCode)
                <img src="{{ $qrCode }}" alt="OxaPay QR Code">
            @else
                <div class="empty-qr">{{ __('nafezly::messages.OXAPAY_OPEN_QR') }}</div>
            @endif

            @if($payAmount && $payCurrency)
                <div class="amount">{{ $payAmount }} {{ $payCurrency }}</div>
                <div class="amount-subtitle">{{ __('nafezly::messages.OXAPAY_PAY_AMOUNT') }}</div>
            @elseif($amount && $currency)
                <div class="amount">{{ $amount }} {{ $currency }}</div>
                <div class="amount-subtitle">{{ __('nafezly::messages.OXAPAY_AMOUNT') }}</div>
            @endif

            <div class="actions">
                @if($qrCode)
                    <a class="button button-primary" href="{{ $qrCode }}" target="_blank" rel="noopener noreferrer">
                        {{ __('nafezly::messages.OXAPAY_OPEN_QR') }}
                    </a>
                @endif
                @if($address)
                    <button type="button" class="button button-secondary" onclick="copyOxaPayAddress()">
                        {{ __('nafezly::messages.OXAPAY_COPY_ADDRESS') }}
                    </button>
                @endif
            </div>
        </div>

        <div class="details-card">
            <div class="detail-row">
                <span class="detail-label">{{ __('nafezly::messages.OXAPAY_TYPE') }}</span>
                <span class="detail-value">{{ strtoupper(str_replace('_', ' ', $type)) }}</span>
            </div>

            @if($amount && $currency)
                <div class="detail-row">
                    <span class="detail-label">{{ __('nafezly::messages.OXAPAY_AMOUNT') }}</span>
                    <span class="detail-value">{{ $amount }} {{ $currency }}</span>
                </div>
            @endif

            @if($payAmount && $payCurrency)
                <div class="detail-row">
                    <span class="detail-label">{{ __('nafezly::messages.OXAPAY_PAY_AMOUNT') }}</span>
                    <span class="detail-value">{{ $payAmount }} {{ $payCurrency }}</span>
                </div>
            @endif

            @if($network)
                <div class="detail-row">
                    <span class="detail-label">{{ __('nafezly::messages.OXAPAY_NETWORK') }}</span>
                    <span class="detail-value">{{ $network }}</span>
                </div>
            @endif

            @if($address)
                <div class="detail-row">
                    <span class="detail-label">{{ __('nafezly::messages.OXAPAY_ADDRESS') }}</span>
                    <div class="address-box">
                        <code id="oxapay-address">{{ $address }}</code>
                    </div>
                </div>
            @endif

            @if($trackId)
                <div class="detail-row">
                    <span class="detail-label">{{ __('nafezly::messages.OXAPAY_TRACK_ID') }}</span>
                    <span class="detail-value">{{ $trackId }}</span>
                </div>
            @endif

            @if($orderId)
                <div class="detail-row">
                    <span class="detail-label">{{ __('nafezly::messages.OXAPAY_ORDER_ID') }}</span>
                    <span class="detail-value">{{ $orderId }}</span>
                </div>
            @endif

            @if($createdAt)
                <div class="detail-row">
                    <span class="detail-label">{{ __('nafezly::messages.OXAPAY_CREATED_AT') }}</span>
                    <span class="detail-value">{{ $createdAt }}</span>
                </div>
            @endif

            @if($expiresAt)
                <div class="detail-row">
                    <span class="detail-label">{{ __('nafezly::messages.OXAPAY_EXPIRES_AT') }}</span>
                    <span class="detail-value">{{ $expiresAt }}</span>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function copyOxaPayAddress() {
        const address = @json($address);
        if (!address) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(address);
            return;
        }

        const tempInput = document.createElement('textarea');
        tempInput.value = address;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
    }
</script>
</body>
</html>
