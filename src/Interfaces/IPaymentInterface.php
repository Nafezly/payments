<?php

namespace Nafezly\Payments\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface IPaymentInterface
{

    /**
     * this function responsible to validate and get only validated data for security purpose
     * @param array $attributes
     * @return $this
     * @throws ValidationException
     *
     */
    public function validate();

    public function setRequest(array $attributes): self;
    public function setBuyerModel(Model $buyer): self;

    public function pay(): self;

    public function verify(): self;

    /**
     * TODO: Implement events on pay validate
     * puplish class and enums
     * check html and api versions
     * documentation
     * check language typo
     * save to logs
     * logs and payment table migrations
     * long featuers
     * check php8 and 7
     * check save to logs as json
     * add verfy with interface and code to paid
     * check return array error validation
     * check for data as collection
     *
     *
     * docs to do
     * add state package
     * add payment method like state
     * */

}
