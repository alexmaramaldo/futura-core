<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 24/10/16
 * Time: 10:45
 */

namespace Univer\Transactions;


use Carbon\Carbon;
use GuzzleHttp\Client;
use Univer\Entities\Transaction;
use Vindi\PaymentProfile;

class CreditCard extends VindiTransaction
{
    /**
     * @return array
     */
    public function setConfig()
    {
        $this->paymentProfile = $this->getOrCreatePaymentProfile();


        $this->arrConfig = [
            'customer_id' => $this->customer->id,
            'installments' => 1,
            'payment_method_code' => $this->payment_data['payment']['payment_method'],
            'card_number' => $this->payment_data['payment']['card_number']
        ];

        $this->arrConfig['bill_items'] = $this->payment_items;

        return $this->arrConfig;
    }

    /**
     * @return mixed
     */
    public function getOrCreatePaymentProfile()
    {
        $vindi_paymentProfile = new PaymentProfile();
        //Verifica a existencia de um payment profile
        $paymentProfile = $vindi_paymentProfile->all(['query' => 'customer_id='.$this->customer->id]);

        $payment = [
            'customer_id' => $this->customer->id,
            'holder_name' => $this->payment_data['user_name'],
            'card_expiration' => $this->payment_data['payment']['card_expiration'],
            'card_number' => str_replace(' ', '', $this->payment_data['payment']['card_number']),
            'card_cvv' => $this->payment_data['payment']['card_cvv'],
            'payment_method_code' => 'credit_card'
        ];

        //Se nao existir nenhum, cria um novo
        if(count($paymentProfile) == 0){
            $paymentProfile = $vindi_paymentProfile->create($payment);
        } else {
            $paymentProfile = $paymentProfile[0];
            $payment['id'] = $paymentProfile->id;

            // não há endpoint /update nem PUT para payment profile. neste caso o backend da Vindi atualiza.
            $paymentProfile = $vindi_paymentProfile->create($payment);
        }


        $responseVerify  = $vindi_paymentProfile->verify($paymentProfile->id);

        $this->vindi_transaction = $responseVerify;
        if($responseVerify->status !== 'success') {
            $this->transaction->status = 'rejected';
            $this->transaction->user_id = $this->payment_data['user_id'];
            $this->transaction->buy_rent_id = $this->buy_rent->id;
            $this->transaction->value = $this->payment_data['value'];
            $this->transaction->id_transacao_vindi = $responseVerify->id;
            $this->transaction->save();

            $this->buy_rent->status = 'rejected';
            $this->buy_rent->save();

            $message = ($responseVerify->gateway_message);

            throw new \Exception("Falha ao processar transação: $message");
        } else{
            $this->buy_rent->status = 'active';
            $this->buy_rent->save();
        }
        return $paymentProfile;
    }
    public function isActive()
    {
     return isset($this->vindi_transaction) && $this->vindi_transaction->status === 'success';
    }
}