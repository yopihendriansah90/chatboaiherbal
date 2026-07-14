<?php

namespace App\Contracts;

use App\Data\ParsedMessage;
use App\Data\ResponsePlan;
use App\Models\BusinessProfile;

interface DecisionEngineContract
{
    public function buildPlan(ParsedMessage $message, array $state, BusinessProfile $business): ResponsePlan;
}
