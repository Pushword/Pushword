<?php

namespace Pushword\StaticGenerator\Tests;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\GenerationStateManager;
use Pushword\StaticGenerator\StaticController;
use Pushword\StaticGenerator\StaticGenerationCoordinator;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Error\RuntimeError;

#[Group('integration')]
final class StaticGeneratorControllerTest extends AbstractAdminTestClass
{
    protected function tearDown(): void
    {
        // Wait for any background static generation process to complete
        // to avoid interfering with subsequent tests
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        $outputStorage = self::getContainer()->get(ProcessOutputStorage::class);
        $pidFile = $processManager->getPidFilePath('static-generator');
        // Wait up to 30 seconds for the process to complete
        $maxWait = 30;
        $waited = 0;
        while ($waited < $maxWait) {
            $processManager->cleanupStaleProcess($pidFile);
            $info = $processManager->getProcessInfo($pidFile);
            if (! $info['isRunning']) {
                break;
            }

            sleep(1);
            ++$waited;
        }

        // Clean up PID file and output storage
        $fs = new Filesystem();
        $fs->remove($pidFile);

        $outputStorage->clear('static-generator');
        $fs->remove(sys_get_temp_dir().'/pushword-controller-test-'.getmypid());
        parent::tearDown();
    }

    public function testController(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/static');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/admin/static/localhost.dev');
        self::assertResponseIsSuccessful();
    }

    /**
     * The live console polls itself: each fragment re-carries the trigger that
     * fetches the next one. Losing it freezes the output mid-generation.
     */
    public function testRunningOutputFragmentCarriesThePollTrigger(): void
    {
        $this->loginUser();

        $fragment = $this->renderOutputFragment('running');

        self::assertStringContainsString('hx-get=', $fragment);
        self::assertStringContainsString('hx-trigger="load delay:500ms"', $fragment);
        self::assertStringContainsString('hx-swap="outerHTML"', $fragment);
        self::assertStringContainsString('hx-target="#static-output-content"', $fragment);
    }

    /**
     * The other half of the same contract: the server ends the loop by omitting
     * the trigger and downgrading the swap, so the finished fragment lands
     * inside the container instead of replacing it. A fragment that keeps its
     * trigger polls the endpoint forever.
     */
    public function testOutputFragmentStopsPollingAndReswapsWhenGenerationIsOver(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $this->markProcessFinished('completed');

        $client->request(Request::METHOD_GET, '/admin/static-output');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('id="static-output-content"', $content);
        self::assertStringNotContainsString('hx-trigger', $content);
        self::assertStringNotContainsString('hx-get', $content);
        self::assertSame('innerHTML', $client->getResponse()->headers->get('HX-Reswap'));
    }

    /**
     * A fragment that was waiting on a blocking generation hands the browser off
     * with HX-Redirect once it ends — htmx 4 still honours the header.
     */
    public function testPendingOutputHandsOffWithHxRedirectWhenGenerationIsOver(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $this->markProcessFinished('completed');

        $client->request(Request::METHOD_GET, '/admin/static-output?pending=1');

        self::assertResponseIsSuccessful();
        self::assertSame('', (string) $client->getResponse()->getContent());
        self::assertResponseHasHeader('HX-Redirect');
        self::assertStringContainsString('/admin/static', (string) $client->getResponse()->headers->get('HX-Redirect'));
    }

    private function renderOutputFragment(string $status): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('@PushwordStatic/output_fragment.html.twig', [
            'status' => $status,
            'output' => '',
            'errors' => [],
            'host' => null,
            'pending' => false,
            'outputProcessType' => StaticGenerationCoordinator::PROCESS_TYPE,
        ]);
    }

    /**
     * No pid file means no running process, so readOutput() falls back to the
     * stored status — the state the controller sees once a generation is over.
     */
    private function markProcessFinished(string $status): void
    {
        /** @var BackgroundProcessManager $processManager */
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        /** @var ProcessOutputStorage $outputStorage */
        $outputStorage = self::getContainer()->get(ProcessOutputStorage::class);

        new Filesystem()->remove($processManager->getPidFilePath(StaticGenerationCoordinator::PROCESS_TYPE));
        $outputStorage->setStatus(StaticGenerationCoordinator::PROCESS_TYPE, $status);
    }

    public function testDispatchErrorIsShownInConsole(): void
    {
        $client = $this->loginUser();

        $container = $client->getContainer();

        $outputStorage = new ProcessOutputStorage(new Filesystem(), sys_get_temp_dir().'/pushword-controller-test-'.getmypid());

        $mockProcessManager = self::createStub(BackgroundProcessManager::class);
        $mockProcessManager->method('getProcessInfo')->willReturn(['isRunning' => false, 'startTime' => null, 'pid' => null]);
        $mockProcessManager->method('getPidFilePath')->willReturn(sys_get_temp_dir().'/pushword-test-static.pid');

        $mockDispatcher = self::createStub(BackgroundTaskDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('nohup failed'));

        $mockAdminContext = self::createStub(AdminContextProviderInterface::class);
        $mockAdminContext->method('getContext')->willReturn(null);

        $controller = new StaticController(
            $this->coordinator($container, $mockDispatcher, $mockProcessManager, $outputStorage),
        );
        $controller->setAdminContextProvider($mockAdminContext);
        $controller->setContainer($container);

        try {
            $controller->generateStatic();
        } catch (RuntimeError) {
        }

        self::assertSame('error', $outputStorage->getStatus('static-generator'));
        self::assertStringContainsString('nohup failed', $outputStorage->read('static-generator')['content']);
    }

    public function testDispatchErrorWithHostUsesPerHostProcessType(): void
    {
        $client = $this->loginUser();
        $container = $client->getContainer();

        $outputStorage = new ProcessOutputStorage(new Filesystem(), sys_get_temp_dir().'/pushword-controller-test-'.getmypid());

        $mockProcessManager = self::createStub(BackgroundProcessManager::class);
        $mockProcessManager->method('getProcessInfo')->willReturn(['isRunning' => false, 'startTime' => null, 'pid' => null]);
        $mockProcessManager->method('getPidFilePath')->willReturn(sys_get_temp_dir().'/pushword-test-static-host.pid');

        $mockDispatcher = self::createStub(BackgroundTaskDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('nohup failed'));

        $mockAdminContext = self::createStub(AdminContextProviderInterface::class);
        $mockAdminContext->method('getContext')->willReturn(null);

        $controller = new StaticController(
            $this->coordinator($container, $mockDispatcher, $mockProcessManager, $outputStorage),
        );
        $controller->setAdminContextProvider($mockAdminContext);
        $controller->setContainer($container);

        try {
            $controller->generateStatic('localhost.dev');
        } catch (RuntimeError) {
        }

        self::assertSame('error', $outputStorage->getStatus('static-generator--localhost.dev'));
        self::assertStringContainsString('nohup failed', $outputStorage->read('static-generator--localhost.dev')['content']);
    }

    private function coordinator(
        ContainerInterface $container,
        BackgroundTaskDispatcherInterface $dispatcher,
        BackgroundProcessManager $processManager,
        ProcessOutputStorage $outputStorage,
    ): StaticGenerationCoordinator {
        /** @var SiteRegistry $siteRegistry */
        $siteRegistry = $container->get(SiteRegistry::class);
        /** @var GenerationStateManager $stateManager */
        $stateManager = $container->get(GenerationStateManager::class);

        return new StaticGenerationCoordinator($dispatcher, $processManager, $outputStorage, $siteRegistry, $stateManager);
    }
}
