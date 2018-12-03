<?php

namespace Univer\Tests;

use Orchestra\Testbench\TestCase;

abstract class AbstractTestCase extends TestCase
{

    public function migrate()
    {
        $this->artisan('migrate:refresh', [
            '--realpath' => realpath(__DIR__ . '/../src/resources/migrations')
        ]);
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return ['Univer\Providers\CoreServiceProvider'];
    }

    protected function getPackageAliases($app)
    {
        return [
//            'TransactionService' => 'Univer\Facades\TransactionService',
            'PricingService' => 'Univer\Facades\PricingService'
        ];
    }

}