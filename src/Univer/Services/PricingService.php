<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 17/10/16
 * Time: 16:02
 */

namespace Univer\Services;


use Univer\Entities\Movie;
use Univer\Entities\PricingItem;
use Univer\Entities\Season;
use Univer\Entities\Show;
use Univer\Entities\Video;
use Univer\Entities\PriceRange;
use Illuminate\Support\Collection;
use Univer\Contracts\iMediaInterface;
use Univer\Entities\PricingExpiration;

class PricingService
{
    /**
     * @var Video
     */
    protected $video;

    /**
     * @var Show
     */
    protected $show;

    /**
     * @var Season
     */
    protected $season;

    /**
     * @var Movie
     */
    protected $movie;

    public function __construct(Video $video,Show $show, Season $season, Movie $movie)
    {
        $this->video = $video;

        $this->show = $show;

        $this->season = $season;

        $this->movie = $movie;
    }

    /**
     * @param iMediaInterface $obj
     */
    public static function clearCacheForItem($id,$type)
    {
        \Cache::forget('prices' . '_' . $id . '_' . $type);
    }

    /**
     *
     * @todo Transformar getters em métodos mágicos __call
     * Retorna o preço de um show
     * @param $idShow
     * @return mixed
     */
    public function getPricesForShow($idShow)
    {
        return $this->getPrices($idShow,'show');
    }

    /**
     * Retorna preço de uma série
     * @param $idSeason
     * @return mixed
     */
    public function getPricesForSeason($idSeason){
        return $this->getPrices($idSeason, 'season');
    }

    /**
     * @param $idVideo
     * @return mixed
     */
    public function getPricesForVideo($idVideo){
        return $this->getPrices($idVideo,'video');
    }

    /**
     * @param $idMovie
     * @return mixed
     */
    public function getPricesForMovie($idMovie){
        return $this->getPrices($idMovie,'movie');
    }

    public function getPriceInstance($id){
        return PricingItem::findOrFail($id);
    }

    /**
     * @param int $idItem
     * @param string $tipoItem
     * @return Collection
     * @throws \Exception
     */
    public function getPrices($idItem,$tipoItem){

        $class = $this->getInstance($idItem,$tipoItem);

        if(!is_object($class)){
            throw new \Exception("Tipo do item {$tipoItem} não reconhecido");
        }

        $instance = $class::findOrFail($idItem);
        return $instance->getPrices();
    }

    /**
     * Retorna a instancia correta baseada no tipo da entidade e no seu ID.
     * @param null|int|iMediaInterface $idItem
     * @param $tipoItem
     * @return null|Season|Show|Video
     * @throws \Exception
     */
    public function getInstance($idItem = null,$tipoItem){
    // o usuário enviará um objeto já instanciado
    if(!is_int($idItem)){
        return $idItem;
    }

    $obj = null;

    switch ($tipoItem){
        case 'movie':
            $obj = new \Univer\Entities\Movie();
            break;
        case 'show':
            $obj = new \Univer\Entities\Show();
            break;
        case 'season':
            $obj = new \Univer\Entities\Season();
            break;
        case 'video':
            $obj = new \Univer\Entities\Video();
            break;
    }
    if(!$obj){
        throw new \Exception("Falha ao criar instancia para objeto tipo: $tipoItem");
    }

    return is_int($idItem) ? $obj::findOrFail($idItem) : $obj;
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function updatePricingItem($id,$params){
        $pricingItem = PricingItem::findOrFail($id);
        $pricingItem->price = $params['price'];
        $pricingItem->discount = $params['discount'];
        $pricingItem->pricing_expiration_id = $params['pricing_expiration_id'];

        $pricingItem->save();

        return $pricingItem->fresh();
    }

    /**
     *
     * @param iMediaInterface|Season|Show|Video $obj
     * @param mixed[] $arrPricing
     * @return
     * @throws \Exception
     */
    public function setPrices(iMediaInterface $obj,$arrPricing){
        if(!$obj instanceof iMediaInterface){
            throw new \Exception("Objeto $obj não implementa interface iMediaInterface.");
        }
        self::clearCacheForItem($obj->getMediaId(),$obj->getMediaType());
        return $obj->precificacao_item()->updateOrCreate(
            [
            'item_id'=>$obj->getMediaId(),
            'item_type'=>$obj->getMediaType(),
            'price_type'=>$arrPricing['price_type']
            ],
            [
            'price'=>$arrPricing['price'],
            'discount'=>$arrPricing['discount'],
            'price_type'=>$arrPricing['price_type'],
            'pricing_expiration_id'=>$arrPricing['pricing_expiration_id']
            ]
        );
    }

    /**
     * Cria uma faixa de preço
     * @param mixed[] $arr
     * @return PriceRange
     */
    public function createPriceRange($arrPriceRange){

        return PriceRange::create([
            'name'=>$arrPriceRange['name'],
            'price_type'=>$arrPriceRange['price_type'],
            'price'=>$arrPriceRange['price'],
            'discount'=> isset($arrPriceRange['discount']) ? $arrPriceRange['discount'] : null,
            'expiration_date'=> isset($arrPriceRange['expiration_date']) ? $arrPriceRange['expiration_date'] : null,
        ]);
    }

    /**
     * @param $methodName
     * @param $arguments
     * @return mixed
     */
    public function __call($methodName,$arguments){
        // Retorna ID
        $id = $arguments[0];

        if (preg_match('~^(setPriceFor)(.*)$~', $methodName, $matches)) {
            // Lendo o tipo do objeto do método
            $type = strtolower(array_pop($matches));
            $instancia = $this->getInstance($id,$type);
            // Arguments: setPriceForSeason(id,arrPrice)
            return $this->setPrices($instancia,$arguments[1]);
        }

        if (preg_match('~^(setPriceRangeFor)(.*)$~', $methodName, $matches)) {
            // Lendo o tipo do objeto do método
            $type = strtolower(array_pop($matches));
            // Arguments: setPriceRangeForSeason(id,arrPricing,Periodo)
            list($id,$objPriceRange,$daysDuration) = $arguments;
            return $this->setPriceRange($this->getInstance($id,$type),$objPriceRange,$daysDuration);
        }
    }


    /**
     * @param iMediaInterface $obj
     * @param PriceRange $objPriceRange
     * @param int $dayDuration A duração, em dias, da associação da faixa de preço com o objeto
     * @return mixed
     * @throws \Exception
     */
    private function setPriceRange(iMediaInterface $obj, PriceRange $objPriceRange,$dayDuration=-1){
        if(!is_object($obj) || !method_exists($obj,'faixa_de_preco')){
            throw new \Exception("Verifique se o objeto enviado utiliza trait Rentable (método faixa_de_preco não encontrado)");
        }
        self::clearCacheForItem($obj->getMediaId(),$obj->getMediaType());

        return $obj->faixa_de_preco()->updateOrCreate([
            'item_id'=>$obj->getMediaId(),
            'item_type'=>$obj->getMediaType(),
            'price_type'=>$objPriceRange->price_type
        ],[
            'price_range_id'=>$objPriceRange->id,
            'pricing_expiration_id'=>PricingExpiration::where('time_period',$dayDuration)->first()->id
        ]);
    }

    public function deleteUnitPricing($idPricingItem,$pricingType,$itemId,$itemType){
        self::clearCacheForItem($itemId,$itemType);
        return PricingItem::where('id',(int)$idPricingItem)->where('price_type',$pricingType)->where('item_id',$itemId)->delete();
    }

}