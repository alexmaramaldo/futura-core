<?php

namespace Univer\Tests\Entities;
use Illuminate\Http\Request;
use Univer\Entities\Show;
use Univer\Entities\GiftCardTvod;
use Univer\Facades\PricingService;
use Univer\Tests\AbstractTestCase;
use Univer\Entities\PricingExpiration;
use Univer\Facades\TransactionService;


class BuyRentTest extends AbstractTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->migrate();

        $this->title = \Univer\Entities\Show::create([
            'title'=>'Meu show',
            'description'=> 'Descrição do meu show',
            'cover' => 'img1.jpg',
            'type' => 'show'
        ]);

        $this->title2 = \Univer\Entities\Show::create([
            'title'=>'Meu show 2',
            'description'=> 'Descrição do meu show',
            'cover' => 'img2.jpg',
            'type' => 'show'
        ]);

        PricingExpiration::create([
            'name' => 'Indefinido',
            'time_period' => -1
        ]);
        PricingExpiration::create([
            'name' => '48 horas',
            'time_period' => 2
        ]);
        PricingExpiration::create([
            'name' => '1 ano',
            'time_period' => 365
        ]);

        GiftCardTvod::create([
           'code' => '123123123',
            'valid_days' => '365'
        ]);

        $this->title->precificacao_item()->create([
            'item_type'=>$this->title->getMediaType(),
            'price'=>35.00,
            'discount'=>3.90,
            'price_type'=>'buy',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',-1)->first()->id
        ]);

        $this->title->precificacao_item()->create([
            'item_type'=>$this->title->getMediaType(),
            'price'=>15.90,
            'discount'=>3.90,
            'price_type'=>'rent',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',2)->first()->id
        ]);
        $this->title2->precificacao_item()->create([
            'item_type'=>$this->title->getMediaType(),
            'price'=>35.00,
            'discount'=>3.90,
            'price_type'=>'buy',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',-1)->first()->id
        ]);

        $this->title2->precificacao_item()->create([
            'item_type'=>$this->title->getMediaType(),
            'price'=>15.90,
            'discount'=>3.90,
            'price_type'=>'rent',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',2)->first()->id
        ]);

    }

    public function test_create_buy_bank_slip()
    {
        $payment = [
            'payment_method'  => 'bank_slip',
            'cpf' => '42116096880'
        ];

        $payment_data = [
            'user_id' => 1,
            'user_name' => 'Ariel Rick And Morty',
            'user_email' => 'arielcontiricky@gmail.com',
            'value' => '25.99',
            'rental_type' => 'buy',
            'payment' => $payment,
            'items'=>[
                ['item_id' => 1, 'item_type' => 'show'],
                ['item_id'=>2,'item_type'=>'show']
            ]
        ];

        $compra = TransactionService::buyOrRentContent($payment_data);

        $this->assertNull($compra->expiration_date);

        $this->assertEquals('pending', $compra->transaction()->first()->status);
        $this->assertEquals(2,$compra->items()->count());

    }

    public function test_create_buy_rent_gift_card()
    {
        $payment = [
            'payment_method'  => 'gift_card',
            'code' => '123123123'
        ];

        $payment_data = ['user_id' => 1,
            'user_name' => 'Ariel Rick And Morty',
            'user_email' => 'arielcontiricky@gmail.com',
            'value' => '25.99',
            'rental_type' => 'buy',
            'payment' => $payment,
            'items'=>[
                ['item_id' => 1, 'item_type' => 'show']
            ]
        ];


        $compra = TransactionService::buyOrRentContent($payment_data);
        $this->assertNotNull($compra->expiration_date);

    }

    public function test_can_create_apple_pay_transaction(){
        $payment = [
            'payment_method'  => 'apple_pay',
            'receipt'=>'XADLFKJA123IADF',
            'transaction_id'=>1,
            'in_app_product_id'=>1
        ];

        $payment_data = ['user_id' => 1,
            'user_name' => 'Ariel Rick And Morty',
            'user_email' => 'arielcontiricky@gmail.com',
            'value' => '25.99',
            'rental_type' => 'buy',
            'payment' => $payment,
            'items'=>[
                ['item_id' => 1, 'item_type' => 'show']
            ]
        ];

        $compra = TransactionService::buyOrRentContent($payment_data);
        $this->assertNotNull($compra->expiration_date);
}
}