<?php

namespace MyWorkFlow\Config\Role;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ServiceProvider implements ServiceProviderInterface
{

    /**
     * {@inheritdoc}.
     */
    public function register(Container $app)
    {
        $app['role'] = function ($app) {
            return new Client($app);
        };
    }
}