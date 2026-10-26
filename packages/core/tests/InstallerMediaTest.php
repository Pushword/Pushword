<?php

declare(strict_types=1);

namespace Pushword\Core\Tests;

use PhpToken;
use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;

use Symfony\Component\Finder\Finder;

/**
 * install.php mirrors dev-app's media~ into every new site, then removes what the
 * starter fixtures do not register. Any other file stays in the site's media/ with no
 * media row pointing at it.
 */
final class InstallerMediaTest extends TestCase
{
    public function testEveryMediaInstallPhpKeepsIsRegisteredByTheStarterFixtures(): void
    {
        $packages = \dirname(__DIR__, 2);
        $removed = $this->stringLiterals($packages.'/core/install.php');

        $kept = [];
        foreach (new Finder()->files()->in($packages.'/dev-app/media~')->depth(0) as $file) {
            if (! \in_array('media/'.$file->getFilename(), $removed, true)) {
                $kept[] = $file->getFilename();
            }
        }

        self::assertNotEmpty($kept);
        self::assertSame(
            [],
            array_values(array_diff($kept, $this->stringLiterals($packages.'/core/starter/AppFixtures.php'))),
            'install.php leaves these dev-app media in every new site; add them to its PostInstall::remove() list.',
        );
    }

    /** @return string[] */
    private function stringLiterals(string $phpFile): array
    {
        $literals = [];
        foreach (PhpToken::tokenize(file_get_contents($phpFile)) as $token) {
            if ($token->is(\T_CONSTANT_ENCAPSED_STRING)) {
                $literals[] = substr($token->text, 1, -1);
            }
        }

        return $literals;
    }
}
