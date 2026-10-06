<?php

declare(strict_types=1);

use App\Kernel;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\TailwindGenerator;

require \dirname(__DIR__, 5).'/vendor/autoload.php';

if (! isset($argv[1], $argv[2])) {
    throw new RuntimeException('Expected a project directory and page ID.');
}

$generator = new TailwindGenerator(true, $argv[1], $argv[1].'/bin', new Kernel('prod', false));
$page = new Page();
new ReflectionProperty(Page::class, 'id')->setValue($page, (int) $argv[2]);
$page->mainContent = 'page-'.$argv[2];
$generator->run($page);
$generator->flush();
