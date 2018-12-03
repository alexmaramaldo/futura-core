<?php
/**
 *  setPrice(id_tem, tipo_item, objPrice * ver API).

outros métodos publicos:
setPriceForShow(id_show)
setPriceForSeason(id_season,id_show)
setPriceForEpisode(id_episode,id_season,id_show)
getPrices(id_item,tipo_item,rental_type (buy|rent))
 *
 */
namespace Univer\Providers;


use Univer\Entities\BuyRent;
use Univer\Entities\Movie;
use Univer\Entities\Show;
use Univer\Entities\Video;
use Univer\Entities\Season;
use Univer\Services\ClerkService;
use Univer\Services\DRMService;
use Univer\Services\PricingService;
use Univer\Services\ContentService;
use Illuminate\Support\ServiceProvider;
use Univer\Services\TransactionService;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
      $this->loadMigrationsFrom(__DIR__.'../../resources/migrations/');
    }

    /**
     *
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();

        $this->registerPricingService();

        $this->registerContentService();

        $this->registerTransactionService();

        $this->registerClerkService();

        $this->registerDRMService();

        $this->registerAliases();
    }

    protected function registerConfig()
    {

    }


    protected function registerPricingService()
    {
        $this->app->bind('pricingservice', function () {
            return new PricingService(new Video(), new Show(), new Season(), new Movie());
        });

        /**
         * Carrega rotas de precificação
         */
        if (! $this->app->routesAreCached()) {
            require (__DIR__.'/../Routes/pricing.php');
        }
    }

    protected function registerContentService()
    {
        $this->app->bind('contentservice', function () {
            return new ContentService(new Video(), new Show(), new Season());
        });
    }

    protected function registerTransactionService()
    {
        $this->app->bind('transactionservice', function () {
            return new TransactionService(new BuyRent());
        });

        /**
         * @todo Alterar codigos de produto dependendo do ambiente
         */
        $this->app->singleton('pricing_types',function(){
          if($this->app->environment() != 'staging'){
            //Produção
            return [
              'buy'=>46332,
              'rent'=>46331
            ];
          } else{
            //Hml, testes
            return [
                'buy'=>40469,
                'rent'=>40471
            ];
          }
        });

    }

    protected function registerClerkService()
    {
        $this->app->bind('clerkservice', function () {
            return new ClerkService(new BuyRent());
        });

    }

    protected function registerDRMService()
    {
        $this->app->bind('drmservice', function () {
            return new DRMService();
        });

    }

    protected function registerAliases(){
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();

        $loader->alias('PricingService', 'Univer\Facades\PricingService');
        $loader->alias('DRMService', 'Univer\Facades\DRMService');
        $loader->alias('ContentService', 'Univer\Facades\ContentService');
        $loader->alias('ClerkService', 'Univer\Facades\ClerkService');
        $loader->alias('TransactionService', 'Univer\Facades\TransactionService');

    }

}
