<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 24/10/16
 * Time: 10:45
 */

namespace Univer\Transactions;


use Vindi\Bill;
use Carbon\Carbon;
use Univer\Entities\Transaction;

class BankSlip extends VindiTransaction
{
    public function getBuyRent()
    {
        $buy_rent = $this->buy_rent->fresh();
        $buy_rent->boleto_url = $this->bank_slip_url;
        return $buy_rent;
    }
//    public function processTransaction()
//    {
//        // Cria o registro da transaction no banco
//        $transaction = new Transaction([
//            'user_id' => $this->payment_data['user_id'],
//            'payment_method' => $this->payment_data['payment']['payment_method'],
//            'value' => $this->payment_data['value'],
//            'buy_rent_id' => $this->payment_data['buy_rent_id']
//        ]);
//
//        $transaction->save();
//        // Cria uma nova bill e pega a config para montar a bill
//        $vindi_bill = new Bill();
//        $bill = $this->getConfig();
//
//        $vindi_bill->create($bill);
//
//        // Atualiza o status da transaction no banco de acordo com o retorno da cobrança na vindi
//        $vindi_bill = json_decode((string) $vindi_bill->getLastResponse()->getBody());
//        $transaction->status = $vindi_bill->bill->charges[0]->status;
//        $transaction->save();
//
//        $this->transaction = $transaction;
//
//        if($this->isActive()){
//            $this->buy_rent->expiration_date = Carbon::now()->addMonth(1)->format('Y-m-d H:i:s');
//            $this->buy_rent->save();
//        }
//
//        return $this;
//    }
}