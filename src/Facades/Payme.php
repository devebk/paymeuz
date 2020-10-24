<?php

namespace devebk\payme\Facades;


use devebk\payme\Builder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \devebk\payme\Builder to($config_array)
 */
class Payme extends Facade {


    protected static function getFacadeAccessor()
    {
        return Builder::class;
    }

}
