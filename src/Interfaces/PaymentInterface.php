<?php

namespace Nafezly\Payments\Interfaces;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

interface PaymentInterface
{
    /**
     * @param $amount
     * @param $user_id
     * @param $user_first_name
     * @param $user_last_name
     * @param $user_email
     * @param $user_phone
     * @param null $source
     * @return array|Application|RedirectResponse|Redirector
     */
    public function pay($amount, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null);

    public function verify(Request $request);
}