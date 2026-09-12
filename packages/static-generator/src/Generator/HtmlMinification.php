<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Generator;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use stdClass;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/** PHP is the default; the optional worker only receives already rendered HTML. */
final class HtmlMinification implements ResetInterface
{
    private const int MAX_FRAME_BYTES = 16 * 1024 * 1024;

    private ?Process $process = null;

    private ?InputStream $input = null;

    private int $requestId = 0;

    private bool $failed = false;

    public function __construct(
        private readonly ?string $binary = null,
        private readonly float $timeout = 5.0,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function compress(string $html): string
    {
        return $this->compressMany([$html])[0];
    }

    /**
     * @param list<string> $documents
     *
     * @return list<string>
     */
    public function compressMany(array $documents): array
    {
        if ([] === $documents) {
            return [];
        }

        if (null !== $this->binary && '' !== $this->binary && ! $this->failed) {
            try {
                return $this->compressNative($documents);
            } catch (Throwable $error) {
                $this->reset();
                // Avoid repeating process startup, timeouts and warnings for every page.
                $this->failed = true;
                $this->logger->warning('Native HTML minification failed; using PHP until the service is reset.', ['exception' => $error]);
            }
        }

        return array_map(HtmlMinifier::compress(...), $documents);
    }

    public function reset(): void
    {
        $this->input?->close();
        $this->process?->stop(0);
        $this->process = null;
        $this->input = null;
        $this->failed = false;
    }

    /**
     * @param list<string> $documents
     *
     * @return list<string>
     */
    private function compressNative(array $documents): array
    {
        $id = ++$this->requestId;
        $frame = json_encode(['version' => 1, 'id' => $id, 'operation' => 'minify_html', 'documents' => $documents], \JSON_THROW_ON_ERROR)."\n";
        if (\strlen($frame) > self::MAX_FRAME_BYTES) {
            throw new RuntimeException('Native HTML request exceeds 16 MiB');
        }

        if (null === $this->process) {
            if (null === $this->binary || ! \function_exists('proc_open') || ! is_executable($this->binary)) {
                throw new RuntimeException('Native HTML executable unavailable');
            }

            $this->input = new InputStream();
            $this->process = new Process([$this->binary], input: $this->input, timeout: $this->timeout);
            $this->process->start();
        }

        // Symfony's timeout is relative to process startup; renew it for each request.
        $this->process->setTimeout(microtime(true) - $this->process->getStartTime() + $this->timeout);
        $this->input?->write($frame);
        $response = '';
        $received = 0;
        foreach ($this->process->getIterator() as $type => $chunk) {
            $received += \strlen($chunk);
            if ($received > self::MAX_FRAME_BYTES) {
                throw new RuntimeException('Native HTML response exceeds 16 MiB');
            }

            if (Process::OUT !== $type) {
                continue;
            }

            $response .= $chunk;
            if (str_contains($response, "\n")) {
                return $this->validateResponse($response, $id, \count($documents));
            }
        }

        throw new RuntimeException('Native HTML worker exited before responding');
    }

    /** @return list<string> */
    private function validateResponse(string $output, int $id, int $count): array
    {
        $response = json_decode($output, flags: \JSON_THROW_ON_ERROR);
        if (! $response instanceof stdClass || 1 !== ($response->version ?? null) || $id !== ($response->id ?? null)
            || ! isset($response->documents) || ! \is_array($response->documents)
            || \count($response->documents) !== $count) {
            throw new RuntimeException('Invalid native HTML response');
        }

        $validated = [];
        foreach ($response->documents as $html) {
            if (! \is_string($html)) {
                throw new RuntimeException('Invalid native HTML document');
            }

            $validated[] = $html;
        }

        return $validated;
    }
}
