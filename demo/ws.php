<?php
declare(strict_types=1);

if(!extension_loaded('sockets')){
	fwrite(STDERR, "ext-sockets required (built-in PHP extension)\n");
	exit(1);
}

$port = max(1, (int)($argv[1] ?? 9501));

$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
@socket_bind($server, '0.0.0.0', $port) or exit("bind {$port} failed\n");
socket_listen($server);
socket_set_nonblock($server);

$conns = [];

echo "[WS] http://127.0.0.1:{$port}/log\n";

while(true){
	$read = [$server];
	foreach($conns as $c) $read[] = $c['s'];
	$write = $except = null;

	if(@socket_select($read, $write, $except, null) < 1) continue;

	if(in_array($server, $read, true)){
		$sock = @socket_accept($server);
		if($sock !== false){
			socket_set_nonblock($sock);
			$conns[spl_object_id($sock)] = ['s' => $sock, 'ws' => false];
		}
	}

	foreach($read as $sock){
		if($sock === $server) continue;
		$id = spl_object_id($sock);
		if(!isset($conns[$id])) continue;

		$data = @socket_read($sock, 65536, PHP_BINARY_READ);
		if($data === false || $data === ''){
			@socket_close($sock);
			unset($conns[$id]);
			continue;
		}

		$conns[$id]['ws'] ? on_frame($conns, $id, $data) : on_new($conns, $id, $data);
	}
}

function on_new(array &$conns, int $id, string $data): void{
	if(!str_contains($data, "\r\n\r\n")){
		@socket_close($conns[$id]['s']);
		unset($conns[$id]);
		return;
	}

	// WebSocket handshake
	if(preg_match('/Sec-WebSocket-Key:\s*(\S+)/i', $data, $m)){
		$key = trim($m[1]);
		$accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
		@socket_write($conns[$id]['s'], "HTTP/1.1 101 Switching Protocols\r\n"
			. "Upgrade: websocket\r\nConnection: Upgrade\r\n"
			. "Sec-WebSocket-Accept: {$accept}\r\n\r\n");
		$conns[$id]['ws'] = true;
		return;
	}

	// HTTP POST /log → broadcast
	if(preg_match('/^POST\s+\/log\s+HTTP\//i', $data)){
		$len = 0;
		if(preg_match('/Content-Length:\s*(\d+)/i', $data, $m2)) $len = (int)$m2[1];
		if($len > 0){
			$pos = strpos($data, "\r\n\r\n");
			$body = substr($data, $pos + 4, $len);
			$frame = ws_encode($body);
			foreach($conns as $c)
				if($c['ws']) @socket_write($c['s'], $frame, strlen($frame));
		}
	}

	@socket_write($conns[$id]['s'], "HTTP/1.1 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nOK");
	@socket_close($conns[$id]['s']);
	unset($conns[$id]);
}

function on_frame(array &$conns, int $id, string $data): void{
	if((ord($data[0]) & 0x0F) === 0x8){ // Close
		@socket_write($conns[$id]['s'], "\x88\x00");
		@socket_close($conns[$id]['s']);
		unset($conns[$id]);
	}
}

function ws_encode(string $payload): string{
	$n = strlen($payload);
	$f = "\x81";
	if($n < 126) $f .= chr($n);
	elseif($n < 65536) $f .= chr(126) . pack('n', $n);
	else $f .= chr(127) . pack('J', $n);
	return $f . $payload;
}
