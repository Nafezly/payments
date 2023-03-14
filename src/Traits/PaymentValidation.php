<?php

namespace Nafezly\Payments\Traits;

use Illuminate\Support\Facades\Validator;

trait PaymentValidation
{

    public function validate()
    {

        $validation = Validator::make($this->response->request, $this->validations);

        if ($validation->fails()) {

            $this->response->status = false;
            $this->response->errors = $validation->errors()->messages();
            throw \Illuminate\Validation\ValidationException::withMessages($this->response->errors);

        }

        $this->response->data = $validation->validated();

    }

}
