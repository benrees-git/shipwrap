<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Connection extends Model
{
    use HasFactory;

    protected $fillable = [
        'service', 'request_token', 'external_token', 'external_expiry'
    ];

    protected $hidden = [
        'external_token',
    ];

}
