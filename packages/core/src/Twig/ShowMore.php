<?php

namespace Pushword\Core\Twig;

use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;
use Twig\TemplateWrapper;

/**
 * The collapsible wrapper, rendered from `/component/show_more.html.twig`.
 *
 * Two authoring syntaxes reach here: `{{ startShowMore() }}` in the page body,
 * evaluated by the Twig filter the Markdown filter runs on every block, and the
 * legacy `<!--start-show-more-->` comment, which
 * {@see \Pushword\Core\Component\EntityFilter\Filter\ShowMore} rewrites into a
 * call to this service. Both share the numbering below, so a page mixing them
 * still gets distinct ids.
 */
class ShowMore implements ResetInterface
{
    /**
     * Ids of the blocks opened and not yet closed, innermost last.
     *
     * @var list<string>
     */
    private array $openIds = [];

    private int $counter = 0;

    /** The page the numbering belongs to; null until the first render. */
    private ?string $pageKey = null;

    public function __construct(
        public Twig $twig,
        public SiteRegistry $apps,
    ) {
    }

    /**
     * Worker-mode safety (kernel.reset): the counter and the page it numbers for
     * are request state. Kept across requests, the second page served by a worker
     * would number from where the first stopped — ids have to be identical from
     * one render of a page to the next, static builds skip rewrites on that.
     */
    public function reset(): void
    {
        $this->openIds = [];
        $this->counter = 0;
        $this->pageKey = null;
    }

    #[AsTwigFunction('startShowMore', needsEnvironment: false, isSafe: ['html'])]
    public function startShowMore(?string $id = null, string $showMoreExtraClass = ''): string
    {
        return $this->renderStart($id, $showMoreExtraClass, null);
    }

    #[AsTwigFunction('endShowMore', needsEnvironment: false, isSafe: ['html'])]
    public function endShowMore(?string $showMoreBackground = null, ?string $id = null): string
    {
        return $this->renderEnd($showMoreBackground, $id, null);
    }

    /**
     * @param ?Page $page The page being filtered, when the caller knows it — the
     *                    filter does, a Twig call in the body does not
     */
    public function renderStart(?string $id, string $showMoreExtraClass, ?Page $page): string
    {
        // Resolved first: it restarts the numbering when the page changed.
        $prefix = $this->pagePrefix($page);
        $id ??= 'sm-'.substr(md5($prefix.'__'.(++$this->counter)), 0, 6);
        $this->openIds[] = $id;

        return $this->getTemplate($page)->renderBlock('before', [
            'id' => $id,
            'showMoreExtraClass' => $showMoreExtraClass,
        ]);
    }

    public function renderEnd(?string $showMoreBackground, ?string $id, ?Page $page): string
    {
        // Pop even when the caller names the id: the stack pairs by nesting, so a
        // named inner block must not leave the outer one's id behind it.
        $openId = array_pop($this->openIds);

        return $this->getTemplate($page)->renderBlock('after', [
            'id' => $id ?? $openId,
            'showMoreBackground' => $showMoreBackground,
        ]);
    }

    /**
     * Ids derive from the page slug, not from `uniqid()`: re-rendering an unchanged
     * page has to produce identical bytes. Moving on to another page restarts the
     * numbering, so one process rendering many pages (static build, page scan)
     * gives each of them the ids it would get on its own.
     */
    private function pagePrefix(?Page $page): string
    {
        $key = ($page ?? $this->apps->getCurrentPage())?->slug;

        // Nothing names the page — a body filtered outside a page render. Carry on
        // numbering instead of restarting: the filter and the Twig calls of one
        // body share this counter, and a restart between them would collide.
        if (null === $key) {
            return $this->pageKey ?? '';
        }

        if ($key !== $this->pageKey) {
            $this->pageKey = $key;
            $this->counter = 0;
            $this->openIds = [];
        }

        return $key;
    }

    private function getTemplate(?Page $page): TemplateWrapper
    {
        $app = $this->apps->get($page->host ?? null);
        $templatePath = $app->getView('/component/show_more.html.twig', '@Pushword');

        return $this->twig->load($templatePath);
    }
}
