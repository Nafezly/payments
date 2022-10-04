<?php
namespace Nafezly\Payments\Traits;

trait SetVariables
{
    private $user_id = null;
    private $user_first_name = null;
    private $user_last_name = null;
    private $user_email = null;
    private $user_phone = null;
    private $source = null;
    private $currency = null;
    private $amount = null;

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
    public function setUserFisrtName($value)
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

}
