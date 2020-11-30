<?php

namespace Ixibei\Spider;

use Illuminate\Support\ServiceProvider;

class SpiderServiceProvider extends ServiceProvider{
    public function register(){
        $this->app->singleton('spider',function(){
            return new Json();
        });
    }
}