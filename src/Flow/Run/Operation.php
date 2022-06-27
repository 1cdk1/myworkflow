<?php

namespace MyWorkFlow\Flow\Run;

use MyWorkFlow\Constants\ClientReturn;
use MyWorkFlow\Constants\FlowCons;
use MyWorkFlow\Constants\TableName;
use think\db\ConnectionInterface;
use think\Exception;

/**
 *
 * @method static ok(ConnectionInterface $db, $flowId, $userInfo, $opInfo)
 * @method static back(ConnectionInterface $db, $flowId, $userInfo, $opInfo)
 * @method static sign(ConnectionInterface $db, $flowId, $userInfo, $opInfo)
 */
class Operation
{
    public static function __callStatic($name, $arguments)
    {
        list($db, $flowId, $userInfo, $opInfo) = $arguments;
        #校验操作类型
        if (!in_array($name, ['ok', 'back', 'sign'])) {
            return [
                'status' => ClientReturn::FAIL,
                'msg'    => '操作不存在',
            ];
        }
        #校验数据库配置
        if ($db instanceof ConnectionInterface) {
            try {
                #运行信息
                $runData = $db->name(TableName::RUN)->where('flow_id', $flowId)->find();
                if (empty($runData)) {
                    throw new Exception('未找到该运行中的流水线');
                }
                #当前步骤
                $nowProcessArr = $db->name(TableName::PROCESS)
                    ->whereIn('process_id', explode(',', $runData['now_process_ids']))
                    ->select()
                    ->toArray();
                if (empty($nowProcessArr)) {
                    throw new Exception('当前流水线存在错误,请重新创建');
                }
                #判断角色是否可进行操作
                #获取传入角色可操作的步骤，如果不存在则为没有操作权限
                $nowProcess = null;
                array_map(function ($value) use ($userInfo, &$nowProcess) {
                    $roleIds = explode(',', $value['role_ids']);
                    if (in_array($userInfo['role_id'], $roleIds) && $nowProcess === null) {
                        $nowProcess = $value;
                    }
                }, $nowProcessArr);
                if ($nowProcess === null) {
                    throw new Exception('当前角色没有权限操作');
                }

                $db->startTrans();
                switch ($name) {
                    case 'sign':#添加审批
                        #更新运行日志
                        $logRes = $db->name(TableName::RUN_LOG)->insert([
                            'flow_id'     => $flowId,
                            'process_id'  => $nowProcess['process_id'],
                            'run_id'      => $runData['run_id'],
                            'user_id'     => $userInfo['user_id'],
                            'create_time' => time(),
                            'content'     => '用户ID:' . $userInfo['user_id'] . '新建了转出审批流程，参与审批的角色有' . $opInfo['sign_role_id']
                        ]);

                        #在当前流程之后新增审批。将当前流程结束。并插入新的审批流程
                        $newProcess = [
                            'flow_id'          => $flowId,
                            'process_name'     => '转出审批',
                            'level'            => $nowProcess['level'],
                            'branch_pid'       => $nowProcess['branch_pid'],
                            'next_process_ids' => $nowProcess['next_process_ids'],
                            'process_desc'     => '新增的转出审批流程',
                            'sign_type'        => $opInfo['sign_type'],
                            'role_ids'         => $opInfo['sign_role_id'],
                            'can_back'         => 1,
                            'process_type'     => FlowCons::STEP_PROCESS,
                        ];
                        $newProcessId = $db->name(TableName::PROCESS)->insertGetId($newProcess);

                        #更新审批流程顺序,将当前流程的下一步ID更新为新流程ID，
                        $nowProcessUpdate = $db->name(TableName::PROCESS)
                            ->where('process_id', $nowProcess['process_id'])
                            ->update([
                                'next_process_ids' => $newProcessId
                            ]);
                        #重新获取当前流程
                        $nowProcess = $db->name(TableName::PROCESS)->find($nowProcess['process_id']);

                    #接着走审批通过流程
                    case 'ok':#通过
                        #更新运行日志
                        $logRes = $db->name(TableName::RUN_LOG)->insert([
                            'flow_id'     => $flowId,
                            'process_id'  => $nowProcess['process_id'],
                            'run_id'      => $runData['run_id'],
                            'user_id'     => $userInfo['user_id'],
                            'create_time' => time(),
                            'content'     => '用户ID:' . $userInfo['user_id'] . '审批通过了' . $nowProcess['process_name'] . '步骤' . ',审核意见:'
                                . $opInfo['approval_opinion']
                        ]);

                        $saveProcess = false;
                        #todo 判断当前步骤会签还是或签
                        if ($nowProcess['sign_type'] == FlowCons::SIGN_AND) {
                            #需要会签通过角色
                            $needSign = explode(',', $nowProcess['role_ids']);
                            #获取当前步骤会签情况
                            $nowProcessRun = $db->name(TableName::RUN_PROCESS)->where([
                                'process_id' => $nowProcess['process_id'],
                                'run_id'     => $runData['run_id'],
                                'flow_id'    => $flowId,
                                'status'     => FlowCons::PROCESSING,
                            ])->find();
                            #已经会签通过角色
                            $isSign = explode(',', $nowProcessRun['is_sign']);
                            if (in_array($userInfo['role_id'], $isSign)) {
                                throw new Exception('该步骤你已经处理过，无需重复处理');
                            }
                            #会签角色
                            empty($nowProcessRun['is_sign']) ? $isSign = [$userInfo['role_id']] : $isSign[] = $userInfo['role_id'];
                            #审批意见
                            $approvalOpinion = explode(',', $nowProcessRun['approval_opinion']);
                            $text = '角色ID为' . $userInfo['role_id'] . '的' . $userInfo['user_id'] . "用户给出的审批意见为"
                                . $opInfo['approval_opinion'];
                            empty($nowProcessRun['approval_opinion']) ? $approvalOpinion = [$text] : $approvalOpinion[] = $text;
                            $opInfo['approval_opinion'] = implode(',', array_unique($approvalOpinion));
                            #更新会签数据
                            $saveProcess = $db->name(TableName::RUN_PROCESS)->where([
                                'process_id' => $nowProcess['process_id'],
                                'run_id'     => $runData['run_id'],
                                'flow_id'    => $flowId,
                                'status'     => FlowCons::PROCESSING,
                            ])->update([
                                'handle_time'      => time(),
                                'is_sign'          => implode(',', array_unique($isSign)),
                                'approval_opinion' => $opInfo['approval_opinion']
                            ]);
                            if (count($needSign) == count($isSign)) {
                                $isFinish = true;
                            } else {
                                $isFinish = false;
                            }
                        } else {
                            $isFinish = true;
                        }

                        #如果当前步骤已完成
                        if ($isFinish) {
                            #把当前运行步骤设为完成
                            $saveProcess = $db->name(TableName::RUN_PROCESS)->where([
                                'process_id' => $nowProcess['process_id'],
                                'run_id'     => $runData['run_id'],
                                'flow_id'    => $flowId,
                                'status'     => FlowCons::PROCESSING,
                            ])->update([
                                'status'           => FlowCons::AGREE,
                                'handle_time'      => time(),
                                'approval_opinion' => $opInfo['approval_opinion']
                            ]);
                        }

                        #判断是否为网关类，如为网关类是否满足条件
                        if ($nowProcess['process_type'] == FlowCons::GATEWAY_PROCESS
                            && self::checkCondition(
                                json_decode($nowProcess['condition'], true), $opInfo['condition']
                            )
                        ) {
                            #获取下一步骤
                            $nextProcess = $db->name(TableName::PROCESS)
                                ->whereIn('process_id', $nowProcess['jump_process_id'])
                                ->select()
                                ->toArray();
                            if (empty($nextProcess)) {
                                throw new Exception('工作流存在异常，未找到下一步骤');
                            }
                            $needInsert = true;
                        } else {
                            #获取下一步骤
                            $nextProcess = $db->name(TableName::PROCESS)
                                ->whereIn('process_id', explode(',', $nowProcess['next_process_ids']))
                                ->select()
                                ->toArray();
                            if (empty($nextProcess)) {
                                throw new Exception('工作流存在异常，未找到下一步骤');
                            }
                            #检查下一步骤之前的步骤是否有全部完成，没有则不动作
                            $beforeProcessIds = $db->name(TableName::PROCESS)
                                ->whereIn('next_process_ids', array_column($nextProcess, 'process_id'))
                                ->where('process_id', '<>', $nowProcess['process_id'])
                                ->column('process_id');
                            $beforeProcessRun = $db->name(TableName::RUN_PROCESS)
                                ->whereIn('process_id', $beforeProcessIds)
                                ->where('status', FlowCons::AGREE)
                                ->count('process_id');
                            #如果下一步骤之前的步骤已全部完成,则插入下一步骤
                            $needInsert = count($beforeProcessIds) == $beforeProcessRun ? true : false;
                        }
                        if ($needInsert) {
                            #组装数据
                            $nextProcessRunData = [];
                            array_map(function ($value) use (&$nextProcessRunData, $runData, $flowId) {
                                $nextProcessRunData[] = [
                                    'run_id'       => $runData['run_id'],
                                    'process_id'   => $value['process_id'],
                                    'flow_id'      => $flowId,
                                    'status'       => $value['process_type'] == FlowCons::END_PROCESS ? FlowCons::FINISH
                                        : FlowCons::RUNNING_STATUS,
                                    'receive_time' => time(),
                                    'create_time'  => time(),
                                ];
                            }, $nextProcess);
                            $insertRunProcess = $db->name(TableName::RUN_PROCESS)->insertAll($nextProcessRunData);
                        } else {
                            $insertRunProcess = true;
                        }

                        #获取当前还在运行的步骤
                        $nowProcessIds = $db->name(TableName::RUN_PROCESS)
                            ->where([
                                'run_id'  => $runData['run_id'],
                                'flow_id' => $flowId,
                                'status'  => FlowCons::PROCESSING
                            ])
                            ->column('process_id');

                        #是否已经没有在执行的步骤
                        if (!empty($nowProcessIds)) {
                            #更新运行表
                            $updateRun = $db->name(TableName::RUN)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                ])
                                ->update([
                                    'now_process_ids' => implode(',', $nowProcessIds),
                                    'update_time'     => time()
                                ]);
                        } else {
                            #结束步骤
                            $endProcessId = $db->name(TableName::RUN_PROCESS)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                    'status'  => FlowCons::END_PROCESS
                                ])
                                ->value('process_id');
                            #变更运行表为已结束
                            $updateRun = $db->name(TableName::RUN)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                ])
                                ->update([
                                    'status'          => FlowCons::END_STATUS,
                                    'now_process_ids' => $endProcessId ? $endProcessId : '',
                                    'end_time'        => time(),
                                    'update_time'     => time(),
                                ]);
                        }
                        if ($saveProcess && $logRes && $insertRunProcess && $updateRun) {
                            $db->commit();
                            return [
                                'status' => ClientReturn::SUCCESS,
                                'msg'    => '操作成功',
                            ];
                        } else {
                            throw new Exception('操作保存失败');
                        }
                        break;
                    case 'back':#驳回
                        if (empty($nowProcess['can_back'])) {
                            throw new Exception('当前步骤不支持驳回');
                        }
                        #把当前步骤设为已驳回
                        $saveProcess = $db->name(TableName::RUN_PROCESS)->where([
                            'process_id' => $nowProcess['process_id'],
                            'run_id'     => $runData['run_id'],
                            'flow_id'    => $flowId,
                            'status'     => FlowCons::PROCESSING,
                        ])->update([
                            'status'           => FlowCons::ROLL_BACK,
                            'handle_time'      => time(),
                            'is_back'          => $opInfo['back_process_id'] ? 1 : 0,
                            'approval_opinion' => $opInfo['approval_opinion']
                        ]);
                        #如果有回退ID则回退到对应步骤，无则全部直接结束
                        if ($opInfo['back_process_id']) {
                            #更新运行日志
                            $logRes = $db->name(TableName::RUN_LOG)->insert([
                                'flow_id'     => $flowId,
                                'process_id'  => $nowProcess['process_id'],
                                'run_id'      => $runData['run_id'],
                                'user_id'     => $userInfo['user_id'],
                                'create_time' => time(),
                                'content'     => '用户ID:' . $userInfo['user_id'] . '驳回了' . $nowProcess['process_name'] . '步骤' . ',审核意见:'
                                    . $opInfo['approval_opinion']
                            ]);
                            #找出要回退到的步骤，回退步骤level必须>=当前level-1
                            $rollbackProcess = $db->name(TableName::PROCESS)
                                ->where('process_id', $opInfo['back_process_id'])
                                ->whereBetween('level', implode(',', [$nowProcess['level'] - 1, $nowProcess['level']]))
                                ->find();
                            if (empty($rollbackProcess)) {
                                throw new Exception('未找到可退回步骤');
                            }
                            #判断当前运行的步骤中是否存在level大于要回退到的流程，并且步骤的pid和当前步骤pid相同
                            $needFinishProcessIds = $db->name(TableName::PROCESS)
                                ->where([
                                    ['level', '>', $rollbackProcess['level']],
                                    ['branch_pid', '=', $nowProcess['branch_pid']],
                                ])
                                ->column('process_id');
                            var_dump($needFinishProcessIds);
                            $saveAllProcess = $db->name(TableName::RUN_PROCESS)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                    'status'  => FlowCons::PROCESSING,
                                ])
                                ->whereIn('process_id', $needFinishProcessIds)
                                ->update([
                                    'status'           => FlowCons::FINISH,
                                    'handle_time'      => time(),
                                    'approval_opinion' => '步骤回退关闭相关流程'
                                ]);

                            #找出上一步步骤
                            #回退步骤
                            $rollbackProcessRunData = [
                                'run_id'       => $runData['run_id'],
                                'process_id'   => $rollbackProcess['process_id'],
                                'flow_id'      => $flowId,
                                'status'       => FlowCons::RUNNING_STATUS,
                                'receive_time' => time(),
                                'create_time'  => time(),
                            ];
                            $insertRunProcess = $db->name(TableName::RUN_PROCESS)->insert($rollbackProcessRunData);

                            #当前仍在运行的步骤
                            $nowProcessIds = $db->name(TableName::RUN_PROCESS)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                    'status'  => FlowCons::PROCESSING
                                ])
                                ->column('process_id');

                            #是否已经没有在执行的步骤
                            if (!empty($nowProcessIds)) {
                                #更新运行表
                                $updateRun = $db->name(TableName::RUN)
                                    ->where([
                                        'run_id'  => $runData['run_id'],
                                        'flow_id' => $flowId,
                                    ])
                                    ->update([
                                        'now_process_ids' => implode(',', $nowProcessIds),
                                        'update_time'     => time()
                                    ]);

                            } else {
                                throw new Exception('系统异常，请稍后重试');
                            }
                        } else {
                            #全部退回
                            #更新运行日志
                            $logRes = $db->name(TableName::RUN_LOG)->insert([
                                'flow_id'     => $flowId,
                                'process_id'  => $nowProcess['process_id'],
                                'run_id'      => $runData['run_id'],
                                'user_id'     => $userInfo['user_id'],
                                'create_time' => time(),
                                'content'     => '用户ID:' . $userInfo['user_id'] . '驳回了整个流程' . ',审核意见:' . $opInfo['approval_opinion']
                            ]);
                            #所有处理中步骤变为已结束
                            $saveAllProcess = $db->name(TableName::RUN_PROCESS)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                    'status'  => FlowCons::PROCESSING,
                                ])
                                ->update([
                                    'status'           => FlowCons::FINISH,
                                    'handle_time'      => time(),
                                    'approval_opinion' => '步骤已被全部打回'
                                ]);
                            #变更运行表为已结束
                            $updateRun = $db->name(TableName::RUN)
                                ->where([
                                    'run_id'  => $runData['run_id'],
                                    'flow_id' => $flowId,
                                ])
                                ->update([
                                    'status'          => FlowCons::END_STATUS,
                                    'now_process_ids' => '',
                                    'end_time'        => time(),
                                    'update_time'     => time(),
                                ]);
                            $insertRunProcess = true;
                        }
                        if ($saveProcess && $logRes && $updateRun && $insertRunProcess) {
                            $db->commit();
                            return [
                                'status' => ClientReturn::SUCCESS,
                                'msg'    => '操作成功',
                            ];
                        } else {
                            echo $saveProcess . ',' . $logRes . ',' . $saveAllProcess . ',' . $updateRun;
                            throw new Exception('操作保存失败');
                        }
                }

            } catch (Exception $e) {
                return [
                    'status' => ClientReturn::FAIL,
                    'msg'    => $e->getMessage(),
                ];
            }
        } else {
            return [
                'status' => ClientReturn::FAIL,
                'msg'    => '数据库配置异常',
            ];
        }
    }

    /**
     * 检查条件是否全部通过
     *
     * @param $role array 条件
     * @param $now  array 当前情况
     *
     * @return bool
     */
    public static function checkCondition(array $role, array $now): bool
    {
        #通过个数
        $pass = 0;
        array_map(function ($v, $k) use ($now, &$pass) {
            if (isset($now[$k]) && $v == $now[$k]) {
                $pass++;
            }
        }, $role, array_keys($role));
        #判断规则个数与通过个数是否相等
        return count($role) == $pass;
    }
}