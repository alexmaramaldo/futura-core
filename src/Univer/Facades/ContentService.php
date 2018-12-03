<?php

namespace Univer\Facades;

use Illuminate\Support\Facades\Facade;

class ContentService extends Facade
{
    /**
     * Nome do service provider de precificação
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'contentservice'; }
}