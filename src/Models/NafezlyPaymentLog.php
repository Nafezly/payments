<?php

namespace Nafezly\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class NafezlyPaymentLog extends Model
{
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $guarded = ['id'];
}