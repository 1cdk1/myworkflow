<?php
/**
 * This file is used to operate the flow
 */

namespace MyWorkFlow\Flow;

use MyWorkFlow\Kernel\BaseContainer;

/**
 * class Application
 *
 * @property \MyWorkFlow\Flow\Run\Client $run
 */
class Application extends BaseContainer
{
    /**
     * @var array
     */
    protected $providers
        = [
            Run\ServiceProvider::class
        ];

}