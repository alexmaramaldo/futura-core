<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 24/10/16
 * Time: 10:45
 */

namespace Univer\Transactions;


use Carbon\Carbon;
use Univer\Entities\BuyRent;
use Univer\Entities\GiftCardTvod;

class GiftCard extends AbstractTransaction
{

    protected $gift_card;

    public function __construct($payment_data, BuyRent $buy_rent, $payment_items)
    {
        parent::__construct($payment_data, $buy_rent, $payment_items);

        $paymentData = $this->payment_data['payment'];

        $this->gift_card = GiftCardTvod::where('code', $paymentData['gift_card_code'])->first();

        if(!$this->gift_card){
            throw new \Exception("Código inválido");
        }
    }

    public function setConfig()
    {
        return [];
    }

    /**
     * @return bool
     */
    private function validarGiftCard()
    {
        //Retorna sempre true em ambientes staging
        if(app()->environment() !== 'staging'){
            // Verifica se codigo não possui usuario associado, se existir, entao codigo ja foi utilizado
            if(!is_null($this->gift_card->user_id)){
                $this->errors[] = "Gift card inválido ou já utilizado";
                return false;
            } else {
                return true;
            }
        } else{
            return true;
        }
    }
    public function processTransaction()
    {
        if($this->validarGiftCard()){
            $this->is_active = true;
            $this->gift_card->user_id = $this->payment_data['user_id'];
            $this->gift_card->save();


            $expirationDate = Carbon::now()->addDays((int)$this->gift_card->valid_days)->format('Y-m-d H:i:s');
            $this->buy_rent->items()->update(['expiration_date'=>$expirationDate]);

            $this->buy_rent->status = 'active';
            $this->buy_rent->save();
        }

        return $this;
    }
}