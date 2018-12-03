<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class BuyRentItems extends Model
{
    protected $table = 'buy_rent_items';
    protected $fillable = [
        'user_id',
        'item_id',
        'item_type',
        'price',
        'buy_rent_id',
        'start_at',
        'expiration_date'
    ];

    public function buyRent()
    {
        return $this->belongsTo('Univer\Entities\BuyRent');
    }
}
