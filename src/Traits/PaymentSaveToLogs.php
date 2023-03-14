<?php

namespace Nafezly\Payments\Traits;


use Nafezly\Payments\Models\NafezlyPaymentLog;

trait PaymentSaveToLogs
{

    /**
     * @param mixed $payload
     * @param mixed $response
     * @return void
     */
    public function saveToLogs(): void
    {
        $this->response->status = false;

        NafezlyPaymentLog::create(
            [
                "status" => false,
                "payload" => json_encode($this->response->request),
                "response" => json_encode($this->response->message),
            ]
        );
    }

}
