<?php

/**
 * Used for test.
 */

return [
    Pushword\Core\PushwordCoreBundle::class => ['all' => true],
    Pushword\PageUpdateNotifier\PushwordPageUpdateNotifierBundle::class => ['all' => true],
    Pushword\StaticGenerator\PushwordStaticGeneratorBundle::class => ['all' => true],
    Pushword\PageScanner\PushwordPageScannerBundle::class => ['all' => true],
    Pushword\TemplateEditor\PushwordTemplateEditorBundle::class => ['all' => true],
    Pushword\Admin\PushwordAdminBundle::class => ['all' => true],
    Pushword\Flat\PushwordFlatBundle::class => ['all' => true],
    Pushword\Conversation\PushwordConversationBundle::class => ['all' => true],

    // Symfony
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],

    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],

    // Used for Media
    Vich\UploaderBundle\VichUploaderBundle::class => ['all' => true],
    Liip\ImagineBundle\LiipImagineBundle::class => ['all' => true],

    // Used for Page
    Knp\Bundle\MarkdownBundle\KnpMarkdownBundle::class => ['all' => true],
    // - generate default welcome page
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['all' => true],

    // Used in Extension/StaticGenerator
    // - for security(is_granted()) annotation in controller
    Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class => ['all' => true],

    // Used for Admin
    // - Sonata
    Sonata\BlockBundle\SonataBlockBundle::class => ['all' => true],
    Knp\Bundle\MenuBundle\KnpMenuBundle::class => ['all' => true],
    Sonata\AdminBundle\SonataAdminBundle::class => ['all' => true],
    Sonata\DoctrineORMAdminBundle\SonataDoctrineORMAdminBundle::class => ['all' => true],
    Sonata\Form\Bridge\Symfony\SonataFormBundle::class => ['all' => true],
    Sonata\Twig\Bridge\Symfony\SonataTwigBundle ::class => ['all' => true],
    Sonata\Doctrine\Bridge\Symfony\SonataDoctrineBundle::class => ['all' => true],
    //Sonata\Exporter\Bridge\Symfony\SonataExporterSymfonyBundle::class => ['all' => true],

    // Used for tests
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['all'=>true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['all'=>true],

    // No need for testing purpose, useful else
    //Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => ['all' => true],
    //Pushword\Conversation\PushwordConversation::class => ['all' => true],
];
