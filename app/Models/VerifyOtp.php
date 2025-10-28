<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerifyOtp extends Model
{
    use HasFactory;

    public $table = 'verify_otp';

    protected $fillable = [
        'otp',
        'is_verify',
        'phone',
        'email',
        'type',
        'expires_at',
        'verify_at',
    ];
}
