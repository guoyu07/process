# 简便封装的多进程协作类
基于pcntl的简单进程控制
>安装
```bash
composer require phpth/process
```
>说明

1.错误处理：
- 多进程执行过程中，主进程如果发生任何级别的错误，将会记录或者输出到屏幕
- 一般非调试情况下，请将进程执行日志和程序执行过程中业务逻辑出输出隔离开来，即类实力化时传入第三个参数

2.信号处理：
- 父进程和子进程都默认了对部分信号的处理，分别是SIGTERM, SIGQUIT, SIGINT, SIGCHLD，SIGHUP, SIGTSTP
- 收到 SIGTERM 和 SIGSTP 信号主进程会强制kill掉子进程
- 收到 SIGQUIT, SIGINT, SIGCHLD，SIGHUP 信号则会等待子进程本次任务完结后自动退出
 
3.调用参数：
- 第一个参数为开启的进程数，注意不包括主进程。
- 第二个参数为进程的执行体，可以使匿名函数，类的静态方法，预定于的函数，或者类方法
- 第三个参数可以控制进程执行过程的信息输出模式，默认为false，代表直接输出到屏幕。如果传入文件路径则写入文件内，目录和文件不存在则会新建。

4.版本变化说明
- 1.0到2.0以前的版本为兼容php5.4及以上的版本，后续除了修复bug，将不再更新。因为7.1之前php的信号处理很低效，7.1引入了一步信号，大大增强了信号处理的性能，推荐使用php7.1及以上。
- 2.0以上为强制使用php7.1及以上版本，后续会一直维护，包括可能新增的功能

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
$process = new process(3,__NAMESPACE__.'\work');

$process->run(3); // 三个进程，每个进程运行三次work函数

// run方法第二个参数为预留，方便以后无缝新增特性
$process->run(0); // 三个进程，每个进程都无限循环的运行work函数




// 传入callback 的说明
/* 了避免混淆，callbacks的规则是：
      1.如果传入字符串或者Closure ，则认为是进程创建后无参数执行的回调，
          例：要执行已经定义的函数test 则传入的值是 __NAMESPACE__.'\test',要执行的是一个匿名函数 则 传入的值是 function(){echo 'test';}
      2.如果传入 callbacks 的是数组，则会对数组的元素再次做简单检测，如果数组元素不是数组，则被认为是进程要执行的回调： Closure ，或者已经定义的类方法或者函数，
      是进程创建后其中的一个进程无参数执行的回调，
           例：传入的参数 [ __NAMESPACE__.'\test' , function(){echo 'test';}  ]
      3.如果传入的数组中的元素是数组，则会再次判定这个数组元素的第二个元素是否是数组且不为空，如果是数组且不为空则认定为是有参数执行，且认定第一个元素是可执行的，
      而且这个数组元素的格式必须符合call_user_func_array的参数格式，否则将会报错。如果这个数组元素的第二个元素不是数组，则认定为无调用参数，且认定这个元素是
      call_user_func_array 的第一个参数。
           例：[
                    [ $class,'work'],
                    [ __NAMESPACE__.'\work' , 'static_work'],
                    [ $work ,  ['process','success'] ],
                    [ [$class,'work'] , ['process','success']],
                    [ [__NAMESPACE__.'\work' , 'static_work'] , ['process','success'] ],
                    [ __NAMESPACE__.'\work::static_work',['process','success'] ] ,
                    [ __NAMESPACE__.'\work' , ['process','success'] ]
                ], 
*/

```
>5.5或以上时设置进程名称才会生效
```php
    //传入参数时
    root@linuxkit-00155d006647:/m/www# ps aux 
    USER        PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
    root        155  0.7  1.3 205040 26448 pts/2    S+   10:03   0:00 php process : master
    root        156  0.0  0.4 205040  9584 pts/2    S+   10:03   0:00 php process : test\work[param1]
    root        157  0.0  0.4 205040  9584 pts/2    S+   10:03   0:00 php process : test\work[param1]
    root        158  0.0  0.4 205040  9584 pts/2    S+   10:03   0:00 php process : test\work[param1]


    //不传参数时
    root@linuxkit-00155d006647:/m/wwwg# ps aux 
    USER        PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
    root        160  0.3  1.3 205040 26376 pts/2    S+   10:06   0:00 php process 1: master
    root        161  0.0  0.4 205040  9640 pts/2    S+   10:06   0:00 php process 1: test\work[no param]
    root        162  0.0  0.4 205040  9640 pts/2    S+   10:06   0:00 php process 1: test\work[no param]
    root        163  0.0  0.4 205040  9640 pts/2    S+   10:06   0:00 php process 1: test\work[no param]

```
