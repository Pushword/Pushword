<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

require_once __DIR__.'/src/IntegerRuntime.php';

$twig = new Environment(new FilesystemLoader(__DIR__.'/templates'), ['strict_variables' => true]);
$twig->addGlobal('site_name', 'Demo');
$twig->addFunction(new TwigFunction('integer', IntegerRuntime::integer(...)));

return $twig;
