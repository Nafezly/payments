<?php

/**
 * Moyasar Payment Gateway - Simple Example
 * 
 * This example demonstrates how to integrate Moyasar payment gateway
 * in your Laravel application.
 */

// ==========================================
// Example 1: Basic Payment with Credit Card
// ==========================================

use Nafezly\Payments\Classes\MoyasarPayment;

// Initialize payment
$moyasar = new MoyasarPayment();

// Process payment
$paymentResult = $moyasar
    ->setAmount(100)                        // Amount in SAR
    ->setCurrency('SAR')                    // Currency code
    ->setUserFirstName('أحمد')              // Customer first name
    ->setUserLastName('محمد')                // Customer last name
    ->setUserEmail('ahmed@example.com')     // Customer email
    ->setUserPhone('966501234567')          // Customer phone
    ->pay();

// Display payment form
if (!empty($paymentResult['html'])) {
    echo $paymentResult['html'];
}

// ==========================================
// Example 2: Payment with STC Pay
// ==========================================

$moyasarStcPay = new MoyasarPayment();

$stcPayResult = $moyasarStcPay
    ->setAmount(250)
    ->setSource('stcpay')                   // Specify payment method
    ->setUserFirstName('فاطمة')
    ->setUserLastName('علي')
    ->setUserEmail('fatima@example.com')
    ->setUserPhone('966505555555')
    ->pay();

echo $stcPayResult['html'];

// ==========================================
// Example 3: Payment with Apple Pay
// ==========================================

$moyasarApplePay = new MoyasarPayment();

$applePayResult = $moyasarApplePay
    ->setAmount(500)
    ->setSource('applepay')
    ->setUserFirstName('خالد')
    ->setUserLastName('حسن')
    ->setUserEmail('khaled@example.com')
    ->pay();

echo $applePayResult['html'];

// ==========================================
// Example 4: Payment with All Methods
// ==========================================

$moyasarAllMethods = new MoyasarPayment();

$allMethodsResult = $moyasarAllMethods
    ->setAmount(1000)
    ->setUserFirstName('سارة')
    ->setUserLastName('عبدالله')
    ->setUserEmail('sara@example.com')
    ->setUserPhone('966502222222')
    // Don't specify source to enable all methods
    ->pay();

echo $allMethodsResult['html'];

// ==========================================
// Example 5: Payment Verification in Controller
// ==========================================

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Initiate payment
     */
    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
        ]);

        $payment = new MoyasarPayment();
        
        $result = $payment
            ->setAmount($validated['amount'])
            ->setUserFirstName($validated['first_name'])
            ->setUserLastName($validated['last_name'])
            ->setUserEmail($validated['email'])
            ->setUserPhone($validated['phone'])
            ->pay();

        if (!empty($result['html'])) {
            return view('payments.moyasar', [
                'paymentHtml' => $result['html'],
                'paymentId' => $result['payment_id']
            ]);
        }

        return back()->with('error', 'Failed to initialize payment');
    }

    /**
     * Verify payment after callback
     */
    public function verifyPayment(Request $request)
    {
        $payment = new MoyasarPayment();
        $result = $payment->verify($request);

        if ($result['success']) {
            // Payment successful
            $paymentData = $result['process_data'];
            
            // Save transaction to database
            \DB::table('transactions')->insert([
                'payment_id' => $result['payment_id'],
                'amount' => $paymentData['amount'] / 100, // Convert from smallest unit
                'currency' => $paymentData['currency'],
                'status' => 'paid',
                'gateway' => 'moyasar',
                'payment_method' => $paymentData['source']['type'] ?? 'unknown',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Send confirmation email
            // Mail::to($user->email)->send(new PaymentConfirmation($paymentData));

            return redirect()->route('payment.success')
                ->with('success', 'Payment completed successfully!')
                ->with('payment_id', $result['payment_id']);
        } else {
            // Payment failed
            \Log::error('Moyasar payment failed', [
                'payment_id' => $result['payment_id'],
                'error' => $result['process_data']
            ]);

            return redirect()->route('payment.failed')
                ->with('error', 'Payment failed. Please try again.');
        }
    }
}

// ==========================================
// Example 6: Custom Payment with Metadata
// ==========================================

$moyasarWithMetadata = new MoyasarPayment();

$metadataResult = $moyasarWithMetadata
    ->setAmount(750)
    ->setUserId(123)                        // Optional: User ID
    ->setUserFirstName('عمر')
    ->setUserLastName('السعيد')
    ->setUserEmail('omar@example.com')
    ->setUserPhone('966503333333')
    ->setSource('creditcard')               // Credit card only
    ->pay();

echo $metadataResult['html'];

// ==========================================
// Example 7: Payment with Custom Payment ID
// ==========================================

$moyasarCustomId = new MoyasarPayment();

$customIdResult = $moyasarCustomId
    ->setPaymentId('ORDER-2024-00123')      // Custom payment reference
    ->setAmount(300)
    ->setUserFirstName('نورة')
    ->setUserLastName('المطيري')
    ->setUserEmail('noura@example.com')
    ->pay();

echo $customIdResult['html'];

// ==========================================
// Example 8: Payment in Different Currency
// ==========================================

$moyasarUSD = new MoyasarPayment();

$usdResult = $moyasarUSD
    ->setAmount(50)                         // $50 USD
    ->setCurrency('USD')
    ->setUserFirstName('Mohammed')
    ->setUserLastName('Ahmed')
    ->setUserEmail('mohammed@example.com')
    ->pay();

echo $usdResult['html'];

// ==========================================
// Example 9: Blade Template for Payment Form
// ==========================================

/*
<!-- resources/views/payments/moyasar.blade.php -->

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الدفع عبر Moyasar</title>
</head>
<body>
    <div class="container">
        <h1>إتمام عملية الدفع</h1>
        
        @if(isset($paymentHtml))
            {!! $paymentHtml !!}
        @else
            <p>حدث خطأ في تحميل نموذج الدفع</p>
        @endif
    </div>
</body>
</html>
*/

// ==========================================
// Example 10: API Routes Setup
// ==========================================

/*
// routes/web.php

use App\Http\Controllers\PaymentController;

// Payment routes
Route::prefix('payment')->group(function () {
    // Create payment
    Route::post('/moyasar/create', [PaymentController::class, 'createPayment'])
        ->name('payment.moyasar.create');
    
    // Verify payment (callback URL)
    Route::get('/verify/moyasar', [PaymentController::class, 'verifyPayment'])
        ->name('verify-payment');
    
    // Success page
    Route::get('/success', [PaymentController::class, 'paymentSuccess'])
        ->name('payment.success');
    
    // Failed page
    Route::get('/failed', [PaymentController::class, 'paymentFailed'])
        ->name('payment.failed');
});
*/

// ==========================================
// Example 11: Environment Configuration
// ==========================================

/*
Add to .env file:

MOYASAR_SECRET_KEY=sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxx
MOYASAR_PUBLISHABLE_KEY=pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxx
MOYASAR_CURRENCY=SAR

Note: Moyasar uses two types of keys:
- Secret Key (sk_test_xxx or sk_live_xxx): Used for backend API operations
- Publishable Key (pk_test_xxx or pk_live_xxx): Used in frontend payment form
*/

// ==========================================
// Example 12: Error Handling
// ==========================================

try {
    $payment = new MoyasarPayment();
    
    $result = $payment
        ->setAmount(100)
        ->setUserFirstName('Test')
        ->setUserLastName('User')
        ->setUserEmail('test@example.com')
        ->pay();
    
    if (isset($result['error']) && !empty($result['error'])) {
        // Handle payment initiation error
        \Log::error('Moyasar payment initialization failed', [
            'error' => $result['error'],
            'payment_id' => $result['payment_id'] ?? null
        ]);
        
        return back()->with('error', 'Unable to process payment. Please try again.');
    }
    
    return view('payments.moyasar', ['paymentHtml' => $result['html']]);
    
} catch (\Exception $e) {
    \Log::error('Moyasar payment exception', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return back()->with('error', 'An error occurred. Please contact support.');
}
