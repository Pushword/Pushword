<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Scanner;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pushword\Core\Service\NativeWorker;
use RuntimeException;
use stdClass;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/** Optional native extraction; null lets each scanner use its PHP implementation. */
final class RenderedPageFactsExtractor implements ResetInterface
{
    private readonly NativeWorker $worker;

    private bool $failed = false;

    public function __construct(
        private readonly ?string $nativePageFacts = null,
        float $nativePageFactsTimeout = 5.0,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->worker = new NativeWorker($nativePageFacts, $nativePageFactsTimeout);
    }

    public function extract(string $html): ?RenderedPageFacts
    {
        if (null === $this->nativePageFacts || '' === $this->nativePageFacts || '' === $html || $this->failed) {
            return null;
        }

        try {
            $result = $this->worker->request('scan_rendered_html', [$html])[0];
            if (! $result instanceof stdClass || ! isset($result->hrefs, $result->missing_alt, $result->anchors)) {
                throw new RuntimeException('Invalid native page facts');
            }

            return new RenderedPageFacts(
                $this->stringList($result->hrefs),
                $this->stringList($result->missing_alt),
                $this->stringList($result->anchors),
            );
        } catch (Throwable $throwable) {
            $this->worker->reset();
            $this->failed = true;
            $this->logger->warning('Native page facts failed; using PHP until the service is reset.', ['exception' => $throwable]);

            return null;
        }
    }

    public function reset(): void
    {
        $this->worker->reset();
        $this->failed = false;
    }

    /** @return list<string> */
    private function stringList(mixed $values): array
    {
        if (! \is_array($values) || ! array_is_list($values)) {
            throw new RuntimeException('Invalid native page fact list');
        }

        foreach ($values as $value) {
            if (! \is_string($value)) {
                throw new RuntimeException('Invalid native page fact value');
            }
        }

        return $values;
    }
}
