<?php
// +----------------------------------------------------------------------
// | phpth|phpy: process
// +----------------------------------------------------------------------
// | Copyright (c) 2018
// +----------------------------------------------------------------------
// | Licensed MIT
// +----------------------------------------------------------------------
// | Author: luajia
// +----------------------------------------------------------------------
// | Date: 2018/6/7 0007
// +----------------------------------------------------------------------
// | Time: 下午 21:18
// +----------------------------------------------------------------------

declare(strict_types=1);

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
     * 子进程编号 注意父进程的值为：null
     * @var
     */
    protected $no ;

    /**
     * master 主进程pid
     * @var int
     */
    protected $pid ;

    /**
     * 管理进程pid 0代表是管理进程，其他正值代表是子进程，值代表父进程pid
     * @var
     */
    protected $master_pid ;

    /**
     * 子进程运行实体
     * @var
     */
    protected $callbacks;

    /**
     * 进程主标题
     * @var string
     */
    protected $process_title ;

    /**
     * 正在运行进程列表
     * @var
     */
    protected $process_list = [] ;

    /**
     * 子进程编号
     * @var
     */
    protected $full_process_no_list ;

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
     * 共享存储对象
     * @object fileShare
     */
    protected $share ;

    /**
     * @var
     */
    protected $log_file ;

    /**
     * @var
     */
    protected $log_file_handle;

    /**
     * @var
     */
    protected $d = "\r\n";

    /**
     * @var int
     */
    protected $retry_times_on_error_to_process_create = 3;

    /**
     * 信号对照表
     * @var array
     */
    protected $signal = [
        1  => 'SIGHUP',
        2  => 'SIGINT',
        3  => 'SIGQUIT',
        4  => 'SIGILL',
        5  => 'SIGTRAP',
        6  => 'SIGABRT',
        7  => 'SIGBUS',
        8  => 'SIGFPE',
        9  => 'SIGKILL',
        10 => 'SIGUSR1',
        11 => 'SIGSEGV',
        12 => 'SIGUSR2',
        13 => 'SIGPIPE',
        14 => 'SIGALRM',
        15 => 'SIGTERM',
        17 => 'SIGCHLD',
        18 => 'SIGCONT',
        19 => 'SIGSTOP',
        20 => 'SIGTSTP',
        21 => 'SIGTTIN',
        22 => 'SIGTTOU',
        23 => 'SIGURG',
        24 => 'SIGXCPU',
        25 => 'SIGXFSZ',
        26 => 'SIGVTALRM',
        27 => 'SIGPROF',
        28 => 'SIGWINCH',
        29 => 'SIGIO',
        30 => 'SIGPWR',
        31 => 'SIGSYS',
    ];

    /**
     *  初始化一些必要参数
     * process constructor.
     * @param int $process_num
     * @param mixed $callbacks 为了避免混淆，callbacks的规则是：
     * 1.如果传入字符串或者Closure ，则认为是进程创建后无参数执行的回调，
     *      例：要执行已经定义的函数test 则传入的值是 __NAMESPACE__.'\test',要执行的是一个匿名函数 则 传入的值是 function(){echo 'test';}
     * 2.如果传入 callbacks 的是数组，则会对数组的元素再次做简单检测，如果数组元素不是数组，则被认为是进程要执行的回调： Closure ，或者已经定义的类方法或者函数，
     * 是进程创建后其中的一个进程无参数执行的回调，
     *      例：传入的参数 [ __NAMESPACE__.'\test' , function(){echo 'test';}  ]
     * 3.如果传入的数组中的元素是数组，则会再次判定这个数组元素的第二个元素是否是数组且不为空，如果是数组且不为空则认定为是有参数执行，且认定第一个元素是可执行的，
     * 而且这个数组元素的格式必须符合call_user_func_array的参数格式，否则将会报错。如果这个数组元素的第二个元素不是数组，则认定为无调用参数，且认定这个元素是
     * call_user_func_array 的第一个参数。
     *      例：[
     *               [ $class,'work'],
     *               [ __NAMESPACE__.'\work' , 'static_work'],
     *               [ $work ,  ['process','success'] ],
     *               [ [$class,'work'] , ['process','success']],
     *               [ [__NAMESPACE__.'\work' , 'static_work'] , ['process','success'] ],
     *               [ __NAMESPACE__.'\work::static_work',['process','success'] ] ,
     *               [ __NAMESPACE__.'\work' , ['process','success'] ]
     *           ],
     * @param boolean|string $log 如果是传入了路径则进程执行信息或写入到文件，否则打印到屏幕。
     * @param string $process_title
     * @param int $memory_limit
     * @throws Exception
     * @throws \ReflectionException
     */
    public function __construct(int $process_num, $callbacks , $log =false , string $process_title ='php',int $memory_limit = 600)
    {
        if (version_compare(PHP_VERSION, '7.1.0') < 0) throw new Exception('请保证PHP版本在7.1或以上！');
        if (stripos(PHP_SAPI, 'cli') === false) throw new Exception('请在cli模式下运行！');
        //初始化状态量
        $this->share = new fileShare('/process/manager.shm');
        $this->pid      = getmypid();
        $this->master_pid = 0 ;
        if (!$this->share->lock()|| !$this->share->set($this->pid, $this->pid)) throw new Exception('无法写入pid信息！');
        $this->share->unlock();
        // 基础设施
        $this->process_num = $process_num > 0 ? $process_num : 1;
        $this->runFormat($callbacks);
        self::$new_count             += 1;
        $this->process_title         = "{$process_title}" . (self::$new_count>1?' '.(self::$new_count-1):'');
        // 日志路径文件处理
        if($log) {
            $this->log_file = $this->setLogPath((string)$log);
        }
        else // 输出到屏幕
        {
            $this->log_file = false ;
        }
        $this->memory_limit          = $memory_limit;
        $this->full_process_no_list  = range(0, $this->process_num - 1);
        cli_set_process_title("{$this->process_title}: master");
        pcntl_async_signals(true);
        ini_set('memory_limit','-1');
    }

    /**
     * 执行空间的额外处理
     */
    public function __destruct()
    {
        if(is_resource($this->log_file_handle))
        {
            flock($this->log_file_handle, LOCK_UN);
            fclose($this->log_file_handle);
            $this->log_file_handle = null ;
        }
        if($this->master_pid)
        {
            $this->log("【子进程：对象已经注销】 pid：{$this->pid}，进程编号：{$this->no}");
            die() ;
        }
        else
        {
            if(count($this->process_list) > 0)// 异常
            {
                $this->stop_by_exception = 1 ;
                $this->log( "【主进程：子进程停止异常】，进程pid列表：".join(',',$this->process_list)."，准备重新终止止子进程，可能的错误信息：[".join(',',(array)error_get_last()).']，trace信息：'.json_encode(debug_backtrace()));
                $this->tunStop();
                $this->wait();
            }
            $this->share->lock();
            $this->share->unset($this->pid);
            $this->share->unlock();
        }
    }

    /**
     * @param int $times 0代表会一直运行，死掉也会从其，其他值为运行循环次数
     * @param bool $sync
     * @return bool
     * @throws Exception
     */
    public final function run(int $times = 0 , bool $sync=true)
    {
        $this->times = abs($times) ;
        //信号处理
        $this->registerHandle();
        if(!$this->processCreate())
        {
            goto error;
        }
        $this->wait();
        $this->log("【主进程：正常退出】 子进程已经正常退出，主进程回收资源完毕");
        restore_error_handler();
        return true ;
        error:
        $this->stop();
        $this->log("【主进程：创建子进程出现异常】 已经停止出现的全部子进程，参考信息：错误号：".pcntl_get_last_error().'，错误信息：'.pcntl_strerror(pcntl_get_last_error()));
        restore_error_handler();
        return false ;
    }

    /**
     * Internal error handler to deal with stream and socket errors that need to be ignored
     *
     * @param  int $errno
     * @param  string $errstr
     * @param  string $errfile
     * @param  int $errline
     * @param  array $errcontext
     * @return null
     */
    public function errorHandle($errno, $errstr, $errfile, $errline, $errcontext = null)
    {
        // raise all other issues to exceptions
        #$this->stop_by_exception = 1 ;
        #throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        $this->log("【主进程：执行发生错误】 错误号：{$errno}，错误信息：{$errstr} on file {$errfile} in line {$errline}");
    }

    /**
     * 强行停止程序
     */
    public function stop():void
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
    public function tunStop():void
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
     * 信号处理
     * @param $signal
     */
    public function signalHandle(int $signal):void
    {
        switch ($signal) {
            case SIGINT:
            case SIGHUP:
            case SIGQUIT:
                $this->stop_by_signal = $signal;
                if (!$this->master_pid) {
                    $this->log("【主进程：信号处理】信号标志：{$this->signal[$signal]} ，处理：等待当前任务执行完成后结束进程");
                    $this->tunStop();
                }
                break;
            case SIGTERM:
            case SIGTSTP :
                $this->stop_by_signal = $signal;
                // 通过信号强制停止；
                if (!$this->master_pid) {
                    $this->log("【主进程：信号处理】信号标志：{$this->signal[$signal]} ，处理：终止所有进程");
                    $this->stop();
                }
                break;
            case SIGCHLD :
                if(!$this->master_pid)
                {
                    $this->log("【主进程：信号处理】信号标志：{$this->signal[$signal]} ，处理：未做处理");
                }
        }
    }

    /**
     * @param float $sleep
     */
    public function sleep( float $sleep):void
    {
        usleep((int)($sleep*1000000));
    }

    /**
     * 创建日志文件并且返回
     * @param string $log
     * @return string
     * @throws Exception
     */
    protected function setLogPath(string $log):string
    {
        $log = rtrim($log,'/\\');
        if(stripos(basename($log), '.')!==false)// 文件
        {
            $path  = dirname($log);
            if(!is_dir($path))
            {
                if(!mkdir($path,0777,true))
                {
                    throw new Exception('创建日志目录失败！');
                }
            }
        }
        else // 目录
        {
            if(!is_dir($log))
            {
                if(!mkdir($log,0777,true))
                {
                    throw new Exception('创建日志目录失败！');
                }
            }
            $log = "{$log}/{$this->process_title}-execute-info.log";
        }
        return $log;
    }

    /**
     * 子进程执行逻辑
     * @param $process_title
     * @param $callback
     */
    protected function childRun(string $process_title, array $callback):void
    {
        $this->registerHandle();
        $this->master_pid = $this->pid ;
        $this->pid = getmypid();
        cli_set_process_title($process_title);
        pcntl_sigprocmask(SIG_UNBLOCK, [SIGTSTP,SIGHUP,SIGINT,SIGQUIT,SIGTERM]);
        restore_error_handler();
        try{
            $loop_entry = $this->times?:true;
            do{
                call_user_func_array($callback[0],$callback[1]);
                $memory = round(memory_get_usage()/1024/1024 ,2);
                if($memory > $this->memory_limit)
                {
                    $this->stop_by_signal = 1 ;
                    $this->log("【子进程：内存占用超过限制】 进程编号：{$this->no} 当前进程将终止执行， pid：{$this->pid}，memory_use：{$memory}M");
                    goto end;
                }
                if($this->stop_by_signal)
                {
                    $this->log("【子进程：已经被捕获的信号停止】 进程编号：{$this->no} 信号编号：{$this->signal[$this->stop_by_signal]}，pid：{$this->pid}");
                    goto end;
                }
                if(!posix_kill($this->master_pid, 0))
                {
                    $this->log( "【子进程：父进程停止】 进程编号：{$this->no} 检测到父进程停止了，子进程即将退出，pid：{$this->pid}");
                    goto end;
                }
                if(!is_bool($loop_entry))
                {
                    --$loop_entry;
                }
            }
            while($loop_entry);
            $this->stop_by_signal = 1 ;
            $this->log("【子进程：回调函数运行次数已经达到上限】 进程编号：{$this->no} 停止执行！上限：{$this->times}");
        }
        catch(Exception $e){
            $this->stop_by_exception = 1;
            $this->log("【子进程：执行发生错误】 进程编号：{$this->no} 错误信息：{$e->getMessage()} on file {$e->getFile()} in line {$e->getLine()}，trace：  {$e->getTraceAsString()} ，code：{$e->getCode()}");
        }
        end:
        $this->log("【子进程：执行结束】 pid：{$this->pid}，进程编号：{$this->no}");
        exit ;
    }

    /**
     * 等待子进程
     */
    protected function wait():void
    {
        repeat:
        $this->checkChildStatus();
        //    捕获停止信号               异常终止                  有限次数将不会重启
        if($this->stop_by_signal || $this->stop_by_exception || $this->times)
        {
            if(count($this->process_list)> 0)
            {
                $this->sleep(1);
                goto repeat;
            }
        }
        else
        {
            $this->processCreate(true);
            $this->sleep(1);
            goto repeat;
        }
    }

    /**
     * 创建子进程
     * @param bool $flag
     * @return bool
     */
    protected function processCreate(bool $flag = false):bool
    {
        $create_result = true ;
        // 查找未运行的进程列表编号
        $exists_process_no = array_keys($this->process_list);
        $need_create_process_no = array_diff($this->full_process_no_list, $exists_process_no);
        if($need_create_process_no)
        {
            pcntl_sigprocmask(SIG_BLOCK, [SIGTSTP,SIGHUP,SIGINT,SIGQUIT,SIGTERM]);
            if (!$flag) set_error_handler([$this,'errorHandle'],E_ALL);
            foreach($need_create_process_no as $v)
            {
                $try = $this->retry_times_on_error_to_process_create ;
                re:
                $this->process_list[$v] = pcntl_fork();
                if ($this->process_list[$v] == 0)
                {
                    $this->no = $v ;
                    if ($flag ) $this->log("【子进程：进程已经被重启】 进程编号：{$v}，pid：".getmypid());
                    $this->childRun("{$this->process_title}: {$this->callbacks[$v]['title']}",$this->callbacks[$v]['callback']);
                }
                elseif ($this->process_list[$v] < 0)
                {
                    //重试
                    if($try>0)
                    {
                        $this->sleep(0.1);
                        -- $try;
                        goto re;
                    }
                    else
                    {
                        if ($flag ) $this->log("【主进程：重启进程发生异常】 异常编号：" . pcntl_get_last_error() . "，异常信息：" . pcntl_strerror(pcntl_get_last_error()));
                        $create_result = false ;
                        goto end;
                    }
                }
            }
            end:
            pcntl_sigprocmask(SIG_UNBLOCK, [SIGTSTP,SIGHUP,SIGINT,SIGQUIT,SIGTERM]);
        }
        return $create_result;
    }

    /**
     * 检查子进程状态
     */
    protected function checkChildStatus():void
    {
        foreach ( $this->process_list as $k=>$v)
        {
            $pid = pcntl_waitpid($v, $status,WNOHANG|WUNTRACED);
            if($pid > 0 || $pid == -1)
            {
                if(!pcntl_wifexited($status))// 异常退出
                {
                    // 导致进程中断的退出码
                    $exit_code = pcntl_wexitstatus($status);
                    $this->log("【主进程：子进程进程异常退出】：子进程可能是出现异常导致进程退出，进程号：{$v}，异常退出码：{$exit_code}，参考异常描述：{$this->exitCodeToString($exit_code)}");
                }
                if(pcntl_wifsignaled ($status))// 未捕获信号导致退出
                {
                    // 导致进程中断的信号
                    $signal  = pcntl_wtermsig($status);
                    $this->log("【主进程：子进程被未捕获信号中断退出】：子进程可能是由未捕获信号导致进程退出，进程号：{$v}，信号编号：{$signal}，信号字符标识：{$this->signal[$signal]}");
                }
                if (pcntl_wifstopped($status)) //未捕获的停止信号
                {
                    $signal = pcntl_wstopsig($status);
                    $this->log("【主进程：work进程未捕获信号断定】：子进程可能是由未捕获信号导致进程退出，进程号：[{$v}], 信号编号：{$signal} ,信号字符标识：{$this->signal[$signal]}");
                }
                unset($this->process_list[$k]) ;
            }
        }
    }

    /**
     * 信号注册
     */
    protected function registerHandle():void
    {
        pcntl_signal(SIGTERM, array($this, 'signalHandle'));
        pcntl_signal(SIGQUIT, array($this, 'signalHandle'));
        pcntl_signal(SIGINT, array($this, 'signalHandle'));
        #pcntl_signal(SIGCHLD, array($this, 'signalHandle'));
        pcntl_signal(SIGHUP, array($this, 'signalHandle'));
        pcntl_signal(SIGTSTP , array($this, 'signalHandle'));
    }

    /**
     * @param $call
     * @throws Exception
     */
    protected function checkCall(array $call):void
    {
        if(empty($call[0]) || !isset($call[1]) || !is_array($call[1]) )
        {
            throw new Exception('子进程执行格式错误！');
        }
        //判断执行提是否可执行
        if(!is_callable($call[0]))
        {
            throw new Exception('无法执行的回调！');
        }
    }

    /**
     * 格式化回调
     * @param $callbacks
     * @throws Exception
     * @throws \ReflectionException
     */
    protected  function runFormat( $callbacks):void
    {
        if(empty($callbacks))
        {
            throw new Exception('进程执行体不能为空');
        }
        if(is_array($callbacks))
        {
            $callbacks_num = count($callbacks);
            if($callbacks_num < $this->process_num)
            {
                $callbacks = array_merge($callbacks,array_fill($callbacks_num, $this->process_num-$callbacks_num, end($callbacks)));
            }
        }
        else
        {
            $callbacks = array_fill(0, $this->process_num, [$callbacks,[]]);
        }
        foreach($callbacks as  $k=> $v)
        {
            if(is_array($v))
            {
                // 判断是不是带参数，不带参数则将整个数组认为是call_user_func_array 的第一个参数
                if(is_array($v[1])) // 带参数
                {
                    if(empty($v[1]))
                    {
                        $param = '[no param]';
                    }
                    else
                    {
                        $param = '['.join(',',$v[1]).']' ;
                    }
                    if(is_string($v[0]))
                    {
                        $name = $v[0];
                    }
                    else if(is_array($v[0]))
                    {
                        if(is_string($v[0][0]))
                        {
                            $name = "{$v[0][0]}::{$v[0][1]}";
                        }
                        else
                        {
                            $ref = new ReflectionClass($v[0][0]);
                            $name = "{$ref->getName()}->{$v[0][1]}";
                        }
                    }
                    else
                    {
                        $ref = new ReflectionClass($v[0]);
                        $name = "{$ref->getName()}";
                    }
                }
                else // 不带参数
                {
                    $param = '[no param]';
                    if(is_string($v[0]))
                    {
                        $name = "{$v[0]}::{$v[1]}";
                    }
                    else
                    {
                        $ref = new ReflectionClass($v[0]);
                        $name = "{$ref->getName()}->{$v[1]}";
                    }
                    //结构化成[ [clss,td]  [param,param2] ]
                    $v = [ $v, [ ] ];
                }
            }
            elseif(is_string($v))// 例如：__NAMESPACE__ .'\Foo::test' ,function(){} 等等;
            {
                $name = $v;
                $v = [$v, []] ;
                $param = '[no param]';
            }
            else
            {
                $ref = new ReflectionClass($v);
                $name = "{$ref->getName()}";
                $v = [$v, []] ;
                $param = '[no param]';
            }
            $this->checkCall($v);
            $this->callbacks[] = ['callback'=>$v , 'title'=>"{$name}{$param}"];
        }
    }

    /**
     * 日志处理
     * @param string $log_string
     */
    protected function log(string $log_string):void
    {
        $log_string = "[".date("Y-m-d H:i:s")."] {$log_string}".PHP_EOL;
        if($this->log_file)
        {
            if(!is_resource($this->log_file_handle))
            {
                $this->log_file_handle = fopen($this->log_file,'a');
            }
            flock($this->log_file_handle, LOCK_EX);
            fwrite($this->log_file_handle, $log_string);
            flock($this->log_file_handle, LOCK_UN);
        }
        else
        {
            echo $log_string;
        }
    }

    /**
     * 根据错误码判断退出信息
     * @param $exit_code
     * @return string
     */
    protected function exitCodeToString( $exit_code):string
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