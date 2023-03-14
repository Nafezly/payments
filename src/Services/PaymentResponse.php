<?php

namespace Nafezly\Payments\Services;

use Illuminate\View\View;
use Nafezly\Payments\Models\NafezlyPayment;
use Nafezly\Payments\Models\Payment;

class PaymentResponse
{

    public bool $status = true;
    public string $message = '';
    public array $data=[];
    public array $request=[];
    public NafezlyPayment $payment;
    public string $payment_id = '';
    public View|null $html = null;
    public string $redirect_url = '';
    public array $errors = [];
}
