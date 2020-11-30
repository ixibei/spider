<?php

namespace Ixibei\Spider\Facades;

use Illuminate\Support\Facades\Facade;

class Spider extends Facade{

    protected static function getFacadeAccessor() {
        return 'spider';
    }

}