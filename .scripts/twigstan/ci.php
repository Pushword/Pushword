<?php

declare(strict_types=1);

$root = \dirname(__DIR__, 2);
$config = require __DIR__.'/config.php';
$templates = [
    'core/src/templates/user/forgot_password.html.twig',
    'core/src/templates/user/login.html.twig',
    'core/src/templates/user/set_password.html.twig',
    'core/src/templates/component/cardList.html.twig',
    'core/src/templates/component/encoded_mail.html.twig',
    'core/src/templates/component/link.html.twig',
    'core/src/templates/component/link_js.html.twig',
    'core/src/templates/component/phone_number.html.twig',
    'core/src/templates/component/txt_anchor.html.twig',
    'core/src/templates/component/video.html.twig',
    'core/src/templates/component/video_youtube_embed.html.twig',
    'conversation/src/templates/conversation/alert.html.twig',
    'conversation/src/templates/conversation/formBtn.html.twig',
    'conversation/src/templates/conversation/new_message.html.twig',
    'conversation/src/templates/conversation/new_message.subject.txt.twig',
    'conversation/src/templates/conversation/new_message.txt.twig',
    'static-generator/src/templates/Caddyfile.twig',
    'static-generator/src/templates/htaccess.twig',
    'static-generator/src/templates/output_fragment.html.twig',
    'static-generator/src/templates/redirect.html.twig',
    'page-scanner/src/templates/output_fragment.html.twig',
    'admin/src/templates/components/hold_toggle.html.twig',
];

return $config
    ->tempDirectory($config->create()->tempDirectory.'/ci')
    ->twigPaths(...array_map(static fn (string $path): string => $root.'/packages/'.$path, $templates))
    ->create();
