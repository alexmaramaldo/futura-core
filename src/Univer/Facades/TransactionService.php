<?php

namespace Univer\Facades;

use Illuminate\Support\Facades\Facade;

class TransactionService extends Facade
{
    /**
     * Nome do service provider de precificação
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'transactionservice'; }
}