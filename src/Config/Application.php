<?php
/**
 * This file is get config for the platform
 * 平台配置
 */

namespace MyWorkFlow\Config;

use MyWorkFlow\Kernel\BaseContainer;

/**
 * Class Application.
 *
 * @property \MyWorkFlow\Config\Process\Client $process
 * @property \MyWorkFlow\Config\Role\Client    $role
 */
class Application extends BaseContainer
{

    /**
     * @var array
     */
    protected $providers
        = [
            Role\ServiceProvider::class,
            Process\ServiceProvider::class
        ];

}