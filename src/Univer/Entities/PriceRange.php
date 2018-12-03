<?php

namespace Univer\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PriceRange extends Model
{
    protected $table = 'price_range';
    protected $fillable = [
        'name',
        'price',
        'discount',
        'price_type',
        'updated_at'
    ];

    /**
     * Retorna apenas faixas de preço ativas
     * @param $query
     * @return mixed
     */
    public function scopeAtiva($query){
        return $query->where("expire_at",">",Carbon::now())->orWhere('expire_at',null);
    }
}
