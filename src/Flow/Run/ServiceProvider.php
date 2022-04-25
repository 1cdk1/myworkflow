<?php

namespace MyWorkFlow\Flow\Run;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ServiceProvider implements ServiceProviderInterface
{

    /**
     * {@inheritdoc}.
     */
    public function register(Container $app)
    {
        $app['run'] = function ($app) {
            return new Client($app);
        };
    }
}