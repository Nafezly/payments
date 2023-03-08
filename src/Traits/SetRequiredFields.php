<?php
namespace Nafezly\Payments\Traits;

use Nafezly\Payments\Exceptions\MissingPaymentInfoException;

trait SetRequiredFields
{
    /**
     * Check required fields and throw Exception if null
     *
     * @param  array $required_fields
     * @param  string  $gatway_name
     * @return void
     */
    public function checkRequiredFields($required_fields, $gatway_name)
    {
     
        $amount = $this->amount ?? null;
        $user_id = $this->user_id ?? null;
        $user_first_name = $this->user_first_name ?? null;
        $user_last_name = $this->user_last_name ?? null;
        $user_email = $this->user_email ?? null;
        $user_phone = $this->user_phone ?? null;
        $source = $this->source ?? null;
        foreach($required_fields as $field){
            $this->{$field} = $this->{$field} ?? ${$field};
            if (is_null($this->{$field})) throw new MissingPaymentInfoException($field, $gatway_name);
        }
    }

}
