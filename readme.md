# ff-log-ws

WebSocket 日志传输扩展。将 `log()` 的日志通过 HTTP POST 实时发送到日志服务端，再由服务端广播到 WebSocket 客户端。每次调用立即发送，不缓冲不等待，适合 FrankenPHP worker 等长进程场景。

## 安装

```bash
composer require veasin/ff-log-ws
```

## 函数参考

---

#### ws - WebSocket 日志传输

配置 `log()` 函数将每次日志调用实时 HTTP POST 到指定端点。支持在现有 PSR-3 Logger 基础上叠加。

```php
ws('http://127.0.0.1:9501/log');                    // 发送日志
ws('http://127.0.0.1:9501/log', ['timeout' => 0.1]); // 自定义超时
```

**$config**：
- **`timeout`**: `float` 默认 `0.5` HTTP 请求超时秒数

**容器配置**：
- **`#log`**: `callable|object` - 可选，已有的 PSR-3 Logger 或闭包。`ws()` 会在其基础上叠加 WS 传输

多次调用 `ws()` 会形成调用链，每次日志调用依次经过各层发送后到达原始 Logger。

---

#### 发送数据格式

```json
{
    "app": "default",
    "logs": [
        {
            "level": "info",
            "message": "用户 {user} 登录",
            "context": {"user": "admin"}
        }
    ]
}
```

## Demo

### 启动服务端

```bash
php demo/ws.php              # PHP（推荐）
php demo/ws.swoole.php       # Swoole
```

### 测试流程

**终端 1** — 启动服务端，**浏览器** — 打开 `demo/demo.html`（F12 → Console），**终端 2**：

```bash
php demo/log.php
```

在浏览器 Console 中实时观察日志输出。

## 测试

```bash
php test/log/ws.php
```
