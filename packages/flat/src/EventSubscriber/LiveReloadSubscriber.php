<?php

declare(strict_types=1);

namespace Pushword\Flat\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class LiveReloadSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private bool $debug,
        private string $projectDir,
    ) {
    }

    /**
     * @return array<string, list<array{0: string, 1?: int}|int|string>|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [['onKernelResponse', -128]],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $this->debug || ! $event->isMainRequest()) {
            return;
        }

        $signalFile = $this->projectDir.'/public/_flat-reload.txt';
        if (! file_exists($signalFile)) {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        if ($response->isRedirection()) {
            return;
        }

        $content = $response->getContent();
        if (false === $content) {
            return;
        }

        $script = <<<'JS'
<script>
(function(){var l="";setInterval(function(){fetch("/_flat-reload.txt",{cache:"no-store"}).then(function(r){return r.text()}).then(function(t){if(l&&t!==l)location.reload();l=t}).catch(function(){})},1000)})();
</script>
JS;

        $content = str_replace('</body>', $script.'</body>', $content);
        $response->setContent($content);
    }
}
