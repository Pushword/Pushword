<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Media;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Who created, renamed and removed which media file, on the `pushword_media` Monolog
 * channel. A file that went missing used to leave nothing to trace it back to the request
 * that took it (production, 2026-10-15).
 */
#[AutoconfigureTag('monolog.logger', ['channel' => 'pushword_media'])]
final readonly class MediaAuditLog
{
    public function __construct(
        private LoggerInterface $logger,
        private Security $security,
    ) {
    }

    public function created(Media $media): void
    {
        $this->log('Media created', $media, ['fileName' => $media->getFileName()]);
    }

    public function renamed(Media $media, string $from, string $to): void
    {
        $this->log('Media renamed', $media, ['from' => $from, 'to' => $to]);
    }

    public function removed(Media $media): void
    {
        $this->log('Media removed', $media, ['fileName' => $media->getFileName()]);
    }

    /** @param array<string, string> $context */
    private function log(string $message, Media $media, array $context): void
    {
        $this->logger->info($message, [
            'media' => $media->id,
            ...$context,
            'hash' => bin2hex($this->hash($media)),
            'user' => $this->security->getUser()?->getUserIdentifier(),
        ]);
    }

    private function hash(Media $media): string
    {
        $hash = $media->getHash();
        if (\is_resource($hash)) {
            rewind($hash);
            $hash = stream_get_contents($hash);
        }

        return \is_string($hash) ? $hash : '';
    }
}
