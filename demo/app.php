<?php
/**
 * ff-log-ws 使用示例
 * 
 * 配合 demo/swoole.ws.php 使用
 * 启动: php demo/swoole.ws.php &
 * 运行: php demo/app.php
 */
require_once __DIR__ . '/../vendor/autoload.php';

use function ff\{container, log};
use function ff\log\ws;

// 配置 WS 日志传输
ws('http://127.0.0.1:9501/log', ['app' => 'demo-app']);

// 记录日志（退出时自动批量发送到 WS 服务端）
log('应用启动', ['version' => '1.0.0'], 'info');
log('用户 {user} 登录', ['user' => 'admin'], 'info');
log('数据库查询', ['sql' => 'SELECT * FROM users', 'ms' => 3.2], 'debug');
log('发生错误: {msg}', ['msg' => '连接超时'], 'error');

echo "已发送 " . date('H:i:s') . "\n";
echo "打开 demo/1.html 连接 ws://thiz.cn:10010 查看日志\n";