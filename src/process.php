<?php
// +----------------------------------------------------------------------
// | phpth\process
// +----------------------------------------------------------------------
// | Copyright (c) 2018
// +----------------------------------------------------------------------
// | Licensed MIT
// +----------------------------------------------------------------------
// | Author: luajia
// +----------------------------------------------------------------------

namespace phpth\process;

use ReflectionClass;
use Exception;

/**
 * Class process
 * @package phpth\process
 */
class process
{
    /**
     * 实例化次数
     * @var int
     */
    public static $new_count = 0;

    /**
     * 进程数量
     * @var int
     */
    protected $process_num ;

    /**
     * 代表运行多少次会死掉 0代表一直运行
     * @var
     */
    protected $times;

    /**
     * 内存限制
     * @var int
     */
    protected $memory_limit;

    /**
     * master 主进程pid
     * @var int
     */
    protected $pid ;

    /**
     * 子进程运行实体
     * @var
     */
    protected $callbacks;

    /**
     * 日志和pid目录
     * @var string
     */
    protected $process_run_time_path;

    /**
     * 进程主标题
     * @var string
     */
    protected $process_title ;

    /**
     * @var
     */
    protected $process_log_file ;

    /**
     * 正在运行进程列表
     * @var
     */
    protected $process_list = [] ;

    /**
     * 信号停止标记
     * @var
     */
    protected $stop_by_signal ;

    /**
     * 异常停止
     */
    protected $stop_by_exception;

    /**
     * 子进程编号
     * @var
     */
    protected $full_process_no_list ;

    /**
     * 打开的日志文件句柄
     * @var
     */
    protected $file_handle ;

    /**
     * 管理进程pid 0代表是管理进程，其他正值代表是子进程，值代表父进程pid
     * @var
     */
    protected $master_pid ;

    /**
     * 共享存储操作对象
     * @object fileShare
     */
    protected $share ;

    /**
     * 信号对照表
     * @var array
     */
    protected $signal = [
        1  => 'SIGHUP', 2 => 'SIGINT', 3 => 'SIGQUIT', 4 => 'SIGILL', 5 => 'SIGTRAP', 6 => 'SIGABRT', 7 => 'SIGBUS',
        8  => 'SIGFPE', 9 => 'SIGKILL', 10 => 'SIGUSR1', 11 => 'SIGSEGV', 12 => 'SIGUSR2', 13 => 'SIGPIPE',
        14 => 'SIGALRM', 15 => 'SIGTERM', 17 => 'SIGCHLD', 18 => 'SIGCONT', 19 => 'SIGSTOP', 20 => 'SIGTSTP',
        21 => 'SIGTTIN', 22 => 'SIGTTOU', 23 => 'SIGURG', 24 => 'SIGXCPU', 25 => 'SIGXFSZ', 26 => 'SIGVTALRM',
        27 => 'SIGPROF', 28 => 'SIGWINCH', 29 => 'SIGIO', 30 => 'SIGPWR', 31 => 'SIGSYS',
    ];

    /**
     * pid 信息文件
     */
    const MASTER_PID_FILE = '/process/master_pid.j';

    /**
     * 初始化一些必要参数
     * process constructor.
     * @param $process_num
     * @param $callbacks
     * @param string $process_title
     * @param bool $process_run_time_path
     * @param int $memory_limit
     * @throws Exception
     * @throws \ReflectionException
     */
    public function __construct($process_num, $callbacks , $process_title ='php process', $process_run_time_path = false,$memory_limit = 600)
    {
        if (version_compare(PHP_VERSION, '5.4.0') < 0) throw new Exception('请保证PHP版本在5.4或以上!');
        if (stripos(PHP_SAPI, 'cli') === false) throw new Exception('请在cli模式下运行!');
        // 运行时文件
        $this->process_run_time_path = rtrim($process_run_time_path ? : __DIR__.'/run' ,'/\\');
        if (!is_dir($this->process_run_time_path) && !mkdir($this->process_run_time_path, 0777, true)) throw new Exception('无法创建运行时目录');
        $this->process_run_time_path = realpath($this->process_run_time_path);
        if (!$this->process_run_time_path) throw new Exception('运行时目录定位错误');
        //初始化状态量
        $this->process_num = $process_num > 0 ? $process_num : 1;
        $this->share = new fileShare(self::MASTER_PID_FILE);
        $this->pid      = getmypid();
        $this->master_pid = 0 ;
        if (!$this->share->lock()|| !$this->share->set($this->pid, $this->process_num)) throw new Exception('无法写入 pid 信息!');
        $this->share->unlock();
        // 基础设施
        $this->runFormat($callbacks);
        self::$new_count             += 1;
        $this->process_title         = "{$process_title}" . (self::$new_count>1?' '.(self::$new_count-1):'');
        $this->memory_limit          = $memory_limit;
        $this->process_log_file      = "{$this->process_run_time_path}/{$this->process_title}.log";
        $this->full_process_no_list  = range(0, $this->process_num - 1);
        if(function_exists('cli_set_process_title'))cli_set_process_title("{$this->process_title}: master");
        function_exists('pcntl_async_signals')?pcntl_async_signals(true):define('DISPATCH_SIGNAl', 1);
    }

    public function __destruct()
    {
        if($this->master_pid)
        {
            if(is_resource($this->file_handle))
            {
                flock($this->file_handle, LOCK_UN);
                fclose($this->file_handle);
            }
            die() ;
        }
        else
        {
            if(count($this->process_list) > 0)// 主进程异常停止
            {
                $this->stop_by_exception = 1 ;
                $this->tunStop();
                $this->wait(1);
                $this->log("【主进程异常停止】,进程pid:{$this->pid},已终止所有子进程，可能的错误信息：[".join(',',(array)error_get_last()).'],trace 信息：'.json_encode(debug_backtrace()));
            }
            $this->share->lock();
            $this->share->del($this->pid);
            $this->share->unlock();
            if(is_resource($this->file_handle))
            {
                flock($this->file_handle, LOCK_UN);
                fclose($this->file_handle);
            }
        }
    }

    /**
     * 强行停止程序
     */
    public function stop()
    {
        if($this->process_list)
        {
            foreach($this->process_list as $k=>$v)
            {
                if($v>0)
                {
                    posix_kill($v ,SIGKILL) ;
                    unset($this->process_list[$k]);
                }
            }
        }
    }

    /**
     * 发送停止信号;
     */
    public function tunStop()
    {
        if(is_array($this->process_list))
        {
            foreach($this->process_list as $v)
            {
                posix_kill($v, SIGINT);
            }
        }
    }

    /**
     * @param float $sleep
     */
    public function sleep($sleep)
    {
        usleep((int)($sleep*1000000));
    }

    /**
     * @param int $times 运行次数
     * @param bool $sync 是否同步 (未来特性)
     * @return bool
     * @throws Exception
     */
    public final function run( $times = 0 ,  $sync=true)
    {
        $this->times = $times ;
        //信号处理
        $this->registerHandle();
        for($i =0;$i<$this->process_num ; $i++)
        {
            $this->process_list[$i] = pcntl_fork();
            if($this->process_list[$i] == 0)
            {
                $this->childRun("{$this->process_title}: {$this->callbacks[$i]['title']}",$this->callbacks[$i]['callback']);
            }
            elseif($this->process_list[$i] < 0)
            {
                goto error;
            }
        }
        mast:
        $this->wait($times);
        $this->log("【正常退出】 主进程和子进程都已经正常退出");
        return true ;
        error:
        $this->stop();
        $this->log("【创建子进程出现异常】 已经停止出现的全部子进程，参考信息：错误号：".pcntl_get_last_error().',错误信息：'.pcntl_strerror(pcntl_get_last_error()));
        return false ;
    }

    /**
     * @param $process_title
     * @param $callback
     * @throws Exception
     */
    protected function childRun($process_title,$callback)
    {
        if(!isset($callback[0])|| !isset($callback[1]))
        {
            throw new Exception('进程执行回调格式错误!');
        }
        $this->registerHandle();
        $this->master_pid = $this->pid ;
        $this->pid = getmypid();
        if(function_exists('cli_set_process_title'))cli_set_process_title($process_title);
        try{
            $loop_entry = $this->times?:true;
            do{
                call_user_func_array($callback[0],$callback[1]);
                if(defined('DISPATCH_SIGNAl') && DISPATCH_SIGNAl) pcntl_signal_dispatch();
                $memory = round(memory_get_usage()/1024/1024 ,2);
                if($memory > $this->memory_limit)
                {
                    $this->stop_by_signal = 1 ;
                    $this->log("【子进程内存占用超过限制】 已经终止, pid:{$this->pid},memory_use: {$memory}M ");
                    goto end;
                }
                if($this->stop_by_signal)
                {
                    $this->log("【子进程已经被捕获的信号停止】  pid:{$this->pid} ");
                    goto end;
                }
                if(!posix_kill($this->master_pid, 0))
                {
                    $this->log("【父进程停止】检测到父进程停止了，子进程即将退出  pid:{$this->pid} ");
                    goto end;
                }
                if(!is_bool($loop_entry))
                {
                    $loop_entry--;
                }
            }
            while($loop_entry);
            $this->stop_by_signal = 1 ;
            $this->log("【子进程内回调函数运行次数已经达到上限】 停止执行！,上限：{$this->times}");
        }
        catch(Exception $e){
            $this->stop_by_exception = 1;
            $this->log("【子进程执行发生错误】 错误信息：{$e->getMessage()} on file {$e->getFile()} in line {$e->getLine()} {$e->getTraceAsString()} {$e->getCode()}");
        }
        end:
        exit ;
    }

    /**
     * 等待子进程
     */
    protected function wait($times)
    {
        repeat:
        if(defined('DISPATCH_SIGNAl') && DISPATCH_SIGNAl) pcntl_signal_dispatch();
        $this->checkChildStatus();
        //    捕获停止信号               异常终止
        if($this->stop_by_signal || $this->stop_by_exception || $times)
        {
            if(count($this->process_list)> 0)
            {
                $this->sleep(1);
                goto repeat;
            }
        }
        else
        {
            $this->ifChildStopCreated();
            $this->sleep(1);
            goto repeat;
        }
    }

    /**
     * 重建子进程
     */
    protected function ifChildStopCreated()
    {
        # 查找丢失的进程列表编号
        $exists_process_no = array_keys($this->process_list);
        $need_create_process_no = array_diff($this->full_process_no_list, $exists_process_no);
        if($need_create_process_no)
        {
            foreach($need_create_process_no as $v)
            {
                $this->process_list[$v] = pcntl_fork();
                if ($this->process_list[$v] == 0)
                {
                    $this->childRun("{$this->process_title}: {$this->callbacks[$v]['title']}",$this->callbacks[$v]['callback']);
                }
                elseif ($this->process_list[$v] < 0)
                {
                    $this->log("【重启进程发生异常】 异常编号：" . pcntl_get_last_error() . ",异常信息：" . pcntl_strerror(pcntl_get_last_error()));
                }
            }
        }
    }

    /**
     * 检查子进程状态
     */
    protected function checkChildStatus()
    {
        foreach ( $this->process_list as $k=>$v)
        {
            $pid = pcntl_waitpid($v, $status,WNOHANG);
            if($pid > 0 || $pid == -1)
            {
                if(!pcntl_wifexited($status))// 异常退出
                {
                    // 导致进程中断的退出码
                    $exit_code = pcntl_wexitstatus($status);
                    $this->log("【work进程异常中断断定】：子进程可能是出现异常导致进程退出，进程号：[{$v}], 异常退出码：{$exit_code},参考异常描述：[{$this->exitCodeToString($exit_code)}]");
                }
                if(pcntl_wifsignaled ($status))// 未捕获信号导致退出
                {
                    // 导致进程中断的信号
                    $signal  = pcntl_wtermsig($status);
                    $this->log("【work进程未捕获信号断定】：子进程可能是由未捕获信号导致进程退出，进程号：[{$v}], 信号编号：{$signal} ,信号字符标识：{$this->signal[$signal]}");
                }
                #====================================================================
                #
                # pcntl_waitpid 调用时第三个参数为 WUNTRACED 调用此段才有效
                #
                # if(pcntl_wifstopped($status)) {
                #   $signal= pcntl_wstopsig($status)
                #   $this->log("【work进程未捕获信号断定】：子进程可能是由未捕获信号导致进程退出，进程号：[{$v}], 信号编号：{$signal} ,信号字符标识：{$this->signal[$signal]}");
                # };
                #
                #====================================================================
                /*wait方式：阻塞式等待，等待的子进程不退出时，父进程一直不退出；
                目的：回收子进程，系统回收子进程的空间。
                依据传统，返回的整形状态字是由实现定义的，其中有些位表示退出状态（正常返回），其他位表示信号编号（异常返回），有的位表示是否产生了一个core文件等。
                终止状态是定义在 sys/wait.h中的各个宏，有四个可互斥的宏可以用来取得进程终止的原因。
                WIFEXITED：若为正常终⽌⼦进程返回的状态，则为真。可以执行宏函数 WEXITSTATUS获取子进程状态传送给exit、_exit或_Exit的参数的低8位。（查看进程是否正常退出）
                WIFSIGNALED：若为异常终⽌⼦进程返回的状态(收到⼀个未捕捉的信号)，则为真。可以执行宏函数WTERMSIG取得子进程终止的信号编号。另外，对于一些定义有宏WCOREDUMP宏，若以正常终止进程的core文件，则为真。
                WIFSTOPPED ：若为当前暂停子进程的返回的状态，则为真，可执行WSTOPSIG取得使子进程暂停的信号编号。
                WIFCONTINUED：若在作业控制暂停后已经继续的子进程返回了状态，则为真，仅用于waitpid。
                举例：
                1. 正常创建⽗⼦进程，⼦进程正常退出，⽗进程等待，并获取退出状态status。调⽤该宏，查看输出结果（正常为⾮0，或1）。
                2. 正常创建⽗⼦进程，⼦进程pause()，⽗进程等待，并设置获取退出状态status， kill杀掉⼦进程。调⽤该宏，查看输出结果（结果为0）。
                3. 若WIFEXITED⾮零，返回⼦进程退出码，提取进程退出返回值，如果⼦进程调⽤exit(7),WEXITSTATUS(status)就会返回7。请注意,如果进程不是正常退出的,也就是说,WIFEXITED返回0,这个值就毫⽆意义.。
                */
                unset($this->process_list[$k]) ;
            }
        }
    }

    /**
     * 信号注册
     */
    protected function registerHandle()
    {
        pcntl_signal(SIGTERM, array($this, 'signalHandle'));
        pcntl_signal(SIGQUIT, array($this, 'signalHandle'));
        pcntl_signal(SIGINT, array($this, 'signalHandle'));
        pcntl_signal(SIGCHLD, array($this, 'signalHandle'));
        pcntl_signal(SIGHUP, array($this, 'signalHandle'));
    }

    /**
     * 信号处理
     * @param $signal
     */
    public function signalHandle($signal)
    {
        switch ($signal) {
            case SIGHUP:
            case SIGQUIT:
                $this->stop_by_signal = 1;
                if (!$this->master_pid) {
                    $this->tunStop();
                }
                break;
            case SIGINT:
            case SIGTERM:
                $this->stop_by_signal = 1;
                // 通过信号强制停止；
                if (!$this->master_pid) {
                    $this->stop();
                    echo 'process killed' . PHP_EOL;
                }
                break;
            case SIGCHLD :
        }
    }

    /**
     * 格式化回调
     * @param $callbacks
     * @throws Exception
     * @throws \ReflectionException
     */
    protected  function runFormat($callbacks)
    {
        if(empty($callbacks))
        {
            throw new Exception('进程执行体不能为空');
        }
        if(is_array($callbacks))
        {
            if(count($callbacks)<=1)
            {
                $callbacks = array_fill(0,$this->process_num,$callbacks[0]);
            }
            foreach($callbacks as  $k=> $v)
            {
                if(is_array($v))
                {
                    if(is_array($v[0])) // 例如：  [ [new test(), 'paraf'] , [4, 5, 6]],
                    {
                        if(empty($v[1]))
                        {
                            $param = '[no param]';
                        }
                        else
                        {
                            $param = '['.join(',',$v[1]).']';
                        }
                        if(is_string($v[0][0]))
                        {
                            $name = $v[0][0];
                        }
                        else
                        {
                            $ref = new ReflectionClass($v[0][0]);
                            $name = $ref->getName();
                        }
                        $this->callbacks[] = ['callback'=>$v , 'title'=>"{$name}{$param}"];
                    }
                    else if(is_callable($v[0]))// 例如：[function ($a) {  echo 'TEST!anmous!!' . microtime(true) . PHP_EOL; sleep(1); },[1]]
                    {
                        if(empty($v[1]))
                        {
                            $param = '[no param]';
                        }
                        else
                        {
                            $param = '['.join(',',$v[1]).']';
                        }
                        if(is_string($v[0]))
                        {
                            $name  = $v[0];
                        }
                        else
                        {
                            $ref = new ReflectionClass($v[0]);
                            $name = $ref->getName() ;
                        }
                        $this->callbacks[] = [ 'callback'=>$v,'title'=>"{$name}{$param}"];
                    }
                    else if(is_object($v[0])) // 例如: [new test(), 'op'],
                    {
                        $ref = new ReflectionClass($v[0]);
                        $this->callbacks[] = ['callback'=>[$v,[]] ,'title'=>"{$ref->getName()}->{$v[1]}[no param]"];
                    }
                    else if(is_string($v[0])) // 例如 ： [__NAMESPACE__ .'\Foo::test',[]]
                    {
                        if(empty($v[1]))
                        {
                            $param = '[no param]';
                        }
                        else
                        {
                            $param = '['.join(',',$v[1]).']';
                        }
                        $this->callbacks[] = [ 'callback'=>$v,'title'=>$v[0].$param];
                    }
                    else //例如：  __NAMESPACE__ .'\Foo::test'  ，'function_name',
                    {
                        $this->callbacks[] = [ 'callback'=>[$v,[]],'title'=>$v.'[no param]'];
                    }
                }
                else// 例如：__NAMESPACE__ .'\Foo::test' ,function(){} 等等;
                {
                    if(is_string($v))
                    {
                        $name = $v;
                    }
                    else
                    {
                        $ref = new ReflectionClass($v);
                        $name = $ref->getName();
                    }
                    $this->callbacks[] = ['callback'=>[$v, []] , 'title'=>"{$name}[no param]" ];
                }
            }
        }
        else // 例如：__NAMESPACE__ .'\Foo::test' ,function(){} 等等;
        {
            if(is_string($callbacks))
            {
                $name = $callbacks;
            }
            else
            {
                $ref = new ReflectionClass($callbacks);
                $name =$ref->getName() ;
            }
            for($i =0 ; $i<$this->process_num ; $i++ )
            {
                $this->callbacks[] = ['callback'=>[ $callbacks , [] ] ,'title'=>"{$name}[no param]"];
            }
        }
    }

    /**
     * 记录日志
     * @param $log_string
     */
    protected function log($log_string)
    {
        if(!is_resource($this->file_handle))
        {
            $this->file_handle = fopen($this->process_log_file,'a');
        }
        flock($this->file_handle, LOCK_EX);
        $log_string = "{$log_string} [".date("Y-m-d H:i:s")."]".PHP_EOL;
        fwrite($this->file_handle, $log_string);
        flock($this->file_handle, LOCK_UN);
    }

    /**
     * 根据错误码判断退出信息
     * @param $exit_code
     * @return string
     */
    protected function exitCodeToString($exit_code)
    {
        $string = '';
        switch ($exit_code)
        {
            case 0:
                $string = '正常退出';
                break;
            case 1:
                $string = '普通错误如：df -abcd,参数错误';
                break;
            case 2:
                $string = 'shell内建的错误如：	empty_function() {}	，还可能是缺少关键字或命令,或者权限问题';
                break;
            case $exit_code>2&&$exit_code<128:
                $string = '未知原因，奇葩的底层错误!';
                break;
            case  $exit_code>128&&$exit_code<=159:
                $string = "信号引起的错误，信号标识:[{$this->signal[$exit_code-128]}]";
                break;
            case $exit_code >159:
                $string = '未知原因 ，可能是退出状态超过范围	exit -1	exit只接受0 ~ 255范围的退出码';
        }
        return $string ;
    }
}