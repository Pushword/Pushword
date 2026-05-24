<?php

namespace Pushword\Core\Tests\EventListener;

use DateTime;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\EventListener\PageListener;
use Pushword\Core\EventListener\PageWorkflowSubscriber;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Service\TailwindGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Marking;

final class PageWorkflowTest extends TestCase
{
    public function testApprovedTransitionRecordsReviewer(): void
    {
        $user = new User();
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $page = new Page();
        $event = new EnteredEvent($page, new Marking(['approved' => 1]));

        new PageWorkflowSubscriber($security)->onApproved($event);

        self::assertSame($user, $page->reviewedBy);
        self::assertInstanceOf(DateTime::class, $page->reviewedAt);
    }

    public function testPublishingBlockedWhenApprovalRequiredAndNotApproved(): void
    {
        $page = $this->newPublishedDraft();

        $this->buildListener(requireApproval: true)->prePersist($page);

        self::assertNull($page->publishedAt, 'A non-approved page must not stay published when approval is required');
    }

    public function testPublishingAllowedWhenApproved(): void
    {
        $page = $this->newPublishedDraft();
        $page->workflowState = 'approved';

        $this->buildListener(requireApproval: true)->prePersist($page);

        self::assertNotNull($page->publishedAt, 'An approved page keeps its publishedAt');
    }

    public function testPublishingUntouchedByDefault(): void
    {
        $page = $this->newPublishedDraft();

        $this->buildListener(requireApproval: false)->prePersist($page);

        self::assertNotNull($page->publishedAt, 'With the flag off, publishing behaves exactly as before (BC)');
    }

    private function newPublishedDraft(): Page
    {
        $page = new Page();
        $page->setSlug('workflow-gate');
        $page->publishedAt = new DateTime();

        return $page; // workflowState defaults to 'draft'
    }

    private function buildListener(bool $requireApproval): PageListener
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $ogGenerator = $this->createMock(PageOpenGraphImageGenerator::class);
        $ogGenerator->method('setPage')->willReturnSelf();

        return new PageListener(
            $security,
            $ogGenerator,
            self::createStub(TailwindGenerator::class),
            $requireApproval,
        );
    }
}
