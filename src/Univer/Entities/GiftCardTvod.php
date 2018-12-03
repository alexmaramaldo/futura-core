<?php

namespace Univer\Entities;

use Illuminate\Database\Eloquent\Model;

class GiftCardTvod extends Model
{
    protected $table = 'gift_card_tvod';
    protected $fillable = [
        'id_user',
        'code',
        'type_item',
        'id_item',
        'valid_days'
    ];

    public function buyrent()
    {
        return $this->belongsTo('Univer\Entities\BuyRent');
    }
}
