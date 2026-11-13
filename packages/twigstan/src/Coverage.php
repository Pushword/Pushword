<?php

declare(strict_types=1);

namespace Pushword\TwigStan;

use Symfony\Component\Filesystem\Filesystem;
use TwigStan\Config\TwigStanConfig;

final class Coverage
{
    public static function check(TwigStanConfig $config): int
    {
        $kernel = require $config->twigEnvironmentLoader;
        $loader = Environment::twig($kernel)->getLoader();
        $result = json_decode(new Filesystem()->readFile($config->tempDirectory.'/phpstan/collect-php-render-points.result.json'), true, flags: \JSON_THROW_ON_ERROR);
        $rendered = [];
        foreach ($result['collectedData'] as $collected) {
            foreach ($collected['data'] as $group) {
                foreach ($group as $render) {
                    $path = $loader->getSourceContext($render['template'])->getPath();
                    $resolved = realpath($path);
                    if (false !== $resolved) {
                        $rendered[$resolved] = true;
                    }
                }
            }
        }

        $covered = 0;
        $missing = [];
        foreach ($config->twigPaths as $template) {
            $resolved = realpath($template);
            if (false !== $resolved && isset($rendered[$resolved])) {
                ++$covered;
            } else {
                $missing[] = $template;
            }
        }

        echo json_encode(['tool' => 'twigstan-coverage', 'covered' => $covered, 'missing' => $missing], \JSON_THROW_ON_ERROR)."\n";

        return [] === $missing ? 0 : 1;
    }
}
