<?php
/**
 *  the base client
 * 基类client
 */

namespace MyWorkFlow\Kernel;

use MyWorkFlow\Constants\ClientReturn;
use think\db\ConnectionInterface;
use think\Exception;

class BaseClient
{
    /**
     * @var \MyWorkFlow\Kernel\BaseContainer
     */
    protected $app;

    /**
     * @var int
     */
    protected $nowTime;

    /**
     * @var ConnectionInterface
     */
    protected $db;

    /**
     * 测试数据1
     *
     * @var array
     */
    protected $demoTemplate1
        = [
            'template_id'   => 111,     #模板ID
            'template_name' => '审批模板111',     #模板名
            'template_desc' => '用于审批啦啦啦流程',   #模板描述
            #工作流程
            'flow_process'  => [
                [
                    'process_id'       => 1,                 #步骤ID
                    'process_name'     => '生成报价单',        #步骤名
                    'process_desc'     => '创建一个报价单，并且自己审核',        #步骤描述
                    'process_type'     => 0,                 #步骤类型  0为开始 1为步骤 2为结束
                    'next_process_ids' => '2,3',                 #下一步骤IDS
                    'role_ids'         => 1,                 #审核角色IDS
                    'can_back'         => 0,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0无 1会签 2或签
                ],
                [
                    'process_id'       => 2,                 #步骤ID
                    'process_name'     => '审核项目',        #步骤名
                    'process_desc'     => '审核报价单项目是否无误',        #步骤描述
                    'process_type'     => 1,                 #步骤类型  0为开始 1为步骤 2为结束
                    'next_process_ids' => 5,                 #下一步骤IDS
                    'role_ids'         => 2,                 #审核角色IDS
                    'can_back'         => 1,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0无 1会签 2或签
                ],
                [
                    'process_id'       => 3,                 #步骤ID
                    'process_name'     => '金额审核',        #步骤名
                    'process_desc'     => '审核报价单金额是否无误',        #步骤描述
                    'process_type'     => 1,                 #步骤类型  0为开始 1为步骤 2为结束
                    'next_process_ids' => 4,                 #下一步骤IDS
                    'role_ids'         => 4,                 #审核角色IDS
                    'can_back'         => 1,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0无 1会签 2或签
                ],
                [
                    'process_id'       => 4,                 #步骤ID
                    'process_name'     => '金额终审',        #步骤名
                    'process_desc'     => '审核报价单金额是否无误',        #步骤描述
                    'process_type'     => 1,                 #步骤类型  0为开始 1为步骤 2为结束
                    'next_process_ids' => 5,                 #下一步骤IDS
                    'role_ids'         => 1,                 #审核角色IDS
                    'can_back'         => 1,                 #是否可驳回 0否 1是
                    'sign_type'        => 1,                 #签名模式 0无 1会签 2或签
                ],
                [
                    'process_id'       => 5,                 #步骤ID
                    'process_name'     => '施工审核',        #步骤名
                    'process_desc'     => '处理施工，并反馈结果',        #步骤描述
                    'process_type'     => 1,                 #步骤类型  0为开始 1为步骤 2为结束
                    'next_process_ids' => 6,                 #下一步骤IDS
                    'role_ids'         => 3,                 #审核角色IDS
                    'can_back'         => 0,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0无 1会签 2或签
                ],
                [
                    'process_id'       => 6,                 #步骤ID
                    'process_name'     => '结束',        #步骤名
                    'process_desc'     => '完整流程',        #步骤描述
                    'process_type'     => 2,                 #步骤类型  0为开始 1为步骤 2为结束
                    'next_process_ids' => 0,                 #下一步骤IDS
                    'role_ids'         => 0,                 #审核角色IDS
                    'can_back'         => 0,                 #是否可驳回 0否 1是
                    'sign_type'        => 0,                 #签名模式 0无 1会签 2或签
                ],
            ]
        ];

    protected $demoRole
        = [
            [
                'role_id' => 1,
                'name'    => '市场经营部人员',
            ],
            [
                'role_id' => 2,
                'name'    => '综合部人员',
            ],
            [
                'role_id' => 3,
                'name'    => '检测部人员',
            ],
            [
                'role_id' => 4,
                'name'    => '财务部人员',
            ],
            [
                'role_id' => 5,
                'name'    => '财务部主管',
            ],
            [
                'role_id' => 6,
                'name'    => '副总',
            ],
            [
                'role_id' => 7,
                'name'    => '总经理',
            ],
        ];

    /**
     * Constructor
     * 构造器
     *
     * @param \MyWorkFlow\Kernel\BaseContainer $app
     */
    public function __construct(BaseContainer $app)
    {
        $this->app = $app;
        $this->nowTime = time();
        //数据库
        if ($app->offsetExists('db') && $app->offsetGet('db') instanceof ConnectionInterface) {
            $this->db = $app->offsetGet('db');
        }
    }

    /**
     * 统一返回方式
     *
     * @param $status
     * @param $msg
     * @param $data
     * @param $code
     *
     * @return array|false
     */
    public function _return($status = ClientReturn::SUCCESS, $msg = '', $data = [], $code = ClientReturn::NORMAL_CODE)
    {
        if (!in_array($status, [ClientReturn::FAIL, ClientReturn::SUCCESS])) {
            return false;
        }
        return [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data,
            'code'   => $code
        ];
    }

    /**
     * 执行成功
     *
     * @param $data
     * @param $msg
     *
     * @return array|false
     */
    public function success($data, $msg = '')
    {
        return $this->_return(ClientReturn::SUCCESS, $msg, $data);
    }

    /**
     * 失败
     *
     * @param $msg
     * @param $code
     * @param $data
     *
     * @return array|false
     */
    public function fail($msg, $code = ClientReturn::NORMAL_ERROR_CODE, $data = [])
    {
        return $this->_return(ClientReturn::FAIL, $msg, $data, $code);
    }

    /**
     * 如果条件成立则抛出异常，调用时需捕获异常
     *
     * @param $condition
     * @param $msg
     *
     * @return void
     * @throws \think\Exception
     */
    public function assert($condition, $msg = '操作失败')
    {
        if ($condition) {
            throw new Exception($msg);
        }
    }
}