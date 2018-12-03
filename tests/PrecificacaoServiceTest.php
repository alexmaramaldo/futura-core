<?php

use Univer\Entities\PriceRange;
use Univer\Facades\PricingService;
use Univer\Entities\PricingExpiration;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 *
 * RODAR ESTES TESTES COM PHPUNIT DIRETAMENTE.
 *
 * Class PrecificacaoTest
 */
class PrecificacaoTest extends \Univer\Tests\AbstractTestCase
{
    /**
     * @var \Univer\Entities\Show;
     */
    protected $show;

    use DatabaseTransactions;

    protected function setUp(){
        parent::setUp();
        $this->migrate();
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

        $this->show = \Univer\Entities\Show::create([
            'title'=>'Meu show',
            'description'=> 'Descrição do meu show',
            'cover'=>'teste.jpg',
            'type'=>'show'
        ]);

        $this->show->precificacao_item()->create([
            'item_type'=>$this->show->getMediaType(),
            'price'=>35.00,
            'discount'=>0.00,
            'price_type'=>'buy',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',-1)->first()->id
        ]);

        $this->show->precificacao_item()->create([
            'item_type'=>$this->show->getMediaType(),
            'price'=>15.90,
            'discount'=>0.00,
            'price_type'=>'rent',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',2)->first()->id
        ]);

    }

    /**
     * Cria um show e adiciona um preço individual para aluguel e venda.
     * Basicamente um teste nos relacionamentos.
     *
     * @return void
     */
    public function test_show_tem_precificacao_individual()
    {

        $precificacao = $this->show->precificacao_item()->where('price_type','buy')->first();

        $this->assertEquals($precificacao->price, 35.00);
        $this->assertEquals($precificacao->discount,0.0);

        $precificacao = $this->show->precificacao_item()->where('price_type','rent')->first();

        $this->assertEquals($precificacao->price, 15.90);
        $this->assertEquals($precificacao->discount,0.0);

        $this->assertEquals('48 horas',$precificacao->validade->name);

    }


    public function test_set_duplicate_prices_updates(){
        PricingService::setPriceForSeason($this->show,[
            'price'=>5.90,
            'discount'=>0,
            'price_type'=>'buy',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',-1)->first()->id
        ]);

        $precos = PricingService::getPricesForShow($this->show->id);

        $this->assertEquals(2,count($precos));
        $price = $precos->where('type','buy')->first()['price'];
        $this->assertEquals(5.90,$price);
    }



    public function test_associa_faixa_de_preco_a_item(){

        $faixaDePrecoVenda = PriceRange::create([
            'name'=>'Lançamentos',
            'price_type'=>'buy',
            'price'=>29.80,
            'discount'=>0
        ]);

        $faixaDePrecoAluguel = PriceRange::create([
            'name'=>'Pós Lançamento',
            'price_type'=>'rent',
            'price'=>12.00,
            'discount'=>0
        ]);


        $this->show->faixa_de_preco()->create([
            'item_type'=>$this->show->getMediaType(),
            'price_range_id'=>$faixaDePrecoVenda->id,
            'price_type'=>$faixaDePrecoVenda->price_type,
            'pricing_expiration_id'=>PricingExpiration::where('time_period',-1)->first()->id
        ]);


        $this->show->faixa_de_preco()->create([
            'item_type'=>$this->show->getMediaType(),
            'price_range_id'=>$faixaDePrecoAluguel->id,
            'price_type'=>$faixaDePrecoAluguel->price_type,
            'pricing_expiration_id'=>PricingExpiration::where('time_period',2)->first()->id
        ]);

        $this->assertEquals(2,(int)($this->show->faixa_de_preco->count()));

    }

    /**
     *
     */
    public function test_show_faixa_de_preco_sobrepoe_faixa_individual(){

//        $precos = $this->show->getPrices();

        $precos = PricingService::getPricesForShow($this->show->id);

        // Verifica que show já tem 2 precificações individuais.
        $this->assertEquals(2,count($precos));

        //Preço de compra individual é de 35,00, setado anteriormente.
        $this->assertEquals(35.00,$precos->where('type','buy')->first()['price']);
        $this->assertEquals(15.90,$precos->where('type','rent')->first()['price']);


        $faixaDePrecoVenda = PriceRange::create([
            'name'=>'Lançamentos',
            'price_type'=>'buy',
            'price'=>29.80,
            'discount'=>5.00
        ]);

        $faixaDePrecoAluguel = PriceRange::create([
            'name'=>'Pós Lançamento',
            'price_type'=>'rent',
            'price'=>12.00,
            'discount'=>0
        ]);


        $this->show->faixa_de_preco()->create([
            'item_type'=>$this->show->getMediaType(),
            'price_range_id'=>$faixaDePrecoVenda->id,
            'price_type'=>'buy',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',-1)->first()->id
        ]);


        $this->show->faixa_de_preco()->create([
            'item_type'=>$this->show->getMediaType(),
            'price_range_id'=>$faixaDePrecoAluguel->id,
            'price_type'=>'rent',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',2)->first()->id
        ]);

        $precos = PricingService::getPricesForShow($this->show->id);

        //Mesmo tendo duas seções
        $this->assertEquals(2,count($precos));

        $precoVenda = $precos->where('type','buy')->first();

        $this->assertEquals(29.80,$precoVenda['price']);

        $precoAluguel = $precos->where('type','rent')->first();
        $this->assertEquals(12.00,$precoAluguel['price']);
    }

    public function test_set_price_for_season(){
        $season = $this->show->seasons()->create([
            'title' => 'Temporada 1',
            'description' => 'Na temporada 1 desta série, vemos as palestras sobre x y z',
            'cover'=> 'teste.jpg',
            'highlight'=> 'teste.jpg',
            'status'=>1
        ]);

        PricingService::setPriceForSeason($season,[
            'price'=>15.90,
            'discount'=>5.90,
            'price_type'=>'rent',
            'pricing_expiration_id'=>PricingExpiration::where('time_period',2)->first()->id
        ]);

        $precos = PricingService::getPricesForSeason($season->id);
        $this->assertEquals(15.90,$precos->where('type','rent')->first()['price']);
    }

//
//
//    public function test_set_price_range_for_season(){
//        $priceRange = PricingService::createPriceRange([
//                'name'=>'Pré lançamentos',
//                'price'=>5.00,
//                'discount'=>4.20,
//                'price_type'=>'rent'
//            ]
//        );
//        $season = $this->show->seasons()->create([
//            'title' => 'Temporada 1',
//            'description' => 'Na temporada 1 desta série, vemos as palestras sobre x y z',
//            'status'=>1
//        ]);
//
//        PricingService::setPriceRangeForSeason($season->id,$priceRange,2);
//
//        $precos = PricingService::getPricesForSeason($season->id);
//
//        $this->assertEquals(5.00,$precos->where('type','rent')->first()['price']);
//
//        // Vamos tentar bugar os price ranges de aluguel
//        $priceRange = PricingService::createPriceRange([
//                'name'=>'Pré lançamentos',
//                'price'=>5.25,
//                'discount'=>0,
//                'price_type'=>'rent'
//            ]
//        );
//
//        /**
//         * Adicionando um preço adicional unitário na season.
//         * Ainda assim, só deverá retornar um item no método getPricesForSeason.
//         */
//        PricingService::setPriceForSeason($season,[
//            'price'=>8.00,
//            'discount'=>3.90,
//            'price_type'=>'rent',
//            'pricing_expiration_id'=>PricingExpiration::where('time_period',2)->first()->id
//        ]);
//
//
//        PricingService::setPriceRangeForSeason($season->id,$priceRange,2);
//        $precos = PricingService::getPricesForSeason($season->id);
//        $this->assertEquals(1,count($precos));
//        $this->assertEquals(5.25,$precos->where('type','rent')->first()['price']);
//
//        /**
//         * Adicionando um preço adicional unitário para COMPRA da season.
//         */
//        PricingService::setPriceForSeason($season,[
//            'price'=>15.00,
//            'discount'=>0,
//            'price_type'=>'buy',
//            'pricing_expiration_id'=>PricingExpiration::where('time_period',-1)->first()->id
//        ]);
//
//        $precos = PricingService::getPricesForSeason($season->id);
//
//        $this->assertEquals(2,count($precos));
//        $this->assertEquals(15,$precos->where('type','buy')->first()['price']);
//
//
//
//    }
}
