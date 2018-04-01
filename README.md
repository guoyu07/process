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

$process = new process(3,[__NAMESPACE__.'\work',['param1']]);

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