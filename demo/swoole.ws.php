<?php
/**
 * Swoole WebSocket 服务器
 * 接收 HTTP POST /log 写入的日志，广播到所有 WebSocket 客户端
 * 
 * 启动: php demo/swoole.ws.php
 * 测试: curl -X POST http://127.0.0.1:9501/log -d '{"app":"test","logs":[]}'
 */
use Swoole\WebSocket\Server;

$port = match ($_SERVER['ENV_MODE'] ?? 'development') {
	'production' => 10011,
	'test' => 10012,
	default => 9501,
};

$ws = new Server('0.0.0.0', $port);
$ws->on('request', function(\Swoole\Http\Request $req, \Swoole\Http\Response $res) use ($ws){
	if(strtolower($req->server['request_method']) === 'post' && $req->server['request_uri'] === '/log'){
		foreach($ws->connections as $fd){
			if($ws->isEstablished($fd)) $ws->push($fd, $req->getContent());
		}
	}
});
echo "WS server started on port {$port}\n";
$ws->start();
