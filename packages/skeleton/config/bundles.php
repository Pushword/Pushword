<?php

use BabDev\PagerfantaBundle\BabDevPagerfantaBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Knp\Bundle\MenuBundle\KnpMenuBundle;
use Pushword\Admin\PushwordAdminBundle;
use Pushword\AdminBlockEditor\PushwordAdminBlockEditorBundle;
use Pushword\AdvancedMainImage\PushwordAdvancedMainImageBundle;
use Pushword\Conversation\PushwordConversationBundle;
use Pushword\Core\PushwordCoreBundle;
use Pushword\Flat\PushwordFlatBundle;
use Pushword\PageScanner\PushwordPageScannerBundle;
use Pushword\PageUpdateNotifier\PushwordPageUpdateNotifierBundle;
use Pushword\StaticGenerator\PushwordStaticGeneratorBundle;
use Pushword\TemplateEditor\PushwordTemplateEditorBundle;
use Pushword\Version\PushwordVersionBundle;
use Sonata\AdminBundle\SonataAdminBundle;
use Sonata\BlockBundle\SonataBlockBundle;
use Sonata\Doctrine\Bridge\Symfony\SonataDoctrineBundle;
use Sonata\DoctrineORMAdminBundle\SonataDoctrineORMAdminBundle;
use Sonata\Form\Bridge\Symfony\SonataFormBundle;
use Sonata\Twig\Bridge\Symfony\SonataTwigBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Vich\UploaderBundle\VichUploaderBundle;

/**
 * Used for test.
 */
return [
    PushwordCoreBundle::class => ['all' => true],
    PushwordAdminBundle::class => ['all' => true],
    PushwordPageUpdateNotifierBundle::class => ['all' => true],
    PushwordStaticGeneratorBundle::class => ['all' => true],
    PushwordPageScannerBundle::class => ['all' => true],
    PushwordTemplateEditorBundle::class => ['all' => true],
    PushwordFlatBundle::class => ['all' => true],
    PushwordConversationBundle::class => ['all' => true],
    PushwordVersionBundle::class => ['all' => true],
    PushwordAdminBlockEditorBundle::class => ['all' => true],
    PushwordAdvancedMainImageBundle::class => ['all' => true],

    // Symfony
    MonologBundle::class => ['all' => true],
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],

    DoctrineBundle::class => ['all' => true],

    BabDevPagerfantaBundle::class => ['all' => true],

    // Used for Media
    VichUploaderBundle::class => ['all' => true],

    // - generate default welcome page
    DoctrineFixturesBundle::class => ['all' => true],

    // Used for Admin
    // - Sonata
    SonataBlockBundle::class => ['all' => true],
    KnpMenuBundle::class => ['all' => true],
    SonataAdminBundle::class => ['all' => true],
    SonataDoctrineORMAdminBundle::class => ['all' => true],
    SonataFormBundle::class => ['all' => true],
    SonataTwigBundle::class => ['all' => true],
    SonataDoctrineBundle::class => ['all' => true],
    // Sonata\Exporter\Bridge\Symfony\SonataExporterSymfonyBundle::class => ['all' => true],

    // Used for tests
    MakerBundle::class => ['dev' => true, 'test' => true],
    WebProfilerBundle::class => ['dev' => true],
    DebugBundle::class => ['dev' => true],

    // No need for testing purpose, useful else
    // Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
    // Pushword\Conversation\PushwordConversation::class => ['all' => true],
];
