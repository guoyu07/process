<?php

$str =[];
$date = '20180401';
for($i=0;$i<=44;$i++)
{
    $str[] = date('Ymd',strtotime('2018-04-01')+($i*86400));
}
echo join(' ',$str);
die();


//namespace test;
use phpth\process\process;
//开启三个进程
include 'src/process.php';
include 'src/fileShare.php';

/*$server = new \swoole_websocket_server("0.0.0.0", 9500);

$server->on('open', function (swoole_websocket_server $server, $request) {
    print_r($server);
    echo "server: handshake success with fd{$request->fd}\n";
});

$server->on('message', function (swoole_websocket_server $server, $frame) {
    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
    $server->push($frame->fd, "this is server");
});

$server->on('close', function ($ser, $fd) {
    echo "client {$fd} closed\n";
});

$server->start();
die();*/
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
#$process->run(0); // 三个进程，每个进程都无限循环的运行work函数