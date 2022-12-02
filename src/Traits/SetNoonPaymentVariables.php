<?php
namespace Nafezly\Payments\Traits;

trait SetNoonPaymentVariables
{
    public $order_name = null;

    public $configuration_local = null;

    public $subscription_amount = null;

    public $subscription_name = null;

    public $subscription_valid_till = null;

    public $subscription_identifier = null;

    /**
     * Sets order name
     *
     * @param  string  $value
     * @return $this
     */
    public function setOrderName($value)
    {
        $this->order_name = $value;
        return $this;
    }

    /**
     * Sets configuration local
     *
     * @param  string  $value
     * @return $this
     */
    public function setConfigurationLocal($value)
    {
        $this->configuration_local = $value;
        return $this;
    }
    
    /**
     * Sets subscription amount
     *
     * @param  float  $value
     * @return $this
     */
    public function setSubscriptionAmount($value)
    {
        $this->subscription_amount = $value;
        return $this;
    }
    
    /**
     * Sets subscription name
     *
     * @param  string  $value
     * @return $this
     */
    public function setSubscriptionName($value)
    {
        $this->subscription_name = $value;
        return $this;
    }
    
    /**
     * Sets subscription valid till
     *
     * @param  string  $value
     * @return $this
     */
    public function setSubscriptionValidTill($value)
    {
        $this->subscription_valid_till = $value;
        return $this;
    }
    
    /**
     * Sets subscription identifier
     *
     * @param  string  $value
     * @return $this
     */
    public function setSubscriptionIdentifier($value)
    {
        $this->subscription_identifier = $value;
        return $this;
    }
}
