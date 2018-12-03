<?php

namespace Univer\Services;

use App\Plano;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery\CountValidator\Exception;
use Univer\Entities\ApplePayTransaction;
use Univer\Entities\BuyRent;
use Univer\Entities\GiftCardTvod;
use Univer\Entities\Transaction;
use Univer\Transactions\ApplePay;
use Univer\Transactions\BankSlip;
use Univer\Transactions\GiftCard;
use Univer\Transactions\CreditCard;
use Univer\Transactions\AbstractTransaction;

class TransactionService
{
    /**
     * @var mixed[]
     */
    protected $payment_data;

    /**
     * @var mixed[]
     */
    protected $payment_items;

    /**
     * @var BuyRent
     */
    protected $buy_rent;

    /**
     * @var int[]
     */
    protected $pricing_types;

    /**
     * @var AbstractTransaction
     */
    protected $objTransaction;

    /**
     * @var mixed[]
     */
    protected $paymentItems;

    protected $userKey;

    public function __construct($payment_data)
    {
        $this->payment_data = $payment_data;

        $this->payment_data['value'] = 0; //Inicia o valor total da transação em 0

        $this->buy_rent = new BuyRent();
        $this->pricing_types = app('pricing_types');

        $this->validateGiftCard();

        if(empty($this->payment_data['items'])){
            throw new \Exception("Nenhum item selecionado");
        }

    }

    /**
     * Configura array de itens caso usuário esteja usando gift card.
     * @throws \Exception
     */
    public function validateGiftCard(){

        if($this->payment_data['payment']['payment_method']==='gift_card'){
            $giftCard = GiftCardTvod::where('code',$this->payment_data['payment']['gift_card_code'])->first();

            if(!$giftCard || (!is_null($giftCard->user_id) && app()->environment() !== 'staging')){
                throw new \Exception("Cartão inválido");
            }else{

                $this->payment_data['rental_type'] = $giftCard->rental_type;

                // ~Forçação de barra~ para ter mais redundancia
                $this->payment_items = $this->payment_data['items']=[
                    ['item_id'=>$giftCard->item_id,'item_type'=>$giftCard->item_type]
                ];

                if(empty($this->payment_items)){
                    throw new \Exception("Nenhum item localizado");
                }
            }
        }
    }

    /**
     * @param $payment_data
     * @return BuyRent
     */
    public static function buyOrRentContent($payment_data){

        $service = new TransactionService($payment_data);
        try{
            return $service->processTransaction();
        } catch (\Exception $ex){
            if(app()->environment() === 'staging'){
                throw new \Exception($ex->getMessage().' '.$ex->getLine(). ' '.$ex->getFile());
            } else{
                throw new \Exception($ex->getMessage());
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function saveItems(){

        foreach($this->payment_data['items'] as $item){

            if((int)$item['item_id'] <= 0 || strlen($item['item_type']) <= 0){
                throw new \Exception("item_id ou item_type inválidos");
            }

            $media = getMediaInstance((int)$item['item_id'],$item['item_type']);

            if($media->availability === 'SVOD'){
                throw new \Exception("Conteúdo disponível apenas com plano de assinatura");
            }


            list($totalPrice, $amount) = $this->getPricesForMedia($media, $item);

            // Incrementa no valor total do pedido
            $this->payment_data['value'] +=$amount;

            $this->payment_items[] = [
                "product_id"=> $this->pricing_types[$this->payment_data['rental_type']],
                "amount"=> $amount,
                "description"=> $media->presentName()
            ];

            // Adiciona item na compra
            $this->buy_rent->items()->create([
                'item_id'=>$media->getMediaId(),
                'item_type'=>$media->getMediaType(),
                'price'=>$totalPrice
            ]);

            $this->clearUserCacheForMedia($media);
        }

        return $this->payment_items;
    }

    /**
     * Cria a entidade BUY RENT com os relativos
     */
    protected function makeBuyRent(){
        // Create a buyrent register
        $this->buy_rent = BuyRent::create([
            'user_id' => isset($this->payment_data['user_id']) ? $this->payment_data['user_id'] : null,
            'rental_type' => $this->payment_data['rental_type'],
            'payment_method'=>$this->payment_data['payment']['payment_method']
        ]);

        $this->saveItems();

        $this->payment_data['buy_rent_id'] = $this->buy_rent->id;

        return $this->buy_rent;
    }

    /**
     * @return boolean
     * @todo Implementar regra para validar compra com device id
     */
    protected function validateRent(){
        $allowed = true;
        if($this->payment_data['payment']['payment_method'] === 'apple_pay'){
            return true;
        }

        foreach($this->payment_data['items'] as $item){

            if(!isset($item['item_id']) || !isset($item['item_type'])){
                throw new \Exception("Id ou tipo do item não localizado");
            }

            $buyRent = new BuyRent();
            $hasRental = $buyRent->select(['buy_rent.id','buy_rent.status','buy_rent.rental_type','buy_rent_items.expiration_date','buy_rent_items.start_at'])
                ->join('buy_rent_items','buy_rent_items.buy_rent_id','=','buy_rent.id')
                ->where('user_id', $this->payment_data['user_id'])
                ->where('buy_rent_items.item_id', $item['item_id'])
                ->where('buy_rent_items.item_type', $item['item_type'])
                ->orderBy('buy_rent.created_at','DESC')
                ->first();

            // Já existe uma tentativa/compra no histórico do usuário
            if($hasRental){

                if($buyRent->status !== 'active'){
                    // já tentou comprar mas não foi finalizado
                    break;
                }
                // Já existe compra ativa
                if($buyRent->rental_type === 'rental'){
                    //Será que usuário já alugou e agora quer comprar?
                    if($this->payment_data['rental_type'] === 'buy'){
                        // allowed: true
                    } else{
                        $allowed = false;
                        break;
                    }

                    // Vamos verificar então se prazo de validade já venceu, e usuário quer alugar novamente
                    if($buyRent->expiration_date > Carbon::now()){
                        $allowed = false;
                        break;
                    }

                } else{
                    // Usuário já tem compra ativa
                    $allowed = false;
                    break;
                }
            }
        }
        return $allowed;
    }

    /**
     * @todo Verificar se preço que vem do controller é o mesmo que é obtido pelo método getPrices
     * @return BuyRent
     */
    private function processTransaction()
    {
        if(!$this->validateRent()){
            throw new \Exception("Usuário já possui item em sua biblioteca");
        }

        $this->makeBuyRent();
        $objTransaction = $this->getObjPaymentType();
        //Process transaction
        return $objTransaction->processar();
    }

    // Limpa cache de compras do usuário para determinada midia
    private function clearUserCacheForMedia($media){
        if( isset($this->payment_data['user_id'])){
            $idUser = $this->payment_data['user_id'];
            \Cache::forget($idUser.'_'.$media->getMediaId().'_canwatch');
            // Cache removido. Era utilizado?
            // \Cache::forget($idUser.'_'.$media->getMediaId().'_svod');
            \Cache::forget($idUser.'_library');
        }
    }

    public static function enableLifetimeSubscription($bill){

        // Procura a Transaction com este bill id
        $transaction = Transaction::where('id_bill_vindi',$bill->id)->first();
        if(!$transaction){
            return false;
        }

        // Verifica se a bill está paga e ativa a assinatura do usuário para sempre
        if($bill->status === 'paid'){
            $transaction->status = 'active';
            $transaction->aproved_at = Carbon::now();
            $transaction->save();
            // Busca o plano do usuário
            $plano = Plano::where('user_id', $transaction->user_id)->where('id_plano_vindi', 54409)->first();

            if(!$plano){
                return false;
            }

            $plano->status = 'ativo';
            $plano->vencimento = Carbon::now()->addYears(1000);
            $plano->save();
            return true;
        } else {
            return false;
        }
    }


    /**
     * @param $bill
     * @return boolean
     * @throws \Exception caso bill seja inválida
     */
    public static function enableRental($bill){
        //Procura uma buy rent com este bill id
        $buyRent = new BuyRent();
        $billId =0;

        if(is_array($bill)){
            $billId = (int)$bill['id'];
        } else{
            $billId = (int)$bill->id;
        }

        if($billId <= 0){
            return false;
        }

        $transaction = Transaction::where('id_bill_vindi',$billId)->first();
        if(!$transaction){
            return false;
        } else{
            if($bill->status === 'paid'){

                $transaction->status = 'active';
                $transaction->aproved_at = Carbon::now();

                $buyRent = BuyRent::where('id',$transaction->buy_rent->id)->first();
                $buyRent->status = 'active';
                $buyRent->save();

                $buyRent->items()->update([
                    'expiration_date'=>Carbon::now()->addMonth(1)->format('Y-m-d H:i:s')
                ]);

                $transaction->save();
                return true;

            } else{
                return false;
            }
        }
    }

    /**
     * @return AbstractTransaction
     * @throws \Exception
     * @internal param $payment_data
     * @internal param $buy_rent
     */
    protected function getObjPaymentType()
    {
        //Verify the type of payment
        switch ($this->payment_data['payment']['payment_method']) {
            case 'credit_card':
                $objPaymentType = CreditCard::class;
                break;
            case 'bank_slip':
                $objPaymentType = BankSlip::class;
                break;
            case 'gift_card':
                $objPaymentType = GiftCard::class;
                break;
            case 'apple_pay':
                $objPaymentType = ApplePay::class;
                break;
            default:
                throw new \Exception("Nao foi possivel instanciar o metodo de pagamento informado: " . $this->payment_data['payment']['payment_method']);
        }
        return new $objPaymentType($this->payment_data, $this->buy_rent,$this->payment_items);
    }

    /**
     * @param $media
     * @param $item
     * @return array
     * @throws \Exception
     */
    protected function getPricesForMedia($media, $item)
    {
        if($this->payment_data['payment']['payment_method'] === 'gift_card'){
            return [0,0];
        }
        $prices = $media->getPrices();

        if ($prices->count() === 0) {
            throw new \Exception("Preço não localizado para item " . $item['item_id'] . " do tipo " . $item['item_type']);
        }

        $amount = $prices->where('type', $this->payment_data['rental_type'])->first()['price'];

        if (!$amount) {
            throw new \Exception("Falha ao localizar preço do item " . $media->getMediaId() . " - " . $media->getMediaType());
        }

        $totalPrice = $amount - $prices->where('type',$this->payment_data['rental_type'])->first()['discount'];

        return array($totalPrice, $amount);
    }
}