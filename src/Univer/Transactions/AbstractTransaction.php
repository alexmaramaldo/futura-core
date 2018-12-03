<?php

namespace Univer\Transactions;

use \Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Univer\Entities\BuyRent;
use Univer\Entities\Transaction;
use Vindi\Bill;
use Vindi\Customer;
use Vindi\Vindi;
use Vindi\PaymentProfile;

/**
 * Class AbstractTransaction
 * @todo Tratamento de erros de pagamento; criar e alocar na variável erros.
 * @package Univer\Transactions
 */
abstract class AbstractTransaction
{

    /**
     * @var Bill
     */
    protected $bill;

    /**
     * @var Vindi
     */
    protected $vindi;


    /**
     * @var Request
     */
    protected $request;
    /**
     * @var mixed[]
     */
    protected $customer;
    /**
     * @var Transaction
     */
    protected $transaction;
    /**
     * @var String
     */
    protected $type = null;
    /**
     * @var mixed[]
     */
    protected $arrConfig = [];
    /**
     * @var PaymentProfile
     */
    protected $paymentProfile;

    /**
     * @var BuyRent
     */
    protected $buy_rent;

    /**
     * @var mixed[]
     */
    protected $payment_items;

    /**
     * @var boolean
     */
    protected $is_active;

    /**
     * @var mixed[]
     */
    protected $errors = [];

    /**
     * @var A chave que identifica o usuário
     */
    protected $userKey;

    /**
     * AbstractTransaction constructor.
     * @param $payment_data
     * @param $buy_rent BuyRent
     */
    public function __construct($payment_data,$buy_rent,$payment_items)
    {
//        $this->vindi = Config::get('vindi');
        
        putenv('VINDI_API_KEY='.app('v2vindiApiKey'));
        $this->payment_data = $payment_data;
        $this->transaction = new Transaction();
        $this->buy_rent = $buy_rent;
        $this->payment_items = $payment_items;

        $this->userKey = isset($this->payment_data['user_id']) ? $this->payment_data['user_id'] : !defined('DEVICE_ID') ? null : DEVICE_ID;
        $this->setConfig();
    }

    protected function setConfig(){
        return [];
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        // Cria a fatura do item desejado na Vindi
        return $this->arrConfig;
    }


    /**
     * @return bool
     */
    public function fails(){
        return count($this->errors) > 0;
    }

    /**
     * @return bool
     */
    public function success(){
        return !$this->fails() && $this->is_active;
    }

    public function getTransaction(){
        return $this->transaction;
    }

    public function getErrors(){
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isActive(){
        return $this->is_active;
    }

    /**
     * Retorna a instancia de buy rent da transação.
     * @return BuyRent
     */
    public function getBuyRent(){
        return $this->buy_rent->fresh();
    }

    public function processar(){
        $transaction = $this->processTransaction();

        /**
         * Validar se transação bem sucedida
         */
        if($transaction->fails()){
            // throw exceptions
            throw new \Exception(implode(',',$transaction->getErrors()));
        } else {
            // DRM AUTHORIZE

            $this->cleanCacheForUser();
            return $transaction->getBuyRent();
        }
    }

    /**
     * limpa caches de compra do usuário
     */
    private function cleanCacheForUser()
    {

        foreach($this->payment_data['items'] as $item){
            Cache::forget('user_can_watch_'.$this->userKey.$item['item_id'].'_'.$item['item_type']);
            Cache::forget('user_can_watch_content_method'.$this->userKey.'_'.$item['item_id'].'_'.$item['item_type']);
        }
    }
}
