<?php

namespace Univer\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PriceRangeItem extends Model
{
    protected $table = 'price_range_item';
    protected $fillable = [
        'item_id',
        'item_type',
        'price_range_id',
        'pricing_expiration_id',
        'price_type'
    ];

    /**
     * @return mixed
     */
    public function preco(){
        return $this->hasOne(FaixaDePreco::class,'id','id_faixa_de_preco')
            ->where('tipo_precificacao',$this->tipo_precificacao)
            ->where(function($orWhere){
                $orWhere->where('data_expiracao','>=',Carbon::now())->orWhere("data_expiracao",null);
            });

    }


    public function validade(){
        return $this->hasOne(PeriodoValidade::class,"id","id_periodo_validade");
    }
}
