<?php
/**
 * Created by PhpStorm.
 * User: souldigital
 * Date: 21/10/16
 * Time: 11:22
 */

namespace Univer\Providers;


use Illuminate\Support\ServiceProvider;

class CodeCategoryServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([__DIR__ . '/../../resources/migrations' => base_path('database/migrations')], 'migrations');
    }

    public function register()
    {

    }
}