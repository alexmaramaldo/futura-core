<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class ApplePayTransaction extends Model
{
    protected $table = 'apple_pay_transactions';

    protected $fillable =[
        'receipt',
        'transaction_id',
        'in_app_product_id',
        'buy_rent_id',
        'device_id'
    ];

    public function buyrent(){
        return $this->belongsTo('Univer\Entities\BuyRent');
    }

}
