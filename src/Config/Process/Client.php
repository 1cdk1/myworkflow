<?php

namespace MyWorkFlow\Config\Process;

use MyWorkFlow\Kernel\BaseClient;

class Client extends BaseClient
{
    public function getList()
    {
        return 11111;
    }

    public function getOne($id)
    {
        return json_encode($this->demoTemplate1);
    }
}