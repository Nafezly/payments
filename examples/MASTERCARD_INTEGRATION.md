# Mastercard Integration (Hosted Checkout + Token Recurring)

## Environment Variables

```env
MASTERCARD_MERCHANT_ID=YOUR_MERCHANT_ID
MASTERCARD_API_PASSWORD=YOUR_API_PASSWORD
MASTERCARD_BASE_URL=https://test-gateway.mastercard.com
MASTERCARD_API_VERSION=100
MASTERCARD_CURRENCY=USD
MASTERCARD_OPERATION=PAY
MASTERCARD_SAVE_TOKEN=false
```

## One-time Payment

```php
use Nafezly\Payments\Classes\MastercardPayment;

$gateway = new MastercardPayment();

$response = $gateway->setUserId($user->id)
    ->setUserFirstName($user->first_name)
    ->setUserLastName($user->last_name)
    ->setUserEmail($user->email)
    ->setUserPhone($user->phone)
    ->setAmount(50.00)
    ->setCurrency('USD')
    ->setOperation('PAY')
    ->pay();

// render $response['html'] in your payment view
```

## Verify Callback

```php
public function payment_verify(Request $request, $payment)
{
    if ($payment === 'mastercard') {
        $verify = (new MastercardPayment())->verify($request);

        // success / failed
        // token may exist at: $verify['process_data']['tokenization']['token']
    }
}
```

## Recurring Charge by Token

```php
use Nafezly\Payments\Classes\MastercardPayment;

$gateway = new MastercardPayment();

$charge = $gateway->chargeByToken(100.00, $storedToken, 'order_1001', 'USD', 'PAY');
```

## Notes

- Package does not store tokens in database.
- Store token in your project database after successful verify.
- Use your scheduler/cron in your application to run recurring automatic charges with `chargeByToken`.
