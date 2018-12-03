<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class PricingItem extends Model
{
    protected $table = 'pricing_item';
    protected $fillable = [
        'item_id',
        'item_type',
        'price',
        'discount',
        'price_type',
        'pricing_expiration_id',
        'status'
    ];

    public function validade(){
        return $this->hasOne(PricingExpiration::class,'id','pricing_expiration_id');
    }

}
