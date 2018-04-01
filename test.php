<?php

namespace test;
use phpth\process\process;
//开启三个进程
include 'src/process.php';


function work($param=false){
    echo 'do work~~~'.microtime(true).PHP_EOL;
    sleep(1);
}


/*$process = new process(3,
    [[__NAMESPACE__ . '\work', ['param1']],
        [__NAMESPACE__ . '\work', ['param1']],
        [__NAMESPACE__ . '\work', ['param1']],
    ]);*/
//或者不传入参数
#$process = new process(3,[__NAMESPACE__.'\work',__NAMESPACE__.'\work',__NAMESPACE__.'\work']);
//或者
$process = new process(3,__NAMESPACE__.'\work');

//$process->run(3); // 三个进程，每个进程运行三次work函数
$process->run(0); // 三个进程，每个进程都无限循环的运行work函数

//$process->run(3); // 三个进程，每个进程运行三次work函数
$process->run(0); // 三个进程，每个进程都无限循环的运行work函数