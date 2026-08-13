<?php

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use PiedWeb\RenderAttributes\TwigExtension;
use Pushword\Core\BackgroundTask\MessengerBackgroundTaskDispatcher;
use Pushword\Core\BackgroundTask\RunCommandHandler;
use Pushword\Core\Command\SchemaDumpCommand;
use Pushword\Core\Component\EntityFilter\Filter\FilterInterface;
use Pushword\Core\Component\EntityFilter\FilterRegistry;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\UserRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Scheduler\CronScheduleProvider;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Service\VichUploadPropertyNamer;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Pushword\Core\Twig\OAuthExtension;
use Pushword\Core\Utils\ImageOptimizer\OptimizerChainFactory;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Bridge\Twig\AnsiExtension;
use Spatie\ImageOptimizer\OptimizerChain;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Twig\Extension\StringLoaderExtension;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$projectDir', '%kernel.project_dir%')
        // pushword/core and pushword/dev-app are siblings, in vendor/pushword/ as in the
        // monorepo's packages/, so one relative path reaches the skeleton in both.
        ->bind('$dockerSkeletonDir', \dirname(__DIR__, 4).'/dev-app/docker-skeleton')
        ->bind('$varDir', '%pw.var_dir%')
        ->bind('$filterSets', '%pw.image_filter_sets%')
        ->bind('$publicMediaDir', '%pw.public_media_dir%')
        ->bind('$mediaCacheDir', '%pw.media_cache_dir%')
        ->bind('$mediaDir', '%pw.media_dir%')
        ->bind('$rawApps', '%pw.apps%')
        ->bind('$pathToBin', '%pw.path_to_bin%')
        ->bind('$tailwindGeneratorIsActive', '%pw.tailwind_generator%')
        ->bind('$imageDriver', '%pw.image_driver%')
        ->bind('$pdfPreset', '%pw.pdf_preset%')
        ->bind('$pdfLinearize', '%pw.pdf_linearize%')
        ->bind('$enablePasswordReset', '%pw.enable_password_reset%')
        ->bind('$scheduledCommands', '%pw.scheduled_commands%');

    $services->load('Pushword\Core\\', __DIR__.'/../../../src/*')
        ->exclude([
            __DIR__.'/../../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);

    $services->load('Pushword\Core\Controller\\', __DIR__.'/../../../src/Controller')
        ->tag('controller.service_arguments');

    // Auto-tag all filters
    $services->instanceof(FilterInterface::class)
        ->tag('pushword.entity_filter');

    // Make FilterRegistry available and autowire tagged filters
    $services->set(FilterRegistry::class)
        ->arg('$filters', tagged_iterator('pushword.entity_filter'))
        ->public();

    $services->set(PagePropertySchemaRegistry::class)
        ->arg('$providers', tagged_iterator('pushword.page_properties_provider'))
        ->public();

    $services->set(SchemaDumpCommand::class)
        ->arg('$pageClass', '%pw.entity_page%');

    // # todo limit to test https://stackoverflow.com/questions/54466158/symfony-4-2-how-to-do-a-service-public-only-for-tests
    $services->set(PushwordRouteGenerator::class)
        ->public();

    $services->set(SiteRegistry::class)
        ->public()
        ->call('setRequestContext', [service(RequestContext::class)]);

    $services->set(RequestContext::class)
        ->public();

    $services->set(MediaRepository::class)
        ->public();

    $services->set(UserRepository::class)
        ->arg('$entityClass', '%pw.entity_user%')
        ->public();

    $services->set(ContentPipelineFactory::class)
        ->public();

    $services->set(MediaExtension::class)
        ->public();

    // See who to avoid limit for this one too
    $services->set(VichUploadPropertyNamer::class)
        ->public();

    // Spatie image optimizer chain (injected into ImageOptimizer so the optimize
    // path can be exercised in tests with a controllable chain).
    $services->set(OptimizerChain::class)
        ->factory(OptimizerChainFactory::create(...));

    $services->set(PushwordCoreBundle::class);
    $services->set(StringLoaderExtension::class);
    $services->set(TwigExtension::class);

    // ANSI to HTML converter for console output
    $services->set(AnsiToHtmlConverter::class);
    $services->set(AnsiExtension::class)
        ->args([service(AnsiToHtmlConverter::class)]);

    // Media Storage (Flysystem)
    $services->set(MediaStorageAdapter::class)
        ->args([
            '$storage' => service('pushword.mediaStorage'),
            '$mediaDir' => '%pw.media_dir%',
            '$isLocal' => true,
        ]);

    // Notification Email Sender - unified service for all notification emails
    $services->set(NotificationEmailSender::class)
        ->arg('$mailer', service(MailerInterface::class)->nullOnInvalid())
        ->public();

    // Background task dispatchers - Messenger mode (only when symfony/messenger is installed)
    if (interface_exists(MessageBusInterface::class)) {
        $services->set(MessengerBackgroundTaskDispatcher::class);
        $services->set(RunCommandHandler::class);
    }

    // Scheduler - only register if symfony/scheduler is installed
    if (interface_exists(ScheduleProviderInterface::class)) {
        $services->set(CronScheduleProvider::class);
    }

    // OAuth Extension - only register if KnpU OAuth2 Client Bundle is installed
    if (class_exists(ClientRegistry::class)) {
        $services->set(OAuthExtension::class)
            ->arg('$clientRegistry', service(ClientRegistry::class)->nullOnInvalid());
    } else {
        // Register with null values when OAuth is not available
        $services->set(OAuthExtension::class)
            ->arg('$clientRegistry', null);
    }
};
