<!DOCTYPE html>
<html lang="{{ $language === 'ar' ? 'ar' : 'en' }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('nafezly::messages.REDIRECTING_TO_PAYMENT') }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }
        .loading {
            text-align: center;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h3 {
            margin: 10px 0;
            color: #333;
        }
        p {
            margin: 5px 0;
            color: #666;
        }
    </style>
</head>
<body onload="document.getElementById('volet-payment-form').submit()">
    <div class="loading">
        <div class="spinner"></div>
        <h3>{{ __('nafezly::messages.REDIRECTING_TO_PAYMENT') }}</h3>
        <p>{{ __('nafezly::messages.PLEASE_WAIT_REDIRECT') }}</p>
    </div>
    
    <form method="POST" action="{{ $sci_url }}" id="volet-payment-form" style="display: none;">
        @foreach($data as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
    </form>
</body>
</html>

