<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Form;

use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Form\NewsletterForm;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A site form with three or more steps drives its own step transition instead of
 * returning through the parent's, so it needs the workflow seams to be reachable
 * from a subclass. When they were not, incrementStep() moved to step N+1, nothing
 * was stored under the token, and the next POST died in loadWorkflow().
 */
final class SubclassDrivenWorkflowTest extends KernelTestCase
{
    private const string TOKEN = 'b7e1000000000000000000000000000000000000000000000000000000000000';

    public function testASubclassAdvancingOnItsOwnTermsLeavesTheWorkflowReadable(): void
    {
        self::bootKernel();

        $messageId = $this->persistMessage();

        $first = $this->buildForm(1);
        $first->advanceAfterPersisting($messageId);

        // The next POST is a fresh request and a fresh instance: it only has the
        // token, and must find the state the subclass stored.
        self::assertSame($messageId, $this->buildForm(2)->loadStoredWorkflow());
    }

    public function testASubclassEndingTheConversationBurnsTheToken(): void
    {
        self::bootKernel();

        $messageId = $this->persistMessage();

        $this->buildForm(1)->advanceAfterPersisting($messageId);
        $this->buildForm(2)->finish();

        $this->expectException(NotFoundHttpException::class);
        $this->buildForm(2)->loadStoredWorkflow();
    }

    private function persistMessage(): int
    {
        $em = self::getContainer()->get('doctrine')->getManager();

        $message = new Message();
        $message->host = 'localhost.dev';
        $message->referring = 'test';
        $message->setContent('Subclass driven workflow');
        $message->authorEmail = 'subclass-workflow-'.uniqid().'@example.tld';

        $em->persist($message);
        $em->flush();

        return (int) $message->id;
    }

    private function buildForm(int $step): SubclassDrivenForm
    {
        $container = self::getContainer();

        $request = Request::create('/conversation/newsletter/test', parameters: [
            'host' => 'localhost.dev',
            'type' => 'newsletter',
            'referring' => 'test',
            'step' => $step,
            'token' => self::TOKEN,
        ]);

        return new SubclassDrivenForm(
            $request,
            $container->get('doctrine'),
            $container->get('security.token_storage'),
            $container->get('form.factory'),
            $container->get('twig'),
            $container->get('router'),
            $container->get('translator'),
            $container->get(SiteRegistry::class),
            $container->get(MessageRepository::class),
            $container->get('cache.app'),
        );
    }
}

/**
 * Stands in for a site's three-step form: it owns the transition, so it calls the
 * protected seams rather than returning through defaultStepValidator().
 */
final class SubclassDrivenForm extends NewsletterForm
{
    public function advanceAfterPersisting(int $messageId): void
    {
        $this->messageId = $messageId;
        $this->advanceStep();
    }

    public function finish(): void
    {
        // The real flow has always loaded the step before it can finish it, which is
        // what resolves the token deleteWorkflow() burns.
        $this->initForm();
        $this->deleteWorkflow();
    }

    public function loadStoredWorkflow(): int
    {
        $this->initForm();

        return $this->getId();
    }
}
