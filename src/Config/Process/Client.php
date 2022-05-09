<?php

namespace MyWorkFlow\Config\Process;

use MyWorkFlow\Kernel\BaseClient;

class Client extends BaseClient
{
    public function getList()
    {
        return json_encode([
            'data'     => [$this->demoTemplate1],
            'total'    => 1,
            'page'     => 1,
            'pre_page' => 10
        ]);
    }

    public function getOne($id)
    {
        return json_encode($this->demoTemplate1);
    }
}