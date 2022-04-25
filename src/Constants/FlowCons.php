<?php

namespace MyWorkFlow\Constants;

class FlowCons
{
    //工作流执行状态
    const WAIT_STATUS = 0;
    const RUNNING_STATUS = 1;
    const END_STATUS = 2;

    //工作流步骤状态
    const WAIT_PROCESS = 0;
    const PROCESSING = 1;
    const FINISH = 2;
    const ROLL_BACK = -1;

    #步骤类型
    const START_PROCESS = 0;
    const STEP_PROCESS=1;
    const END_PROCESS=2;
}