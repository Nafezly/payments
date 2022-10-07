<?php
namespace Nafezly\Payments\Traits;

trait SetVariables
{
    public $user_id = null;
    public $user_first_name = null;
    public $user_last_name = null;
    public $user_email = null;
    public $user_phone = null;
    public $source = null;
    public $currency = null;
    public $amount = null;

    /**
     * Sets user ID
     *
     * @param  integer  $value
     * @return $this
     */
    public function setUserId($value)
    {
        $this->user_id = $value;
        return $this;
    }

    /**
     * Sets user first name
     *
     * @param  string  $value
     * @return $this
     */
    public function setUserFirstName($value)
    {
        $this->user_first_name = $value;
        return $this;
    }

    /**
     * Sets user last name
     *
     * @param  string  $value
     * @return $this
     */
    public function setUserLastName($value)
    {
        $this->user_last_name = $value;
        return $this;
    }

    /**
     * Sets user email
     *
     * @param  string  $value
     * @return $this
     */
    public function setUserEmail($value)
    {
        $this->user_email = $value;
        return $this;
    }

    /**
     * Sets user phone
     *
     * @param  string  $value
     * @return $this
     */
    public function setUserPhone($value)
    {
        $this->user_phone = $value;
        return $this;
    }

    /**
     * Sets source
     *
     * @param  string  $value
     * @return $this
     */
    public function setSource($value)
    {
        $this->source = $value;
        return $this;
    }

    /**
     * Sets currency
     *
     * @param  string  $value
     * @return $this
     */
    public function setCurrency($value)
    {
        $this->currency = $value;
        return $this;
    }

    /**
     * Sets amount
     *
     * @param  double  $value
     * @return $this
     */
    public function setAmount($value)
    {
        $this->amount = $value;
        return $this;
    }


    /**
     * set passed vaiables to pay function to be global
     * @param $amount
     * @param null $user_id
     * @param null $user_first_name
     * @param null $user_last_name
     * @param null $user_email
     * @param null $user_phone
     * @param null $source
     * @return void
     */
    public function setPassedVariablesToGlobal($amount = null, $user_id = null, $user_first_name = null, $user_last_name = null, $user_email = null, $user_phone = null, $source = null)
    {
        $this->setAmount($amount);
        $this->setUserId($user_id);
        $this->setUserFirstName($user_first_name);
        $this->setUserLastName($user_last_name);
        $this->setUserEmail($user_email);
        $this->setUserPhone($user_phone);
        $this->setSource($source);
    }
    

}
