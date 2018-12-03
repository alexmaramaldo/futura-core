<?php

namespace Univer\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PriceRangeItem extends Model
{
    protected $table = 'price_range_item';
    protected $primaryKey = 'item_id';
    protected $fillable = [
        'item_id',
        'item_type',
        'price_range_id',
        'pricing_expiration_id',
        'price_type'
    ];

    /**
     * Retorna faixas de preço ativas.
     *
     * @return mixed
     */
    public function preco(){
        return $this->hasOne(PriceRange::class,'id','price_range_id')
            ->where('price_type',$this->price_type)
            ->where(function($orWhere){
                $orWhere->where('expire_at','>=',Carbon::now())->orWhere("expire_at",null);
            });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function validade(){
        return $this->hasOne(PricingExpiration::class,"id","pricing_expiration_id");
    }
}
