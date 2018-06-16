<?php

namespace phpth\process;

use Exception;

class manager
{
    /**
     * pid信息文件
     */
    const MASTER_PID_FILE='/process/master_pid.j';

    protected $share ;

    protected $pid_info ;

    protected $cmd ;

    /**
     * manager constructor.
     * @throws \Exception
     */
    public function __construct( $run_cmd ,$log_path = false  )
    {
        $this->share = new fileShare(self::MASTER_PID_FILE);
        if(!$run_cmd)
        {
            throw new Exception('运行命令必须');
        }
        // 分析命令构成
        $cmd_arr = preg_split("/\s+/",$run_cmd);
        if(preg_match('/(\/\w+$)|(^\w+$)/', $cmd_arr))//使用了PHP命令
        {
            $this->chRoot($cmd_arr[1]);
        }
        else
        {// 未使用php命令
            $this->chRoot($cmd_arr[0]);
        }


        $cmd['notice_use'] = "echo {$this->color('use','绿色')} {$this->color('[reload,start,stop,kill]','红色')} {$this->color('to manager service !','绿色')}";
        $cmd['notice_reboot'] = "echo {$this->color('正在重新启动中~~~','红色')}";
        $cmd['notice_have_runing'] = "echo {$this->color("已有服务在运行,请使用ps查看,pid:%s",'红色')}";
        $cmd['notice_after_run'] = "nohup  >> {$log_path} 2>&1 &";
        $cmd['notice_after_run'] = "echo {$color("服务已经启动,pid:".join(',',$pid),'绿色')}";
        $cmd['notice_after_run'] = "echo {$color("正在平滑停止,等待全部任务停止可能需要时间!，请耐心等待~",'红色')}";
        $cmd['notice_after_run'] = PHP_BINARY . ' ' . ROOT_PATH . 'think sms:stop  >> ' . ROOT_PATH . 'service.output.log 2>&1 &';
        $cmd['notice_after_run'] = "echo {$color("进程已经全部停止!",'绿色')}";
        $cmd['notice_after_run'] = "echo {$color("kill参数非常危险!他会造成数据任务不一致或者数据丢失~~~;echo 你确定继续吗?(y|n)",'红色')}";
        $cmd['notice_after_run'] = "echo pid:{$color(join(',',$pid)." is killed !",'红色')}";



    }

    public static function getExecCmd()
    {

    }

    /**
     * @param $cmd
     * @return string
     */
    public function chRoot($cmd)
    {
        return chroot(dirname($cmd)) ;
    }

    public function color($string, $color)
    {
        $_color = [
            '黑色'   => '"\033[30m%s\033[0m"',
            '红色'   => '"\033[31m%s\033[0m"',
            '绿色'   => '"\033[32m%s\033[0m"',
            '黄色'   => '"\033[33m%s\033[0m"',
            '蓝色'   => '"\033[34m%s\033[0m"',
            '紫色'   => '"\033[35m%s\033[0m"',
            '天蓝'   => '"\033[36m%s\033[0m"',
            '白色'   => '"\033[37m%s\033[0m"',
            '黑底白'  => '"\033[40;37m%s\033[0m"',
            '红底白'  => '"\033[41;37m%s\033[0m"',
            '绿底白'  => '"\033[42;37m%s\033[0m"',
            '黄底白'  => '"\033[43;37m%s\033[0m"',
            '蓝底白'  => '"\033[44;37m%s\033[0m"',
            '紫底白'  => '"\033[45;37m%s\033[0m"',
            '天蓝底白' => '"\033[46;37m%s\033[0m"',
            '白底黑'  => '"\033[47;30m%s\033[0m"',
        ];
        return sprintf($_color[$color], $string);
    }


    public function getProcessId()
    {
        global $color;
        $process_pid = [];
        if (file_exists(ROOT_PATH . 'runtime/mobile/master.pid')) {
            $pid = file_get_contents(ROOT_PATH . 'runtime/mobile/master.pid');
            if ($pid) {
                exec('ps -eo pid,ppid|grep ' . $pid, $res);
                foreach ($res as $v) {
                    $process_pid[] = preg_split('/\s+/', trim($v))[0];
                }
            } else {
                system("echo {$color("can't find pid!",'红色')}");
            }
        }
        return $process_pid;
    }
}

if (!empty($argv[1])) {
    switch ($argv[1]) {
        case 'reload':
            reload();
            break;
        case 'start':
            start();
            break;
        case 'stop':
            stop();
            break;
        case 'kill':
            kill();
            break;
        default:
            goto err;
    }
} else {
    err:
    system("echo {$color('use','绿色')} {$color('[reload,start,stop,kill]','红色')} {$color('to manager mobile service !','绿色')}");
}

function reload()
{
    global $color;
    stop();
    system("echo {$color('正在重新启动中~~~','红色')}");
    start();
}

function start()
{
    global $color;
    $pid = get_process_pid();
    if (current($pid)) {
        if (posix_kill(current($pid), 0)) {
            system("echo {$color("已有服务在运行,请使用ps查看,pid:".join(',',$pid),'红色')}");
            return;
        }
    }
    popen("nohup " . PHP_BINARY . ' ' . ROOT_PATH . 'think sms:start  >> ' . ROOT_PATH . 'service.output.log 2>&1 &', 'r');
    sleep(1);
    $pid = get_process_pid();
    system("echo {$color("服务已经启动,pid:".join(',',$pid),'绿色')}");
}

function stop()
{
    global $color;
    system("echo {$color("正在平滑停止,等待全部任务停止可能需要时间!，请耐心等待~",'红色')}");
    $pid = get_process_pid();
    popen(PHP_BINARY . ' ' . ROOT_PATH . 'think sms:stop  >> ' . ROOT_PATH . 'service.output.log 2>&1 &', 'r');
    sleep:
    foreach ($pid as $v) {
        if (posix_kill($v, 0)) {
            sleep(1);
            goto sleep;
        }
    }
    system("echo {$color("进程已经全部停止!",'绿色')}");
}

function kill()
{
    global $color;
    system("echo {$color("kill参数非常危险!他会造成数据任务不一致或者数据丢失~~~",'红色')}");
    echo '你确定继续吗?(y|n) ';
    $input = fgetc(STDIN);
    if (stripos($input, 'y') !== false) {
        global $color;
        $pid = get_process_pid();
        if ($pid) {
            foreach ($pid as $v) {
                posix_kill($v, SIGKILL);
            }
            system("echo pid:{$color(join(',',$pid)." is killed !",'红色')}");
        }
    } else {
        system("echo {$color("已经终止kill~",'绿色')}");
    }
}


