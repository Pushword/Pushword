<?php

use BabDev\PagerfantaBundle\BabDevPagerfantaBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use KnpU\OAuth2ClientBundle\KnpUOAuth2ClientBundle;
use League\FlysystemBundle\FlysystemBundle;
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
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;
use Vich\UploaderBundle\VichUploaderBundle;

/**
 * Used for test.
 */
$bundles = [
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
    FlysystemBundle::class => ['all' => true],
    VichUploaderBundle::class => ['all' => true],

    // - generate default welcome page
    DoctrineFixturesBundle::class => ['all' => true],

    // Used for Admin
    StimulusBundle::class => ['all' => true],
    EasyAdminBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    TwigExtraBundle::class => ['all' => true],

    // Used for tests
    MakerBundle::class => ['dev' => true, 'test' => true],
    WebProfilerBundle::class => ['dev' => true],
    DebugBundle::class => ['dev' => true],

    // No need for testing purpose, useful else
    // Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
    // Pushword\Conversation\PushwordConversation::class => ['all' => true],
];

// Conditionally register OAuth bundle if installed
if (class_exists(KnpUOAuth2ClientBundle::class)) {
    $bundles[KnpUOAuth2ClientBundle::class] = ['all' => true];
}

return $bundles;
