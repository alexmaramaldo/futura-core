<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 24/10/16
 * Time: 10:45
 */

namespace Univer\Transactions;

use Carbon\Carbon;
use Univer\Entities\ApplePayTransaction;
use Univer\Facades\DRMService;

class ApplePay extends AbstractTransaction
{
    protected $applePayTransaction;

    protected $is_active = false;

    public function __construct($payment_data,$buy_rent,$payment_items)
    {
        parent::__construct($payment_data,$buy_rent,$payment_items);


        // Constante definida no LogsApiCalls
        if(!defined('DEVICE_ID') || is_null(DEVICE_ID)){
            throw new \Exception("DEVICE_ID não localizado no request.");
        }
        $this->applePayTransaction = new ApplePayTransaction();
    }

    public function setConfig()
    {
     return [];
    }

    public function drmAuthorize()
    {
        $user = new \stdClass();
        $user->email = DEVICE_ID;

        try{
            DRMService::login($user);
        } catch (\Exception $ex){
            $transaction = $this->applePayTransaction->fresh();
            \Log::error("Falha ao autorizar DEVICE no DRM",['email'=>$user->email,'apple_pay_transaction'=>json_encode($transaction)]);
        }

    }

    public function processTransaction()
    {
        $payment = $this->payment_data['payment'];

        $created = $this->applePayTransaction->create([
            'buy_rent_id'=>(int)$this->buy_rent->id,
            'receipt' => $payment['apple_pay_receipt'],
            'transaction_id' => $payment['transaction_id'],
            'in_app_product_id' => $payment['in_app_product_id'],
            'device_id' => DEVICE_ID
        ]);

        $this->transaction = $created;
        if ($created) {
            $this->is_active = true;
            $this->buy_rent->status = 'active';

            $expirationDate = Carbon::now()->addMonth(1)->format('Y-m-d H:i:s');

            $this->buy_rent->items()->update(['expiration_date'=>$expirationDate]);
            $this->buy_rent->save();

            $this->drmAuthorize();
        }
        return $this;
    }

}
