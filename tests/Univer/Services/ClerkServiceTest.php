<?php
//
//namespace Univer\Tests\Entities;
//
//use App\User;
//use Illuminate\Foundation\Testing\DatabaseTransactions;
//
//use Univer\Entities\BuyRent;
//use Univer\Entities\GiftCardTvod;
//use Univer\Entities\PricingExpiration;
//use Univer\Entities\Show;
//use Univer\Entities\Video;
//use Univer\Facades\ClerkService;
//use Univer\Facades\ContentService;
//use Univer\Facades\PricingService;
//
//class ClerkServiceTest extends \TestCase
//{
//    use DatabaseTransactions;
//
//    protected $title;
//
//    public function setUp()
//    {
//        parent::setUp();
//    }
//
//    public function test_user_can_see_content()
//    {
//
//        $show = ContentService::createShow([
//            'title'=>'Show Teste 1',
//            'description' => "A série se passa num universo paralelo."
//        ]);
//
//        $payment = [
//            'payment_method'  => 'apple_pay',
//            'receipt'=>'XADLFKJA123IADF',
//            'transaction_id'=>1,
//            'in_app_product_id'=>1
//        ];
//
//        $payment_data = [
//            'value' => '25.99',
//            'rental_type' => 'buy',
//            'payment' => $payment,
//            'item_type' => $show->getMediaType()
//        ];
//
//        \TransactionService::buyOrRentContent($payment_data);
//        $canwatch = ClerkService::getContentByApplePayReceipt($payment['receipt']);
//        $this->assertArrayHasKey('rented_object',$canwatch[0]);
//    }
//
//    public function test_user_can_watch_episode_if_bought_show()
//    {
//        // Testa se ao comprar uma série, usuário tem acesso à todos os episodios da mesma
//        $show= Show::whereHas('seasons')->first();
//        $season = $show->seasons()->first();
//        $video = Video::where('id',3776)->first();
//
//        $user = User::first();
//
////        // Usuário não deve ter acesso ao conteúdo.
//        $this->assertFalse(ClerkService::userCanWatchContent($user->id,$show));
//        $this->assertFalse(ClerkService::userCanWatchContent($user->id,$season));
//        $this->assertFalse(ClerkService::userCanWatchContent($user->id,$video));
//
//        // Define o preço do show
//        PricingService::setPriceForShow($show,[
//            'price'=>15.90,
//            'discount'=>0,
//            'price_type'=>'rent',
//            'pricing_expiration_id'=>PricingExpiration::where('time_period',2)->first()->id
//        ]);
//        // Quanto vale o show? PricingService::getPricesForShow($show);
//
//        $hash = rand(999,99999);
//        GiftCardTvod::create([
//            'code'=> $hash,
//            'valid_days'=>365,
//            'item_type'=>$show->getMediaType(),
//            'item_id'=>$show->getMediaId()
//        ]);
//
//        $payment = [
//            'payment_method'  => 'gift_card',
//            'code' =>  $hash
//        ];
//
//        $payment_data = [
//            'user_id' => $user->id,
//            'user_name' => 'Ariel Rick And Morty',
//            'user_email' => 'arielcontiricky@gmail.com',
//            'value' => '25.99',
//            'rental_type' => 'buy',
//            'payment' => $payment,
//            'item_id' => $show->getMediaId(),
//            'item_type' => $show->getMediaType()
//        ];
//
//        $transaction = \TransactionService::buyOrRentContent($payment_data);
//
//        $this->assertTrue($transaction->id > 0);
//        $this->assertTrue(BuyRent::where('user_id',$user->id)->first()->item_id === $show->getMediaId());
//
//        ClerkService::userCanWatchContent($user->id,$show);
//        $canWatch = ClerkService::userCanWatchContent($user->id,$video);
//
//        $this->assertTrue($canWatch);
//    }
//}