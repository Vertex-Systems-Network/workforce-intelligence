<?php
namespace App\Services\Commerce;
use App\Models\User;
/** Provides platform operator service behavior within the WorkIntel application. */ class PlatformOperatorService
{
    /** Determines whether the is operator condition is satisfied. */ public function isOperator(?User $user):bool
    {
        if(!$user)return false;
        $emails=array_map('strtolower',config('workintel.commerce.operator_emails',[]));
        return in_array(strtolower($user->email),$emails,true);
    }
    /** Handles the assert operation for the current WorkIntel workflow. */ public function assert(?User $user):void{abort_unless($this->isOperator($user),403,'Platform operator access is required.');}
}
