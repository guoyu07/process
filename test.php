<?php
namespace test;

use phpth\process\process;

include 'src/process.php';
include 'src/fileShare.php';

error_reporting(E_ALL);

set_error_handler(function($errno, $errstr, $errfile, $errline)
{
    echo "测试不通过！！！",PHP_EOL;
    switch ($errno) {
        case E_USER_ERROR:
            echo "ERROR: [$errno] $errstr\n";
            break;
        case E_USER_WARNING:
            echo "My WARNING [$errno] $errstr\n";
            break;

        case E_USER_NOTICE:
            echo "NOTICE [$errno] $errstr\n";
            break;

        default:
            echo "Unknown error type: [$errno] $errstr\n";
            break;
    }
    echo "error on line $errline in file $errfile\n";
    echo " PHP " . PHP_VERSION . " (" . PHP_OS . ")\n";
    echo "Aborting...\n";
    exit(1);
});

function work($work='default_param',$work2='default_param_2')
{
    echo "【function】  param1： {$work}，param2：{$work2} \n";
    sleep(1);
}

class work
{
    public function work($work='default_param',$work2='default_param_2')
    {
        echo "【method 】 param1： {$work}，param2：{$work2} \n";
        sleep(1);
    }

    public static function static_work($work='default_param',$work2='default_param_2')
    {
        echo "【static method】 param1： {$work}，param2：{$work2} \n";
        sleep(1);
    }
}

$work = function($work='default_param',$work2='default_param_2'){
    echo "【closure method 】param1： {$work}，param2：{$work2} \n";
    sleep(1);
};

$class = new work();

//进程运行测试1：
$callbacks = [
    $work,
    [$class,'work'],
    [__NAMESPACE__.'\work','static_work'],
    __NAMESPACE__.'\work::static_work',
    __NAMESPACE__.'\work',
    [$work,['process','success']],
    [[$class,'work'],['process','success']],
    [[__NAMESPACE__.'\work','static_work'],['process','success']],
    [__NAMESPACE__.'\work::static_work',['process','success']],
    [__NAMESPACE__.'\work',['process','success']],
];

try{

    echo '正确测试 test1 ',PHP_EOL;
    sleep(1);
    $process = new process(10, $callbacks ,'./test.log');
    $process->run(1);

    // 测试2
    echo '正确测试 test2 ',PHP_EOL;
    sleep(1);
    $process = new process (3, function(){echo time(),PHP_EOL;});
    $process->run(2);

    // 测试3
    echo '正确测试 test3 ',PHP_EOL;
    sleep(1);
    $process = new process (3, [ $work ] );
    $process->run(2);

    // 测试4
    echo '正确测试 test4 ',PHP_EOL;
    sleep(1);
    $process = new process (3, [
        [ $work,[] ]
    ] );
    $process->run(2);

    // 测试5
    echo '正确测试 test5 ',PHP_EOL;
    sleep(1);
    $process = new process (3, [
        [   $work,[1,2]  ]
    ] );
    $process->run(2);

    // 测试6
    echo '正确测试 test6 ',PHP_EOL;
    $process = new process (3, [
       [ [$class,'work' ]  ,[] ],
        ] );
    $process->run(2);
}
catch(\Exception $e)
{
    echo '测试不通过！！！，',$e->getMessage(),PHP_EOL;
    echo '错误码：',$e->getCode(),PHP_EOL;
    echo '错误文件：',$e->getFile(),PHP_EOL;
    echo '错误行数：',$e->getLine(),PHP_EOL;
    echo '堆栈信息：',$e->getTraceAsString(),PHP_EOL;
    echo " PHP " . PHP_VERSION . " (" . PHP_OS . ")\n";
    echo "Aborting...\n";
}
