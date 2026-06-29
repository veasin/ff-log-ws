<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, log, test};
use function ff\log\ws;

container(null);
container('^#log', null);

// 测试 1: ws 后 container('#log') 为可调用
ws('http://127.0.0.1:1/test', ['app' => 'test-app']);
test('ws后logger为可调用', is_callable(container('#log')), true);
container(null);
container('^#log', null);

// 测试 2: ws 叠加在现有 logger 上
$called = false;
$lastLog = [];
$original = function(string $level, $message, array $context) use (&$called, &$lastLog){
	$called = true;
	$lastLog = ['level' => $level, 'message' => $message, 'context' => $context];
};
container('#log', $original);
ws('http://127.0.0.1:1/test', ['app' => 'test-app']);
$logger = container('#log');
test('ws后logger保留原始调用', is_callable($logger), true);
$logger('info', 'test msg', ['id' => 1]);
test('原始logger被调用', $called, true);
test('原始logger接收消息', $lastLog['message'] ?? '', 'test msg');
test('原始logger接收level', $lastLog['level'] ?? '', 'info');
test('原始logger接收上下文', $lastLog['context'] ?? [], ['id' => 1]);
container(null);
container('^#log', null);

// 测试 3: 与 log() 函数协同工作
class WsTestLogger{
	public array $entry = [];
	public function log(string $level, string|object|array $message, array $context = []): void{
		$this->entry = ['level' => $level, 'message' => $message, 'context' => $context];
	}
}
$testLogger = new WsTestLogger();
container('#log', $testLogger);
ws('http://127.0.0.1:1/test', ['app' => 'test-app']);
log('ws log message', ['key' => 'value'], 'warning');
test('log()与ws协同-消息', $testLogger->entry['message'] ?? '', 'ws log message');
test('log()与ws协同-level', $testLogger->entry['level'] ?? '', 'warning');
test('log()与ws协同-上下文', $testLogger->entry['context'] ?? [], ['key' => 'value']);
container(null);
container('^#log', null);

// 测试 4: 多次调用 ws 形成链
$invoked = [];
container('#log', function(...$args) use (&$invoked){ $invoked[] = 'p0'; });
ws('http://127.0.0.1:1/a', ['app' => 'a']);
ws('http://127.0.0.1:1/b', ['app' => 'b']);
$logger = container('#log');
$logger('info', 'chain test', []);
test('多次ws链式调用-原始触发', count($invoked), 1);
test('多次ws链式调用-原始值', $invoked[0] ?? '', 'p0');
container(null);
container('^#log', null);

// 测试 5: ws 不阻断其他 logger 多次调用
$count = 0;
container('#log', function(...$args) use (&$count){ $count++; });
ws('http://127.0.0.1:1/test');
$logger = container('#log');
$logger('info', 'm1', []);
$logger('info', 'm2', []);
test('原始logger被多次调用', $count, 2);
container(null);
container('^#log', null);

test();
