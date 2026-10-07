<?php

declare(strict_types=1);

namespace Pushword\Conversation\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Form\ConversationFormInterface;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Site\SiteRegistry;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

final class ConversationFormController extends AbstractController
{
    private ?ConversationFormInterface $form = null;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SiteRegistry $apps,
        private readonly Twig $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RouterInterface $router,
        private readonly ManagerRegistry $doctrine,
        private readonly MessageRepository $messageRepo,
        private readonly CacheInterface $cache,
        #[Autowire(service: 'limiter.anonymous_content')]
        private readonly RateLimiterFactory $anonymousContentLimiter,
    ) {
    }

    private function getFormManagerClassBeforeV1(string $type): ?string
    {
        $param = 'conversation_form_'.str_replace('-', '_', $type);

        if (! $this->apps->get()->has($param)) {
            return null;
        }

        return $this->apps->get()->getStr($param);
    }

    /**
     * @return class-string<ConversationFormInterface>
     */
    private function getFormManagerClass(string $type): string
    {
        $class = $this->apps->get()->getArray('conversation_form')[$type]
            ?? $this->getFormManagerClassBeforeV1($type)
            ?? 'App\\Form\\'.$type;

        if (! is_string($class)) {
            throw new Exception('`'.$type."` does'nt exist (not configured).");
        }

        if (! class_exists($class)
            || ! new ReflectionClass($class)->implementsInterface(ConversationFormInterface::class)) {
            throw new NotFoundHttpException('Conversation form `'.$type.'` does not exist.');
        }

        /** @var class-string<ConversationFormInterface> $class */

        return $class;
    }

    /**
     * Return current form manager depending on `type` (request).
     */
    private function getFormManager(string $type, Request $request): ConversationFormInterface
    {
        if (null !== $this->form) {
            return $this->form;
        }

        $class = $this->getFormManagerClass($type);

        return $this->form = new $class(
            $request,
            $this->doctrine,
            $this->tokenStorage,
            $this->formFactory,
            $this->twig,
            $this->router,
            $this->translator,
            $this->apps,
            $this->messageRepo,
            $this->cache,
        );
    }

    #[Route(path: '/conversation/{type}/{referring}', name: 'pushword_conversation', requirements: [
        'type' => '[a-zA-Z0-9-]*',
        'referring' => '[-A-Za-z0-9_\/\.]*',
    ], methods: ['POST', 'GET'])]
    public function show(Request $request, string $type): Response
    {
        // Reset per-request state. This controller is a shared service, so under a
        // long-running worker (FrankenPHP, RoadRunner…) $form would survive between
        // requests and pin every later request to the first request's host/locale.
        // show() is the only entrypoint, so clearing here is enough to guarantee a
        // fresh resolution per request.
        $this->form = null;

        $host = $request->query->getString('host') ?: $request->getHost();
        $this->apps->switchSite($host);

        // A foreign origin was already refused by ConversationCorsListener, before this
        // limiter: a hostile page cannot spend its visitors' submission quota.
        if ($request->isMethod(Request::METHOD_POST)) {
            $limit = $this->anonymousContentLimiter
                ->create(($request->getClientIp() ?? 'unknown').':'.$this->apps->get()->getMainHost())
                ->consume();
            if (! $limit->isAccepted()) {
                $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

                throw new TooManyRequestsHttpException($retryAfter, 'Too many submissions. Please try again later.');
            }
        }

        // The locale is resolved by ConversationLocaleListener, early enough for the
        // translator and the validator to pick it up.

        $response = new Response();

        $form = $this->getFormManager($type, $request)->getCurrentStep()->getForm();
        $form->handleRequest($request);
        /** @var FormInterface<Message|null> $form */
        if ($form->isSubmitted()) {
            return $response->setContent($this->getFormManager($type, $request)->validCurrentStep($form));
        }

        return $response->setContent($this->getFormManager($type, $request)->showForm($form));
    }
}
