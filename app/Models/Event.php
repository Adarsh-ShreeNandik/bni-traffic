<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'first_name',
        'last_name',
        'region_name',
        'chapter_name',
        'event_date',
        'event_type',
        'join_date',
        'induction_date',
    ];
}
