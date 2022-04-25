<?php

namespace MyWorkFlow\Config\Process;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ServiceProvider implements ServiceProviderInterface
{

    /**
     * @inheritDoc
     */
    public function register(Container $app)
    {
        $app['process'] = function ($app) {
            return new Client($app);
        };
    }
}