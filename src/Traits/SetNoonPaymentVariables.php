<?php
namespace Nafezly\Payments\Traits;

trait SetNoonPaymentVariables
{
    public $order_name = null;

    public $configuration_local = null;

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
}
