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

class ShowTransformer extends AbstractTransformer
{

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
            "svod" => $item->available_as,
            "type" => $type,
            "price" => $item->price,
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
            "svod" => $item->available_as,
            "type" => $type,
            "price" => $item->preco->price,
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


}