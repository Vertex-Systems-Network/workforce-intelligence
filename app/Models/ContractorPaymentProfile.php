<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Provides contractor payment profile behavior within the WorkIntel application. */ class ContractorPaymentProfile extends Model{protected $fillable=['workspace_id','member_id','vendor_name','tax_identifier','payment_terms','payment_method','bank_reference','withholding_enabled','withholding_percent'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['bank_reference'=>'array','withholding_enabled'=>'boolean','withholding_percent'=>'decimal:4'];}}
