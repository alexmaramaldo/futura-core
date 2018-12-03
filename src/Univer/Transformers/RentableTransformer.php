<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 11/10/16
 * Time: 19:12
 */

namespace Univer\Transformers;


use Illuminate\Database\Eloquent\Collection;
use Univer\Contracts\iMediaInterface;
use Univer\Entities\PriceRangeItem;
use Univer\Entities\PricingItem;

class RentableTransformer extends AbstractTransformer
{

    /**
     * Tabela para conversão de preços 
     * @var array
     */
    protected $priceTable =[
        ['brl'=>0.99,'usd' => 0.99],
        ['brl'=>1.99,'usd' => 0.99],
        ['brl'=>2.49,'usd' => 1.99],
        ['brl'=>2.50,'usd' => 1.99],
        ['brl'=>3.20,'usd' => 1.99],
        ['brl'=>4.99,'usd' => 2.50],
        ['brl'=>7.90,'usd' => 3.99],
        ['brl'=>8.99,'usd' => 4.99],
        ['brl'=>12.99,'usd' => 6.20],
        ['brl'=>14.99,'usd' => 7.99],
        ['brl'=>24.90,'usd' => 1.99],
        ['brl'=>39.90,'usd' => 8.99],
    ];

    /**
     * @param PriceRangeItem|PricingItem $item
     * @return array
     * @throws \Exception
     */
    public function transform($item)
    {
        if ($item instanceof PricingItem) {
            return $this->transformPrecificacao($item);
        }
        if ($item instanceof PriceRangeItem) {
            return $this->transformFaixaDePreco($item);
        }

        throw new \Exception("Falha ao localizar tipo da precificação do item ".json_encode($item));
    }


    /**
     * @param PricingItem $item
     * @return array
     */
    private function transformPrecificacao(PricingItem $item)
    {
        $type = $item->price_type;

        return [
//            "svod" => $item->available_as,
            "type" => $type,
            "price" => $item->price,
            "price_usd"=>$this->toDolar($item->price),
            "discount" => $item->discount,
            "dates" => [
                "expire_after" => $this->getValidade($item)
            ],
            "id"=>$item->id,
            "pricing_type"=>"unit_pricing"
        ];
    }

    /**
     * @param $item
     * @return array
     */
    protected function transformFaixaDePreco($item)
    {
        $type = $item->preco->price_type;

        return [
//            "svod" => $item->available_as,
            "type" => $type,
            "price" => $item->preco->price,
            "price_usd"=>$this->toDolar($item->preco->price),
            "discount" => $item->preco->discount,
            "dates" => [
                "expire_after" => $this->getValidade($item)
            ],
            "id"=>$item->id,
            "pricing_type"=>"price_range"
        ];
    }

    /**
     * @param $item
     * @return null
     */
    private function getValidade($item)
    {
        if (method_exists($item, 'validade')) {
            return isset($item->validade->time_period) ? $item->validade->time_period : null;
        }
        return null;
    }

    protected function toDolar($price)
    {
        if(strlen($price) === 0 || $price <= 0){
            return 0.00;
        } else{
            $price = (string)($price);
            $col = collect($this->priceTable);
            $exactMatch = ($col->where('brl',$price)->first());

            if(!$exactMatch){
                $nextMatch = $col->where('brl','>',$price)->first();
                $exactMatch = $nextMatch;
            }

            // Caso não tenha encontrado valor, arredonda para o maior preço em dolar possível.
            if(!$exactMatch){
                return $col->pop()['usd'];
            }
            return $exactMatch['usd'];
        }
    }


}
