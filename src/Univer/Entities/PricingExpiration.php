<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class PricingExpiration extends Model
{
    protected $table = 'pricing_expiration';
    protected $fillable = [
        'name',
        'time_period'
    ];

}
