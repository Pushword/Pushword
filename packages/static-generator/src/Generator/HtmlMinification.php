<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Generator;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pushword\Core\Service\NativeWorker;
use RuntimeException;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/** PHP is the default; the optional worker only receives already rendered HTML. */
final class HtmlMinification implements ResetInterface
{
    private readonly NativeWorker $worker;

    private bool $failed = false;

    public function __construct(
        private readonly ?string $binary = null,
        float $timeout = 5.0,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->worker = new NativeWorker($binary, $timeout);
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
        $this->worker->reset();
        $this->failed = false;
    }

    /**
     * @param list<string> $documents
     *
     * @return list<string>
     */
    private function compressNative(array $documents): array
    {
        $validated = [];
        foreach ($this->worker->request('minify_html', $documents) as $html) {
            if (! \is_string($html)) {
                throw new RuntimeException('Invalid native HTML document');
            }

            $validated[] = $html;
        }

        return $validated;
    }
}
