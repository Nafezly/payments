# Moyasar Payment Gateway Integration

## Overview
This document explains how to integrate and use the Moyasar payment gateway with the Nafezly Payments package.

## Features
- Credit Card Payments (Visa, Mastercard, Mada)
- Apple Pay
- STC Pay
- Secure payment processing
- Automatic payment verification
- Multi-currency support

## Configuration

### Environment Variables
Add the following variables to your `.env` file:

```env
# Moyasar Configuration
MOYASAR_API_KEY=your_api_key_here
MOYASAR_SECRET_KEY=your_secret_key_here
MOYASAR_PUBLISHABLE_KEY=your_publishable_key_here
MOYASAR_CURRENCY=SAR
```

### Getting API Keys
1. Sign up for a Moyasar account at [https://dashboard.moyasar.com/register/new](https://dashboard.moyasar.com/register/new)
2. Navigate to Settings > API Keys
3. Copy your API Key and Publishable Key
4. For testing, use test keys (starts with `pk_test_` and `sk_test_`)
5. For production, use live keys (starts with `pk_live_` and `sk_live_`)

## Usage

### Basic Payment

```php
use Nafezly\Payments\Classes\MoyasarPayment;

$payment = new MoyasarPayment();

// Make a payment
$result = $payment
    ->setAmount(100) // Amount in SAR (will be converted to Halalas automatically)
    ->setCurrency('SAR')
    ->setUserFirstName('Ahmed')
    ->setUserLastName('Mohammed')
    ->setUserEmail('ahmed@example.com')
    ->setUserPhone('966501234567')
    ->pay();

// Display the payment form
echo $result['html'];
```

### Payment with Specific Method

```php
// Credit Card Only
$result = $payment
    ->setAmount(100)
    ->setSource('creditcard')
    ->pay();

// STC Pay Only
$result = $payment
    ->setAmount(100)
    ->setSource('stcpay')
    ->pay();

// Apple Pay Only
$result = $payment
    ->setAmount(100)
    ->setSource('applepay')
    ->pay();
```

### Payment Verification

After the user completes the payment, they will be redirected to your callback URL with the payment ID as a query parameter.

```php
use Illuminate\Http\Request;
use Nafezly\Payments\Classes\MoyasarPayment;

public function verifyPayment(Request $request)
{
    $payment = new MoyasarPayment();
    $result = $payment->verify($request);
    
    if ($result['success']) {
        // Payment successful
        $paymentId = $result['payment_id'];
        $paymentData = $result['process_data'];
        
        // Update your order status, send confirmation email, etc.
        
        return redirect()->route('payment.success');
    } else {
        // Payment failed
        return redirect()->route('payment.failed');
    }
}
```

## Payment Response Structure

### Success Response
```php
[
    'success' => true,
    'payment_id' => 'abc123...',
    'message' => 'Payment completed successfully',
    'process_data' => [
        'id' => 'abc123...',
        'status' => 'paid',
        'amount' => 10000, // Amount in smallest currency unit (Halalas)
        'currency' => 'SAR',
        'description' => 'Payment description',
        'created_at' => '2024-01-01T12:00:00.000Z',
        // ... other payment details
    ]
]
```

### Failed Response
```php
[
    'success' => false,
    'payment_id' => 'abc123...',
    'message' => 'Payment failed',
    'process_data' => [
        'id' => 'abc123...',
        'status' => 'failed',
        // ... error details
    ]
]
```

## Currency Support

The amount should be specified in the main currency unit (e.g., SAR, USD). The package automatically converts it to the smallest unit required by Moyasar:

- 1 SAR = 100 Halalas
- 1 KWD = 1000 Fils
- 1 USD = 100 Cents

Supported currencies:
- SAR (Saudi Riyal)
- KWD (Kuwaiti Dinar)
- USD (US Dollar)
- EUR (Euro)
- And many more...

## Testing

### Test Cards

For testing purposes, you can use the following test card numbers:

**Successful Payment:**
- Card Number: `4111 1111 1111 1111`
- CVV: Any 3 digits
- Expiry: Any future date

**Failed Payment:**
- Card Number: `4000 0000 0000 0002`
- CVV: Any 3 digits
- Expiry: Any future date

**3D Secure Required:**
- Card Number: `4000 0000 0000 3063`
- CVV: Any 3 digits
- Expiry: Any future date

### Test STC Pay
- Phone Number: `0500000001`
- OTP: `1234`

## Security Notes

1. **Never expose your Secret Key** - Keep it secure in your `.env` file
2. **Always verify payments** on the server side before fulfilling orders
3. **Use HTTPS** in production
4. **Validate callback URLs** to ensure they come from Moyasar
5. **Log all transactions** for audit purposes

## Webhook Integration (Optional)

For real-time payment notifications, you can set up webhooks:

1. Go to your Moyasar Dashboard
2. Navigate to Settings > Webhooks
3. Add your webhook URL (e.g., `https://yourdomain.com/webhooks/moyasar`)
4. Select the events you want to receive notifications for

## Troubleshooting

### Common Issues

**Issue: Payment form not displaying**
- Check that your publishable key is correct
- Verify that the Moyasar CSS and JS files are loading
- Check browser console for errors

**Issue: Payment verification fails**
- Ensure your API key has the correct permissions
- Check that the payment ID is being passed correctly
- Verify your server can make outbound HTTPS requests

**Issue: Amount mismatch**
- Remember that amounts are automatically converted to the smallest unit
- Always verify the amount on the server side after payment

## Support

For issues specific to Moyasar integration:
- Moyasar Documentation: [https://docs.moyasar.com](https://docs.moyasar.com)
- Moyasar Support: [care@moyasar.com](mailto:care@moyasar.com)
- Phone: 800 1111 848

For package-related issues:
- Create an issue on GitHub
- Contact the package maintainer

## Example Implementation

```php
// routes/web.php
Route::post('/payment/moyasar', [PaymentController::class, 'initiatePayment'])->name('payment.moyasar');
Route::get('/payment/verify/moyasar', [PaymentController::class, 'verifyPayment'])->name('verify-payment');

// app/Http/Controllers/PaymentController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Nafezly\Payments\Classes\MoyasarPayment;

class PaymentController extends Controller
{
    public function initiatePayment(Request $request)
    {
        $payment = new MoyasarPayment();
        
        $result = $payment
            ->setAmount($request->amount)
            ->setUserFirstName($request->first_name)
            ->setUserLastName($request->last_name)
            ->setUserEmail($request->email)
            ->setUserPhone($request->phone)
            ->pay();
        
        return view('payment.moyasar', ['payment' => $result]);
    }
    
    public function verifyPayment(Request $request)
    {
        $payment = new MoyasarPayment();
        $result = $payment->verify($request);
        
        if ($result['success']) {
            // Save to database
            \App\Models\Transaction::create([
                'payment_id' => $result['payment_id'],
                'amount' => $result['process_data']['amount'] / 100,
                'currency' => $result['process_data']['currency'],
                'status' => 'paid',
                'gateway' => 'moyasar',
            ]);
            
            return view('payment.success', ['payment' => $result]);
        }
        
        return view('payment.failed', ['payment' => $result]);
    }
}
```

## License

This integration follows the same license as the Nafezly Payments package.
