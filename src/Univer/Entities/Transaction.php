<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';
    protected $fillable = [
        'user_id',
        'payment_method',
        'value',
        'status',
        'gift_card',
        'buy_rent_id',
        'id_transacao_vindi',
        'id_bill_vindi',
        'aproved_at'
    ];

    public function buyrent(){
        return $this->hasOne('Univer\Entities\BuyRent','id','buy_rent_id');
    }

    public function buy_rent(){
        return $this->buyrent();
    }

}
