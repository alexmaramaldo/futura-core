<?php

namespace Univer\Services;

use Illuminate\Support\Facades\DB;
use JWTAuth;
use App\User;
use Carbon\Carbon;
use Univer\Entities\BuyRent;
use Illuminate\Support\Facades\Auth;
use Univer\Contracts\iMediaInterface;
use Univer\Entities\BuyRentItems;


class ClerkService
{
    /**
     * @var BuyRent
     */
    protected $rentals;

    /**
     * Vamos tentar persistir numa chamada se usuario pode ou não ver o conteúdo.
     * Para não ficar verificando n vezes em uma chamada
     * @var bool
     */
    protected $canSee = false;

    /**
     * Registra se já foi verificada autorização via svod
     * @var bool
     */
    protected $svod_checked = false;

    /**
     * Define se usuário pode ver conteúdo por conta de ter plano svod ativo
     * @var bool
     */
    protected $svod_enabled = false;

    public function __construct(BuyRent $rentals)
    {
        $this->rentals = $rentals;
    }

    /**
     * @param $idUser
     * @param iMediaInterface $media
     * @param null $rental_type
     * @param boolean $keepTrying Indica se o sistema deve buscar uma permissão indireta para assistir a um conteudo. (Ex. usuário comprou a season ou série inteira de um episodio)
     * @return bool
     */
    public function userCanWatchContent($idUser, $media, $rental_type=null, $keepTrying=true)
    {
        if(!$media instanceof iMediaInterface)
        {
            throw new \Exception("Objeto media não parece ser uma instancia de iMediainterface");
        }
        if($idUser > 0)
        {
            $userKey = $idUser;
            if($usuarioPremium = User::select('premium')->where('id',$idUser)->first())
            {
                if((int)$usuarioPremium->premium === 1)
                {
                    return true;
                }
            }
        }
        else
        {
            if(defined('DEVICE_ID') && !is_null(DEVICE_ID))
            {
                $userKey = DEVICE_ID;
            }
            else
            {
                // Não enviou idUser nem tem DEVICE_ID definido, não pode ver conteúdo.
                return false;
            }
        }
        //Vamos tentar achar a permissão da forma mais simples para a mais complexa.
        if($this->canSeeBySvod($userKey,$media,$rental_type))
        {
            return $this->canSee = true;
        }
        else
        {
            $hasRental = $this->canWatchQuery($idUser, $media, $rental_type);
            return $hasRental ? $this->canSee = true : $this->lookDeeper($idUser,$media);
        }
    }

    /**
     * @param iMediaInterface $media
     * @return bool
     */
    public function getItemFromLibrary(iMediaInterface $media){

        //@todo Extrair esta verificação de usuário logado via Site / via API / localizar device id
        if(!Auth::check()){
            try{
                $user = JWTAuth::parseToken()->authenticate();
                $idUser = $user->id;
            } catch(\Exception $ex){
                //implementar retorno correto aqui,enviando apple pay e device id
                if(!defined('DEVICE_ID')){
                    return false;
                }
                $idUser =0; // Força o clerk a procurar usando um device id
            }
        } else{
            $idUser = Auth::user()->id;
        }

        if($this->userCanWatchContent($idUser,$media)){
            return $this->getRentDetails($idUser,$media);
        } else{
            return false;
        }
    }

    /**
     * Verifica se media é do tipo SVOD ou mixed e se usuário tem plano ativo.
     * Caso sim, libera conteúdo.
     * @param $idUser
     * @param $media iMediaInterface
     * @param null $rental_type
     * @return bool
     */
    public function canSeeBySvod($idUser,$media,$rental_type=null){
        $dataNow = Carbon::now();
        $dataLimit = Carbon::createFromFormat('Y-m-d H:i:s',$dataNow->format('Y-m-d'). ' 23:59:00');
        $remainingMinutes = $dataNow->diffInMinutes($dataLimit);

        $ret = false;
        if(isset($media->availability) && $media->availability !== 'TVOD'){
            $this->svod_checked = true;
            //chamada pode ser da API, vamos procurar o user no banco
            if($user = User::select('id')->where('id',$idUser)->first()){
                $user->isActive();
                $user = $user->fresh();

                $this->svod_enabled = $user->isActive();
                $ret = $this->svod_enabled;
            }
        }

        // Grava permissão svod por usuário por dia caso verdadeira, 1 minuto caso falsa.
        //return (\Cache::remember($idUser.'_svod',$ret == true? $remainingMinutes : 1,function()use($ret){
            return $ret;
        //}));
    }

    protected function getRentDetails($idUser,$media){
        // Este método só é chamado após verificar se user can watch content; neste caso
        // svod_enabled já deve ter sido definido.
        if($this->svod_checked && $this->svod_enabled === true){
            $compra = new \stdClass();
            $compra->rental_type = 'SVOD';
            return $compra;
        } else{
            if($this->canSeeBySvod($idUser,$media))
            {
                $compra = new \stdClass();
                $compra->rental_type = 'SVOD';
                return $compra;
            }
        }

         $hasRental =  $this->rentals
            ->select(['buy_rent.*','buy_rent_items.*'])
            ->join('buy_rent_items','buy_rent_items.buy_rent_id','=','buy_rent.id')
            ->where('buy_rent_items.item_id', $media->getMediaId())
            ->where('buy_rent_items.item_type', $media->getMediaType())
            ->where('buy_rent_items.expiration_date', '>', Carbon::now()->format('Y-m-d H:i:s'))
            ->orderBy('buy_rent.created_at','DESC');

        if(defined('DEVICE_ID') && !is_null(DEVICE_ID)){
            $hasRental->leftjoin('apple_pay_transactions','apple_pay_transactions.buy_rent_id','=','buy_rent.id');
            $hasRental->where(function($subQuery) use($idUser){
                $subQuery->where('apple_pay_transactions.device_id',DEVICE_ID);
                if($idUser > 0){
                    $subQuery->orWhere('user_id', $idUser);
                }

            });
        } else{
            if($idUser > 0){
                $hasRental->where('user_id', $idUser);
            }
        }

        return $hasRental->first();

        // Retornar o getUsersLibrary com um id buy rent.
        // user can watch content deverá retornar id do buy rent que tem a data correta de compra/validade

    }

    /**
     * Busca compras permitidas por meio de relacionamentos
     *
     * @param $idUser
     * @param iMediaInterface $media
     * @param null $rental_type
     * @return bool
     */
    private function lookDeeper($idUser,iMediaInterface $media,$rental_type=null){
        $canWatch = false;

        // O caminho mais fácil é ver se o video pertence a um filme.
        if(method_exists($media,'title') && !is_null($media->title()->first())){
            $canWatch =$this->canWatchQuery($idUser,$media->title()->first(),$rental_type);
        }

        /**
         * Verifica se o video pertence a alguma season
         */
        if(method_exists($media,'season') && (!$canWatch && ($media->season()->count() > 0))){
            foreach($media->season()->select('id')->get() as $season){
                $canWatch = $this->canWatchQuery($idUser,$season,$rental_type);
                if($canWatch){
                    return $this->canSee = $canWatch;
                    break;
                }
            }

            if(!$canWatch && method_exists($media,'show')){
                $canWatch = $this->canWatchQuery($idUser,$media->show(),$rental_type);
            }
        }

        return $canWatch;
    }

    /**
     * Retorna todas compras ativas de um usuário (dentro do prazo de um mês, ou com start_at menor do que dois dias)
     * @param $idUser
     * @return mixed
     */
    public function getUsersLibrary($idUser)
    {
        $rentals = $this->rentals
            ->where('user_id', $idUser)
            ->join('buy_rent_items', 'buy_rent_items.buy_rent_id', '=', 'buy_rent.id')
            ->where('status','active')
            ->orderBy('buy_rent.created_at', 'DESC')
            ->groupBy('buy_rent_items.item_id')
            ->groupBy('buy_rent_items.item_type')
            ->where('buy_rent_items.expiration_date','!=',null)
            ->where('buy_rent.expiration_date','>',Carbon::now());


        /*$rentals = $this->rentals
            ->where('user_id', $idUser)
            ->where('status','active')
            ->orderBy('created_at', 'DESC')
            ->where('expiration_date','>',Carbon::now());*/

        return $rentals->get();
    }

    /**
     * Retorna todas compras de um usuário
     * @param $idUser
     * @return mixed
     */
    public function getUserHistory($idUser){
        return BuyRent::where('user_id',$idUser)->get();
    }

    /**
     * @param int $idUser
     * @param iMediaInterface $media
     * @param $rental_type
     * @param $paymentParams
     * @return mixed
     * @throws \Exception
     */
    public function rentMediaForUser($idUser,iMediaInterface $media,$rental_type,$paymentParams){
        $user = User::findOrFail($idUser);

        if($this->userCanWatchContent($idUser,$media,$rental_type)){
            throw new \Exception("Usuário já possui o conteúdo informado.");
        }
        return \TransactionService::buyOrRentContent($user,$media,$rental_type, $paymentParams);
    }

    /**
     * @param $receipt
     * @return mixed
     * @todo Retornar todas compras por DEVICE ID
     */
    public function getContentByApplePayReceipt($receipt=null){

        if($receipt && !is_array($receipt)){
            $receipt = [$receipt];
        }

        if(!defined('DEVICE_ID') && !$receipt){
            throw new \Exception("Device ID ou receipt obrigatórios");
        }
        $rentals = $this->rentals
            ->select('buy_rent_items.*')
            ->join('apple_pay_transactions','apple_pay_transactions.buy_rent_id','=','buy_rent.id')
            ->join('buy_rent_items','buy_rent_items.buy_rent_id','=','buy_rent.id');

        if($receipt){
            $rentals = $rentals->whereIn('apple_pay_transactions.receipt',$receipt);
        }
        if(defined('DEVICE_ID')){
            $rentals->where('apple_pay_transactions.device_id',DEVICE_ID);
        }

        return $rentals->get();
    }

    /**
     * Verifica se usuário pode assistir determinado conteúdo. (comprou, alugou, e está válido)
     *
     * @param $idUser
     * @param $media
     * @param $rental_type
     * @return mixed
     */
    protected function canWatchQuery($idUser, $media, $rental_type)
    {

        $hasRental = \Cache::remember($idUser.'_'.$media->getMediaId().'_canwatch',2,function()use($idUser,$media,$rental_type){
            $hasRental = $this->rentals
                ->join('buy_rent_items', 'buy_rent_items.buy_rent_id', '=', 'buy_rent.id')
                ->where('buy_rent_items.item_id', $media->getMediaId())
                ->where('buy_rent_items.item_type', $media->getMediaType())
                ->where('buy_rent_items.expiration_date','!=',null)
                ->where('status','active')
                ->orderBy('buy_rent.created_at','DESC')
                ->limit(1);

            if(defined('DEVICE_ID') && !is_null(DEVICE_ID)){
                $hasRental->leftjoin('apple_pay_transactions','apple_pay_transactions.buy_rent_id','=','buy_rent.id');

                $hasRental->where(function($subQuery) use($idUser){
                    $subQuery->where('device_id',DEVICE_ID)
                        ->orWhere('user_id', $idUser);
                });
            } else{
                if($idUser > 0){
                    $hasRental->where('user_id', $idUser);
                }
            }

            return $hasRental->first();
        });

        if ($hasRental) {
            if ($hasRental->rental_type == 'buy') {
                return true;
            } else {

                $realExpiration = $hasRental->expiration_date;
                if($hasRental->start_at){
                    $realExpiration = Carbon::createFromFormat('Y-m-d H:i:s',$hasRental->start_at)->addDays(2);
                }

                $now = Carbon::now();
                if (strtotime($realExpiration) >= strtotime($now)) {
                    return true;
                }else{
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * Define a data de inicio de visualização de um item (start_at), caso seja alugado e dentro do prazo válido
     * @param iMediaInterface $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsWatchedForUser(iMediaInterface $item)
    {
        if(!Auth::check()){
            try{
                $user = JWTAuth::parseToken()->authenticate();
                $idUser = $user->id;
            } catch(\Exception $ex){
                return response()->json(['status'=>false,'msg'=>'Usuário inválido']);
            }
        } else{
            $idUser = Auth::user()->id;
        }

        if($compra = $this->getRentDetails($idUser,$item)){

            if($compra->rental_type && $compra->rental_type === 'rent'){

                $itemBeingWatched = BuyRentItems::select('id')->where('id',$compra->id)->where('start_at',null)->where('expiration_date','>',Carbon::now())->first();
                if($itemBeingWatched){
                    $itemBeingWatched->start_at = Carbon::now();
                    $itemBeingWatched->save();

                    return response()->json(['status'=>true,'item'=>$item]);
                } else{
                    return response()->json(['status'=>false,'msg'=>'Compra já tem']);
                }
            }
        } else{
            return response()->json(['status'=>false,'msg'=>'Usuário não tem permissão para assistir o conteúdo solicitado']);
        }
    }


    /**
     * Retorna objeto iMediaInterface com atributos pricing
     * @param iMediaInterface $item
     * @return iMediaInterface
     */
    public function getCanSeeContent(iMediaInterface $item)
    {
        $pricing = $item->getPrices();
        $pricing = $pricing->transform(function($item,$key){
            return [
                'type'=>$item['type'],
                'price'=>$item['price'],
                'price_usd'=>$item['price_usd'],
                'discount'=>$item['discount'],
                'dates'=>[
                    'expire_after'=>$item['dates']['expire_after']
                ],

            ];
        });

        $item->pricing = $pricing->toArray();

        $idUser = false;
        if(!Auth::check()){
            try{
                $user = JWTAuth::parseToken()->authenticate();
                $idUser = $user->id;
            } catch(\Exception $ex){
                //implementar retorno correto aqui,enviando apple pay e device id
                //return ($this->getContentByApplePayReceipt());
                if(!defined('DEVICE_ID')){
                    return $item;
                }
            }
        } else{
            $idUser = Auth::user()->id;
        }

        if(!$idUser && !defined('DEVICE_ID')){
            return $item;
        }
        $canWatch = ClerkService::userCanWatchContent($idUser,$item);

        if($canWatch){
            $item->can_see_content = true;
            if(\Request::get('include_rentals') === 'true'){
                $item->rentals = ClerkService::getItemFromLibrary($item);
            }
        } else{
            $item->can_see_content = false;
        }

        return $item;
    }

}
