<?php

namespace MyWorkFlow\Flow\Run;

use MyWorkFlow\Constants\ClientReturn;
use MyWorkFlow\Constants\FlowCons;
use MyWorkFlow\Kernel\BaseClient;
use MyWorkFlow\Kernel\BaseContainer;
use MyWorkFlow\Constants\TableName;
use think\Exception;

class Client extends BaseClient
{
    public function __construct(BaseContainer $app)
    {
        parent::__construct($app);
        //必须要有数据库对象传入
        if (empty($this->db)) {
            return false;
        }
    }

    /**
     * 创建本地工作流
     *
     * @param int   $templateId 工作流模板ID
     * @param array $params     创建参数
     *
     * @return array|false
     */
    public function createFlow(int $templateId, array $params)
    {
        //拉取远端模板ID
        $template = $this->demoTemplate1;
        //组装数据
        $this->db->startTrans();
        try {
            //插入本地工作流表
            $flowData = [
                'template_id'   => $template['template_id'],
                'template_name' => $template['template_name'],
                'template_desc' => $template['template_desc'],
                'flow_name'     => $params['flow_name'],
                'flow_desc'     => $params['flow_desc'],
                'create_time'   => $this->nowTime,
                'update_time'   => $this->nowTime
            ];
            $flowId = $this->db->name(TableName::FLOW)->insertGetId($flowData);

            //插入本地工作流流程
            #拿出开始,结束及普通节点
            $processData = [];
            array_map(function ($value) use (&$processData) {
                if ($value['process_type'] == 0) {
                    $processData['start'] = $value;
                } elseif ($value['process_type'] == 2) {
                    $processData['end'] = $value;
                } else {
                    $processData['process'][$value['process_id']] = $value;
                }
            }, $template['flow_process']);
            #递归整理所有普通节点，按顺序排列
            $tmpProcess = $this->_recursionProcess($processData['process'], $processData['start']['next_process_ids']);
            #进行倒序处理，并添加回结束及开始节点。用于插入数据库
            $middleProcess = array_values(array_column(array_reverse($tmpProcess), null, 'process_id'));
            $finalProcess = array_merge([
                $processData['end']
            ], $middleProcess);
            $finalProcess[] = $processData['start'];

            #插入数据
            $localNextProcessIds = [];
            array_map(function ($value) use ($flowId, &$localNextProcessIds) {
                #计算出下一步骤的ID
                #判断是否有多个下一步
                if (stripos($value['next_process_ids'], ',') === false) {
                    #没有只需
                    $nextProcessId = empty($localNextProcessIds[$value['next_process_ids']]) ? 0
                        : $localNextProcessIds[$value['next_process_ids']];
                } else {
                    #存在多个下一步
                    $ids = [];
                    array_map(function ($id) use (&$ids, $localNextProcessIds) {
                        $ids[] = empty($localNextProcessIds[$id]) ? 0 : $localNextProcessIds[$id];;
                    }, explode(',', $value['next_process_ids']));
                    $nextProcessId = implode(',', $ids);
                }
                #组装数据
                $processData = [
                    'flow_id'          => $flowId,
                    'process_name'     => $value['process_name'],
                    'process_desc'     => $value['process_desc'],
                    'process_type'     => $value['process_type'],
                    'next_process_ids' => $nextProcessId,
                    'role_ids'         => $value['role_ids'],
                    'can_back'         => $value['can_back'],
                    'sign_type'        => $value['sign_type'],
                    'create_time'      => $this->nowTime
                ];
                $nowId = $this->db->name(TableName::PROCESS)->insertGetId($processData);
                $this->assert((!$nowId), '添加步骤失败');
                $localNextProcessIds[$value['process_id']] = $nowId;
            }, $finalProcess);

            $this->assert((!$flowId), '创建失败');

            $this->db->commit();
            return $this->success(['flow_id' => $flowId]);
        } catch (Exception $e) {
            $this->db->rollback();
            return $this->fail($e->getMessage(), ClientReturn::DATABASE_ERROR_CODE);
        }
    }

    /**
     * 发起工作流
     *
     * @param $flowId
     * @param $userId
     *
     * @return array|false
     */
    public function startFlow($flowId, $userId)
    {
        //开始工作流操作
        $this->db->startTrans();
        try {
            //找出要运行的工作流
            $flowData = $this->db->name(TableName::FLOW)->find($flowId);
            $this->assert(empty($flowData), '未找到对应工作流');

            //找出第一个步骤
            $firstProcess = $this->db->name(TableName::PROCESS)
                ->where([
                    'process_type' => 0
                ])->find();
            $this->assert(empty($firstProcess), '工作流存在错误，缺失第一步流程！');


            //发起工作流
            $runId = $this->db->name(TableName::RUN)->insertGetId([
                'user_id'         => $userId,
                'flow_id'         => $flowId,
                'now_process_ids' => $firstProcess['process_id'],
                'status'          => FlowCons::RUNNING_STATUS,
                'start_time'      => $this->nowTime,
                'create_time'     => $this->nowTime,
            ]);
            $this->assert(empty($runId), '发起工作流失败');

            //添加流程步骤
            $runProcessId = $this->db->name(TableName::RUN_PROCESS)->insertGetId([
                'run_id'       => $runId,
                'process_id'   => $firstProcess['process_id'],
                'flow_id'      => $flowId,
                'status'       => FlowCons::RUNNING_STATUS,
                'receive_time' => $this->nowTime,
                'create_time'  => $this->nowTime,
            ]);

            //添加日志
            $log = $this->db->name(TableName::RUN_LOG)->insert([
                'flow_id'    => $flowId,
                'process_id' => $firstProcess['process_id'],
                'run_id'     => $runId,
                'user_id'    => $userId,
                'content'    => '用户ID:' . $userId . '发起了新的名为' . $flowData['flow_name'] . '的工作流',
            ]);

            $this->assert(!$log || !$runProcessId || !$runId, '发起工作流失败');
            $this->db->commit();
            return $this->success(['flow_id' => $flowId]);
        } catch (Exception $exception) {
            $this->db->rollback();
            return $this->fail($exception->getMessage());
        }


    }

    /**
     * 运行流水线
     *
     * @param string $operation 操作 ok通过 back驳回 sign会签
     *
     * @param int    $flowId
     * @param array  $userInfo
     * @param array  $opInfo    操作信息
     *
     * @return array|false
     */
    public function runFlow(string $operation, int $flowId, array $userInfo, array $opInfo)
    {
        $res = Operation::$operation($this->db, $flowId, $userInfo, $opInfo);
        return $res['status'] ? $this->success($res) : $this->fail($res['msg']);
    }

    public function getNowProcess($flowId)
    {

    }

    /**
     * 按流程递归排序步骤
     *
     * @param $process
     * @param $processIds
     *
     * @return array
     */
    public function _recursionProcess($process, $processIds)
    {
        $ids = explode(',', $processIds);
        $data = [];
        foreach ($ids as $v) {
            if (isset($process[$v])) {
                $data[] = $process[$v];
                if ($process[$v]['next_process_ids']) {
                    $tmp = $this->_recursionProcess($process, $process[$v]['next_process_ids']);
                    if (!empty($tmp)) {
                        $data = array_merge($data, $tmp);
                    }
                }
            }
        }
        return $data;
    }
}