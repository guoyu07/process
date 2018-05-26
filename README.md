# 简单好用的php进程控制类
基于pcntl的简单进程控制
>安装
```bash
composer require phpth/process
```
 process类的第三个参数可以控制进程执行过程的信息输出模式，默认为false，代表直接输出到屏幕<br />
 如果传入文件路径则写入文件内，目录和文件不存在则会新建<br />
 如果传入是目录，则会以当前的主进程进程标题为名文件名写入

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