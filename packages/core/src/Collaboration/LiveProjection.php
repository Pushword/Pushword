<?php

declare(strict_types=1);

namespace Pushword\Core\Collaboration;

use Pushword\Core\Entity\Page;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Twig\Attribute\AsTwigFunction;

/** @phpstan-type MarkdownPatch array{content: string, replacements: list<array{find: string, replace: string}>} */
final class LiveProjection
{
    private const string WORKER = __DIR__.'/live-editing.mjs';

    private string|false|null $node = null;

    #[AsTwigFunction('liveEditingAvailable')]
    public function isAvailable(): bool
    {
        $this->node ??= new ExecutableFinder()->find('node') ?? false;

        return false !== $this->node && is_file(self::WORKER) && \function_exists('proc_open');
    }

    /**
     * @param list<string> $legacy
     *
     * @return array{snapshot: string, content: string}|JsonResponse
     */
    public function resolve(bool $carousel, string $baseline, ?string $stored, ?string $incoming, ?string $content, array $legacy = []): array|JsonResponse
    {
        return $this->project([
            'carousel' => $carousel,
            'baseline' => $baseline,
            'stored' => $stored,
            'incoming' => $incoming,
            'content' => $content,
            'legacy' => $legacy,
        ]);
    }

    /**
     * @param MarkdownPatch $patch
     * @param list<string>  $legacy
     *
     * @return array{snapshot: string, content: string}|JsonResponse
     */
    public function patchMarkdown(string $baseline, ?string $stored, array $patch, array $legacy): array|JsonResponse
    {
        $patch['normalized'] = Page::normalizeMainContent($patch['content']);

        return $this->project([
            'baseline' => $baseline,
            'stored' => $stored,
            'patch' => $patch,
            'legacy' => $legacy,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{snapshot: string, content: string}|JsonResponse
     */
    private function project(array $input): array|JsonResponse
    {
        if (! $this->isAvailable() || ! \is_string($this->node)) {
            return $this->unavailable();
        }

        $process = new Process(
            [$this->node, '--max-old-space-size=128', self::WORKER],
            input: json_encode($input, \JSON_THROW_ON_ERROR),
            timeout: 2.0,
        );

        try {
            $process->run();
        } catch (ProcessStartFailedException|ProcessTimedOutException) {
            return $this->unavailable();
        }

        if (! $process->isSuccessful()) {
            return $this->unavailable();
        }

        $result = json_decode($process->getOutput(), true);
        if (! \is_array($result) || ! \is_string($result['snapshot'] ?? null) || ! \is_string($result['content'] ?? null)) {
            return new JsonResponse(['error' => 'invalid_snapshot'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return ['snapshot' => $result['snapshot'], 'content' => $result['content']];
    }

    public function unavailable(): JsonResponse
    {
        return new JsonResponse(['error' => 'collaboration_unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
