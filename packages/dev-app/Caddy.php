<?php

/**
 * FrankenPHP with Caddy Manager Script
 * Usage: php Caddy.php [start|stop|restart|status].
 */
class Caddy
{
    private const string PIDFILE = '/tmp/caddy.pid';

    private const string LOGFILE = '/tmp/caddy-output.log';

    /**
     * @param array<int, mixed> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'start';

        return match ($command) {
            'start' => $this->start(),
            'stop' => $this->stop(),
            'restart' => $this->restart(),
            'status' => $this->status(),
            default => $this->showUsage(),
        };
    }

    private function start(): int
    {
        if (file_exists(self::PIDFILE)) {
            $pid = (int) file_get_contents(self::PIDFILE);
            if ($this->isProcessRunning($pid)) {
                echo "⚠️  Caddy is already running (PID: {$pid})\n";
                $this->showUrl();

                return 0;
            }
        }

        echo "🚀 Starting Pushword with Caddy/FrankenPHP\n";
        echo "\n";

        // Start FrankenPHP with Caddy in background using proc_open
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['file', self::LOGFILE, 'w'],  // stdout
            2 => ['file', self::LOGFILE, 'a'],  // stderr
        ];

        $process = proc_open(
            'frankenphp run --config Caddyfile',
            $descriptorSpec,
            $pipes,
            getcwd()
        );

        if (is_resource($process)) {
            $status = proc_get_status($process);
            $pid = $status['pid'];
            fclose($pipes[0]);

            if ($pid > 0) {
                file_put_contents(self::PIDFILE, (string) $pid);
            }
        }

        // Wait a moment for Caddy to start and detect the port
        sleep(3);

        // Extract the URL from the output
        $port = $this->extractPort();

        if ($port) {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "✅ Pushword is running!\n";
            echo sprintf('🌐 Open: http://127.0.0.1:%s%s', $port, \PHP_EOL);
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "\n";
        } else {
            echo "⚠️  Could not determine port, check the log file:\n";
            echo '   tail -f '.self::LOGFILE."\n";
            echo "\n";
        }

        return 0;
    }

    private function stop(): int
    {
        if (file_exists(self::PIDFILE)) {
            $pid = (int) file_get_contents(self::PIDFILE);
            if ($this->isProcessRunning($pid)) {
                echo "🛑 Stopping Caddy (PID: {$pid})...\n";
                posix_kill($pid, \SIGTERM);
                @unlink(self::PIDFILE);
                echo "✅ Caddy stopped\n";

                return 0;
            }

            echo "⚠️  Caddy is not running\n";
            @unlink(self::PIDFILE);

            return 0;
        }

        echo "⚠️  Caddy is not running (no PID file found)\n";

        return 0;
    }

    private function restart(): int
    {
        echo "🔄 Restarting Caddy...\n";
        $this->stop();
        sleep(1);

        return $this->start();
    }

    private function status(): int
    {
        if (file_exists(self::PIDFILE)) {
            $pid = (int) file_get_contents(self::PIDFILE);
            if ($this->isProcessRunning($pid)) {
                echo "✅ Caddy is running (PID: {$pid})\n";
                $this->showUrl();

                return 0;
            }

            echo "❌ Caddy is not running (stale PID file)\n";
            @unlink(self::PIDFILE);

            return 0;
        }

        echo "❌ Caddy is not running\n";

        return 0;
    }

    private function showUrl(): void
    {
        if (file_exists(self::LOGFILE)) {
            $port = $this->extractPort();
            if ($port) {
                echo sprintf('🌐 Pushword URL: http://127.0.0.1:%s%s', $port, \PHP_EOL);
            }
        }
    }

    private function extractPort(): ?int
    {
        if (! file_exists(self::LOGFILE)) {
            return null;
        }

        $content = file_get_contents(self::LOGFILE);
        if (! $content) {
            return null;
        }

        // Extract port from JSON log - looking for "actual_address":"[::]:PORT"
        if (preg_match('/"actual_address":"(?:\[::\]|127\.0\.0\.1):(\d+)"/', $content, $matches)) {
            return (int) $matches[1];
        }

        // Fallback to HTTP URL pattern
        if (preg_match('/https?:\/\/[^:]+:(\d+)/', $content, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // Try to send signal 0 (doesn't kill, just checks if process exists)
        return posix_kill($pid, 0);
    }

    private function showUsage(): int
    {
        $scriptName = basename($GLOBALS['argv'][0] ?? 'Caddy.php');
        echo "Usage: {$scriptName} {start|stop|restart|status}\n";
        echo "\n";
        echo "Commands:\n";
        echo "  start   - Start FrankenPHP with Caddy (default)\n";
        echo "  stop    - Stop the running Caddy server\n";
        echo "  restart - Restart Caddy\n";
        echo "  status  - Show Caddy status\n";

        return 1;
    }
}

// Run the manager
$manager = new Caddy();
exit($manager->run($argv));
