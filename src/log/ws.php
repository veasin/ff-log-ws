<?php
declare(strict_types=1);
namespace ff\log;

use function ff\container;

/**
 * WebSocket 日志传输。配置 log() 函数将日志通过 HTTP POST 实时发送到日志服务端，
 * 每次调用立即发送（不缓冲、不等待响应），再由服务端广播到 WebSocket 客户端。
 * 支持在现有 Logger 基础上叠加。
 * ```
 * ws('http://127.0.0.1:9501/log');                        // 自动生成唯一 app
 * ws('http://127.0.0.1:9501/log', ['app' => 'my-service']);// 指定应用标识
 * ws('http://127.0.0.1:9501/log', ['timeout' => 0.1]);     // 自定义超时
 * ```
 * @param string $uri    日志服务端 HTTP 接口地址，接收 POST JSON
 * @param array  $config 配置: app 应用标识, timeout 超时秒数(默认0.5)
 * @return void
 */
function ws(string $uri, array $config = []): void{
	$config += ['timeout' => 0.5];
	$hasApp = isset($config['app']);

	$previous = container('#log');
	if($previous && is_object($previous) && method_exists($previous, 'log')) $previous = $previous->log(...);

	container('#log', function(...$args) use ($previous, $uri, $config, $hasApp){
		$payload = json_encode([
			'app' => $hasApp ? $config['app'] : bin2hex(random_bytes(4)),
			'logs' => [[
				'level' => $args[0],
				'message' => $args[1],
				'context' => $args[2],
				'timestamp' => time(),
			]],
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$ctx = stream_context_create(['http' => [
			'method' => 'POST',
			'header' => ["Content-Type: application/json;charset=UTF-8"],
			'content' => $payload,
			'timeout' => $config['timeout'],
		]]);
		$stream = @fopen($uri, 'r', false, $ctx);
		if($stream) @fclose($stream);
		if($previous && is_callable($previous)) $previous(...$args);
	});
}
