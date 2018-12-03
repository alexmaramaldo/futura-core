<?php

namespace Univer\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Univer\Entities\PricingItem;
use Univer\Services\PricingService;

/**
 * Class PricingController
 * @package Univer\Controllers
 */
class PricingController extends Controller
{
    /**
     * @var PricingService
     */
    protected $pricingService;

    /**
     * @param PricingService $service
     */
    public function __construct(PricingService $service)
    {
        $this->pricingService = $service;
    }

    /**
     * @param Request $request
     * @param $pricingType
     * @param $idPricing
     * @return PricingItem
     */
    public function updateUnitPricing(Request $request, $pricingType, $idPricing)
    {
        return $this->pricingService->updatePricingItem((int)$idPricing, [
            'price' => $request->price,
            'discount' => $request->discount,
            'pricing_expiration_id' => $request->pricing_expiration_id
        ]);
    }

    /**
     * @param Request $request
     * @param $itemType int
     * @param $itemId int
     * @return mixed
     */
    public function storeUnitPricing(Request $request,$itemType,$itemId){
        $instance = ($this->pricingService->getInstance((int)$itemId,$itemType));
        $this->pricingService->setPrices($instance,[
            'price'=>$request->price,
            'discount'=>$request->discount,
            'price_type'=>$request->type,
            'pricing_expiration_id'=>$request->pricing_expiration_id
        ]);
        return response()->json([
           'status'=>true,
            'pricing'=>$instance->getPrices()
        ]);
    }

    /**
     * @param Request $request
     * @param $pricingType
     * @param $idPricing
     * @return bool
     */
    public function deleteUnitPricing(Request $request,$pricingType,$idPricing){
        $delete =$this->pricingService->deleteUnitPricing($idPricing,$request->price_type,$request->item_id,$request->item_type);
        if($delete){
            return response()->json([
                'status'=>true
            ]);
        }
    }
}