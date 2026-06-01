<?php

namespace Pushword\PageWorkflow\Tests\Workflow;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\PageWorkflow\Entity\PageEditorialState;
use Pushword\PageWorkflow\Workflow\PageEditorialWorkflowSubscriber;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Marking;

final class PageEditorialWorkflowSubscriberTest extends TestCase
{
    public function testApprovedTransitionRecordsReviewer(): void
    {
        $user = new User();
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $state = new PageEditorialState(new Page());
        $event = new EnteredEvent($state, new Marking(['approved' => 1]));

        new PageEditorialWorkflowSubscriber($security, $em)->onApproved($event);

        self::assertSame($user, $state->reviewedBy);
        self::assertInstanceOf(DateTime::class, $state->reviewedAt);
    }
}
