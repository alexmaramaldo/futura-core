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
use Univer\Entities\Transaction;
use Vindi\Bill;
use Vindi\Customer;

class VindiTransaction extends AbstractTransaction
{
    /**
     * @var \Vindi\Transaction
     */
    protected $vindi_transaction;
    protected $bank_slip_url;


    public function __construct($payment_data, BuyRent $buy_rent, $payment_items)
    {
        $vindiKey = app('v2vindiApiKey');

        if(!$vindiKey){
            throw new \Exception("Falha ao localizar chave VINDI");
        }
        putenv('VINDI_API_KEY='.$vindiKey);
        $this->payment_data = $payment_data;
        $this->transaction = new Transaction();
        $this->buy_rent = $buy_rent;
        $this->payment_items = $payment_items;
        $this->customer = $this->getOrCreateCustomer();

        $this->setConfig();
    }

    /**
     * @return array
     */
    public function setConfig()
    {
        $this->arrConfig = [
            'customer_id' => $this->customer->id,
            'installments' => 1,
            'payment_method_code' => $this->payment_data['payment']['payment_method']
        ];

        $this->arrConfig['bill_items'] = $this->payment_items;

        return $this->arrConfig;
    }

    /**
     * @return mixed
     */
    public function getOrCreateCustomer()
    {

        if(!isset($this->payment_data['user_name'])){
            throw new \Exception("Campo user_name e user_email obrigatórios para esta forma de pagamento");
        }

        $vindi_customer = new Customer();
        $customer = $vindi_customer->all(['query' => 'email=' . $this->payment_data['user_email']]);

        //Verifica se o usuario existe, se existir retorna o mesmo, se não cria
        if (count($customer) === 0) {
            return $vindi_customer->create([
                'name' => $this->payment_data['user_name'],
                'email' => isset($this->payment_data['user_email']) ? $this->payment_data['user_email'] : ''
            ]);
        } else {
            return $customer[0];
        }
    }

    public function processTransaction()
    {

        // Cria o registro da transaction no banco
        $transaction = Transaction::create([
            'user_id' => $this->payment_data['user_id'],
            'payment_method' => $this->payment_data['payment']['payment_method'],
            'value' => $this->payment_data['value'],
            'buy_rent_id' => $this->payment_data['buy_rent_id']
        ]);

        $transaction->save();

        // Cria uma nova bill e pega a config para montar a bill
        $vindi_bill = new Bill();
        $bill = $this->getConfig();
        $vindi_bill->create($bill);

        // Atualiza o status da transaction no banco de acordo com o retorno da cobrança na vindi
        $vindi_bill = json_decode((string) $vindi_bill->getLastResponse()->getBody());

        $transaction->id_bill_vindi = $vindi_bill->bill->id;
        $transaction->status = $vindi_bill->bill->charges[0]->status === 'paid' ? 'active' : $vindi_bill->bill->charges[0]->status;
        $transaction->id_transacao_vindi = $this->vindi_transaction ? $this->vindi_transaction->id : null;

        if(property_exists($vindi_bill->bill->charges[0],'print_url')){
            $this->bank_slip_url = $vindi_bill->bill->charges[0]->print_url;
        }

        $transaction->save();

        $this->transaction = $transaction;

        if($this->isActive()){
            $expirationDate = Carbon::now()->addMonth(1)->format('Y-m-d H:i:s');

            $this->buy_rent->status = 'active';
            $this->buy_rent->items()->update(['expiration_date'=>$expirationDate ]);
            $this->buy_rent->save();

            $transaction->aproved_at = Carbon::now();
            $transaction->save();

        }

        return $this;
    }

    public function isActive()
    {
        return isset($this->transaction->status) && $this->transaction->status === 'active';
    }

}