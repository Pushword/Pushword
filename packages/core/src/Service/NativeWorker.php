<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use RuntimeException;
use stdClass;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Service\ResetInterface;

/** Bounded, versioned JSON transport shared by optional native operations. */
final class NativeWorker implements ResetInterface
{
    private const int MAX_FRAME_BYTES = 16 * 1024 * 1024;

    private ?Process $process = null;

    private ?InputStream $input = null;

    private int $requestId = 0;

    public function __construct(private readonly ?string $binary = null, private readonly float $timeout = 5.0)
    {
    }

    /**
     * @param list<mixed> $documents
     *
     * @return list<mixed>
     */
    public function request(string $operation, array $documents): array
    {
        $id = ++$this->requestId;
        $frame = json_encode(['version' => 1, 'id' => $id, 'operation' => $operation, 'documents' => $documents], \JSON_THROW_ON_ERROR)."\n";
        if (\strlen($frame) > self::MAX_FRAME_BYTES) {
            throw new RuntimeException('Native request exceeds 16 MiB');
        }

        if (null === $this->process) {
            if (null === $this->binary || ! \function_exists('proc_open') || ! is_executable($this->binary)) {
                throw new RuntimeException('Native executable unavailable');
            }

            $this->input = new InputStream();
            $this->process = new Process([$this->binary], input: $this->input, timeout: $this->timeout);
            $this->process->start();
        }

        $this->process->setTimeout(microtime(true) - $this->process->getStartTime() + $this->timeout);
        $this->input?->write($frame);
        $response = '';
        $received = 0;
        foreach ($this->process->getIterator() as $type => $chunk) {
            $received += \strlen($chunk);
            if ($received > self::MAX_FRAME_BYTES) {
                throw new RuntimeException('Native response exceeds 16 MiB');
            }

            if (Process::OUT !== $type) {
                continue;
            }

            $response .= $chunk;
            if (str_contains($response, "\n")) {
                $decoded = json_decode($response, flags: \JSON_THROW_ON_ERROR);
                if (! $decoded instanceof stdClass || 1 !== ($decoded->version ?? null) || $id !== ($decoded->id ?? null)
                    || ! isset($decoded->documents) || ! \is_array($decoded->documents)
                    || ! array_is_list($decoded->documents) || \count($decoded->documents) !== \count($documents)) {
                    throw new RuntimeException('Invalid native response');
                }

                return $decoded->documents;
            }
        }

        throw new RuntimeException('Native worker exited before responding');
    }

    public function reset(): void
    {
        $this->input?->close();
        $this->process?->stop(0);
        $this->process = null;
        $this->input = null;
    }

    public function __destruct()
    {
        $this->reset();
    }
}
