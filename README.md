# 简单好用的php进程控制类
基于pcntl的简单进程控制
>安装
```bash
composer require phpth/process
```
>使用
```php
<?php
namespace test;
use phpth\process\process;
//开启三个进程


function work($param=false){
    echo 'do work~~~'.microtime(true).PHP_EOL;
     sleep(1);
}

$process = new process(3,
        [[__NAMESPACE__.'\work',['param1']],
        [__NAMESPACE__.'\work',['param1']],
        [__NAMESPACE__.'\work',['param1']],
]);
//或者不传入参数
$process = new process(3,[__NAMESPACE__.'\work',__NAMESPACE__.'\work',__NAMESPACE__.'\work']);
//或者
#$process = new process(3,__NAMESPACE__.'\work');

//$process->run(3); // 三个进程，每个进程运行三次work函数
$process->run(0); // 三个进程，每个进程都无限循环的运行work函数


//process参数说明

/**
 * process($process_num, $callbacks , $process_title ='php process', $process_run_time_path = false,$memory_limit = 600)
 * @param $process_num int 开启的子进程数
 * @param $callbacks mixed 进程运行体
 * @param string $process_title string 进程名称
 * @param bool $process_run_time_path 进程执行日志存放路径
 * @param int $memory_limit float 每个进程的最大内存限制
 */


```
>5.5或以上时设置进程名称才会生效
```php
    //传入参数时
    root@linuxkit-00155d006647:/m/www/JiuRongWang# ps aux 
    USER        PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
    root        155  0.7  1.3 205040 26448 pts/2    S+   10:03   0:00 php process : master
    root        156  0.0  0.4 205040  9584 pts/2    S+   10:03   0:00 php process : test\work[param1]
    root        157  0.0  0.4 205040  9584 pts/2    S+   10:03   0:00 php process : test\work[param1]
    root        158  0.0  0.4 205040  9584 pts/2    S+   10:03   0:00 php process : test\work[param1]


    //不传参数时
    root@linuxkit-00155d006647:/m/www/JiuRongWang# ps aux 
    USER        PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
    root        160  0.3  1.3 205040 26376 pts/2    S+   10:06   0:00 php process 1: master
    root        161  0.0  0.4 205040  9640 pts/2    S+   10:06   0:00 php process 1: test\work[no param]
    root        162  0.0  0.4 205040  9640 pts/2    S+   10:06   0:00 php process 1: test\work[no param]
    root        163  0.0  0.4 205040  9640 pts/2    S+   10:06   0:00 php process 1: test\work[no param]



```