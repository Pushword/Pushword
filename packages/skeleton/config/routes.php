<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->import('@PushwordVersionBundle/VersionRoutes.yaml');
    $routingConfigurator->import('@PushwordStaticGeneratorBundle/StaticRoutes.yaml');
    $routingConfigurator->import('@PushwordPageScannerBundle/PageScannerRoutes.yaml');
    $routingConfigurator->import('@PushwordTemplateEditorBundle/TemplateEditorRoutes.yaml');
    $routingConfigurator->import('@PushwordAdminBlockEditorBundle/AdminBlockEditorRoutes.yaml');
    $routingConfigurator->import('@PushwordAdminBundle/AdminRoutes.yaml');
    $routingConfigurator->import('@PushwordConversationBundle/Resources/config/routes/conversation.yaml');
    $routingConfigurator->import('@PushwordCoreBundle/Resources/config/routes.php');
};
