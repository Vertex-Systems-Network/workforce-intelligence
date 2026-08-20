<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
/** Provides performance review answer behavior within the WorkIntel application. */ class PerformanceReviewAnswer extends Model{protected $fillable=['performance_review_id','reviewer_member_id','reviewer_type','question_key','question_text','rating','response'];/** Defines attribute casting rules for the model. */ protected function casts():array{return ['rating'=>'decimal:2'];}}
