<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Relevant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'first_name',
        'last_name',
        'p',
        'a',
        'l',
        'm',
        's',
        'rgi',
        'rgo',
        'rri',
        'rro',
        'v',
        '1_2_1',
        'tyfcb',
        'ceu',
        't',
        'targeted_date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
