<?php

namespace Nafezly\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Nafezly\Payments\Traits\SetVariables;
use Nafezly\Payments\Traits\SetRequiredFields;

class BaseController 
{
	use SetVariables,SetRequiredFields;
}