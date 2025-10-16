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
    ];
}
