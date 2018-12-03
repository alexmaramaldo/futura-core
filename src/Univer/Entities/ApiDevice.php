<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class ApiDevice extends Model
{
    protected $table = 'api_devices';

    protected $fillable =[
        'device_id',
        'os',
        'model',
        'user_id',
        'created_at'
    ];

}
