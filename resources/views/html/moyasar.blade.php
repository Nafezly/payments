<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moyasar Payment</title>
    <link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.14.0/moyasar.css">
    <script src="https://cdn.moyasar.com/mpf/1.14.0/moyasar.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .payment-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .payment-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .payment-header p {
            color: #666;
            font-size: 16px;
        }
        .amount-display {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        .amount-display .label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .amount-display .amount {
            color: #667eea;
            font-size: 36px;
            font-weight: bold;
        }
        .mysr-form {
            margin-top: 20px;
        }
        .loader {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loader.active {
            display: block;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .footer-note {
            text-align: center;
            margin-top: 20px;
            color: #999;
            font-size: 12px;
        }
        @media (max-width: 600px) {
            .payment-container {
                padding: 20px;
            }
            .payment-header h1 {
                font-size: 24px;
            }
            .amount-display .amount {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1>بوابة الدفع الآمنة</h1>
            <p>Moyasar Payment Gateway</p>
        </div>

        <div class="amount-display">
            <div class="label">المبلغ المطلوب</div>
            <div class="amount">{{ number_format($data['amount'] / 100, 2) }} {{ $data['currency'] }}</div>
        </div>

        <div class="mysr-form"></div>
        
        <div class="loader" id="payment-loader">
            <div class="spinner"></div>
            <p style="margin-top: 15px; color: #666;">جاري معالجة الدفع...</p>
        </div>

        <div class="footer-note">
            <p>🔒 جميع المعاملات محمية ومشفرة</p>
        </div>
    </div>

    <script>
        Moyasar.init({
            element: '.mysr-form',
            amount: {{ $data['amount'] }},
            currency: '{{ $data['currency'] }}',
            description: '{{ $data['description'] }}',
            publishable_api_key: '{{ $data['publishable_api_key'] }}',
            callback_url: '{{ $data['callback_url'] }}',
            methods: {!! json_encode($data['methods']) !!},
            @if(in_array('creditcard', $data['methods']))
            supported_networks: {!! json_encode($data['supported_networks']) !!},
            @endif
            @if(isset($data['apple_pay']))
            apple_pay: {!! json_encode($data['apple_pay']) !!},
            @endif
            @if(isset($data['metadata']))
            metadata: {!! json_encode($data['metadata']) !!},
            @endif
            on_initiating: function() {
                console.log('Payment initiated');
            },
            on_completed: function(payment) {
                console.log('Payment completed', payment);
                document.getElementById('payment-loader').classList.add('active');
                document.querySelector('.mysr-form').style.display = 'none';
                
                // Optional: Save payment ID to backend
                // You can uncomment and implement this if needed
                // savePaymentToBackend(payment);
            },
            on_failure: function(error) {
                console.error('Payment failed', error);
                alert('فشلت عملية الدفع. الرجاء المحاولة مرة أخرى.');
            }
        });

        // Optional function to save payment to backend
        // function savePaymentToBackend(payment) {
        //     fetch('/api/save-payment', {
        //         method: 'POST',
        //         headers: {
        //             'Content-Type': 'application/json',
        //             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        //         },
        //         body: JSON.stringify({
        //             payment_id: payment.id,
        //             status: payment.status,
        //             amount: payment.amount,
        //             currency: payment.currency
        //         })
        //     });
        // }
    </script>
</body>
</html>
