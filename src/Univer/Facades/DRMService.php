<?php

namespace Univer\Facades;

use Illuminate\Support\Facades\Facade;

class DRMService extends Facade
{
    /**
     * DRMService
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'drmservice'; }
}