<?php
namespace Phphue\Test\Support;

class LocalHttpServer
{
    private $process;
    private array $pipes = [];
    public readonly string $url;

    public function __construct()
    {
        $this->process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/http-server.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $this->pipes
        );
        if (! is_resource($this->process)) {
            throw new \RuntimeException('Could not start HTTP test server');
        }
        stream_set_timeout($this->pipes[1], 5);
        $address = trim((string) fgets($this->pipes[1]));
        if (! preg_match('/^127\.0\.0\.1:\d+$/', $address)) {
            throw new \RuntimeException('HTTP test server failed to start');
        }
        $this->url = 'http://' . $address;
    }

    public function __destruct()
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($this->process);
        }
    }
}
