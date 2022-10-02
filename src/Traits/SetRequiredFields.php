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
     * @param  array  $arguments
     * @return void
     */
    public function checkRequiredFields($required_fields, $gatway_name, $arguments)
    {
        $amount = $arguments[0] ?? null;
        $user_id = $arguments[1] ?? null;
        $user_first_name = $arguments[2] ?? null;
        $user_last_name = $arguments[3] ?? null;
        $user_email = $arguments[4] ?? null;
        $user_phone = $arguments[5] ?? null;
        $source = $arguments[6] ?? null;
        foreach($required_fields as $field){
            $this->{$field} = $this->{$field} ?? ${$field};
            if (is_null($this->{$field})) throw new MissingPaymentInfoException($field, $gatway_name);
        }
    }

}
