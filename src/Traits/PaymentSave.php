<?php

namespace Nafezly\Payments\Traits;

use Illuminate\Database\Eloquent\Model;
use Nafezly\Payments\Enums\PaymentStatusEnum;
use Nafezly\Payments\Models\NafezlyPayment;

trait PaymentSave
{

    /**
     * @return $this
     */
    public function saveToPayment()
    {

        $this->response->payment = NafezlyPayment::create(
            [
                "model_id" => $this->buyer?->id,
                "model_table" => $this->buyer?->getTable(),
                "order_id" => $this->response->data['order_id'],
                "order_table" => $this->response->data['order_table'],
                "payment_method" => self::PPAYMENT_METHOD,
                "payment_status" => PaymentStatusEnum::UNPAID,
                "transaction_code" => $this->response->data['transaction_code'],
                "amount" => $this->response->data['amount'],
                "notes" => (array_key_exists('notes',$this->response->data))?$this->response->data['notes']:null
            ]
        );


    }

    /**
     * @return $this
     */

    public function updateToPayment(string $status){

        $this->response->payment=NafezlyPayment::where('transaction_code',$this->response->data['transaction_code'])
            ->where('amount',$this->response->data['amount'])
            ->firstOrFail();

        $this->response->payment->payment_status=$status;
        $this->response->payment->save();

    }


}
