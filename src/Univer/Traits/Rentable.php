<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 11/10/16
 * Time: 18:05
 */

namespace Univer\Traits;


use Univer\Entities\Favorite;
use Univer\Entities\PriceRange;
use Univer\Entities\PriceRangeItem;
use Univer\Entities\PricingExpiration;
use Univer\Entities\PricingItem;
use Univer\Transformers\RentableTransformer;


trait Rentable
{
    /**
     * Retorna preços do show/season/video
     * @return mixed
     */
    public function getPrices(){
        return \Cache::remember('prices'.'_'.$this->getMediaId().'_'.$this->getMediaType(), 60,function(){
            // Verifica se item tem faixa de preço ativa
            $faixasdePrecoAtivas = $this->getFaixasdePrecoAtivas();

            // Faixas de preço já incluem precificação individual do item.
            if($faixasdePrecoAtivas){
                return $this->transform($faixasdePrecoAtivas);
            } else{
                // Caso não tenha faixas de preço retorna todas as precificações individuais.
                return $this->transform($this->precificacao_item()->all());
            }
        });
    }


    public function getPricingAttribute(){
        return $this->getPrices();
    }


    /**
    * Função para gerar o selozinho no canto dos thumbs ('assine', 'alugue', 'compre')
    * baseados na disponibilidade e preços fornecidos no admin.
    * @return string
    **/
    public function getLabel()
    {
        if($this->availability=='TVOD'){

            $prices = $this->getPrices();

            if($prices->where('type','buy')->first()){
                return 'Compre';
            }

            if($prices->where('type','rent')->first()){
                return 'Alugue';
            }

            return 'Alugue';
        }

        return 'Assinantes';
    }

    /**
     * Verifica se usuário já favoritou item
     * @return bool
     */
    public function isFavorited()
    {
        $favorite = null;
        if(session('perfil')){
            $favorite = Favorite::select('id','status','stars')->where('perfil_id', session('perfil'))->where('item_id', $this->getMediaId())->where('item_type',$this->getMediaType())->first();
        }

        return is_null($favorite) ? false: true;
    }


    /**
     *
     * RELACIONAMENTOS
     *
     */

    /**
     * Precificação unitária de um item.
     * @return mixed
     */
    public function precificacao_item(){
        return $this->hasMany(PricingItem::class,'item_id',$this->getPrimaryKey())->where('item_type',$this->getMediaType());
    }

    /**
     * Precificação através de faixas de preço.
     * @return mixed
     */
    public function faixa_de_preco(){
        return $this->hasMany(PriceRangeItem::class,'item_id',$this->getPrimaryKey())->where('item_type',$this->getMediaType());
    }

    /**
     * @return mixed
     */
    private function getFaixasdePrecoAtivas(){
        //Busca as faixas de preço ativas para o item.
        $faixasdePrecoAtivas = $this->faixa_de_preco()->get();

        // Atualmente existem dois tipos de precificação.
        // Se não houver faixa de preço ativa para ambos tipos (BUY e RENT), buscar faixa de preço individual.
        if($faixasdePrecoAtivas && $faixasdePrecoAtivas->count() < 2){

            // A faixa de preço prevalesce sobre a precificação individual. (Caso de promoções).
            // Buscar precificações individuais que não tenham sido encontradas por faixa de preço.
            $precificacao = $this->precificacao_item()->whereNotIn('price_type',$faixasdePrecoAtivas->pluck('price_type')->toArray())->get();

            if($precificacao){
                $precificacao->each(function($item,$key) use($faixasdePrecoAtivas){
                    $faixasdePrecoAtivas->push($item);
                });
            }
        }
        return $faixasdePrecoAtivas;
    }

    /**
     * Seleciona o transformer da classe que implementa
     * @param $items
     * @return mixed
     */
    private function transform($items){
        return app()->make($this->getTransformer())->transformCollection($items);
    }

    public function getTransformer(){
        if(strlen($this->transformer) <= 0){
            return RentableTransformer::class;
        }
        return $this->transformer;
    }

    public function scopeOfAvailability($query, $availability_types)
    {
        $availability_types = is_array($availability_types) ? $availability_types : array($availability_types);
        return $query->whereIn('availability', $availability_types);
    }


}