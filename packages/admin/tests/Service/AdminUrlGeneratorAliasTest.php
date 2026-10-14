<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Service\AdminUrlGeneratorAlias;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class AdminUrlGeneratorAliasTest extends KernelTestCase
{
    public function testThePageListCarriesItsFilterAsEasyAdminFilters(): void
    {
        self::bootKernel();
        $alias = self::getContainer()->get(AdminUrlGeneratorAlias::class);

        $url = $alias->generate('admin_page_list', ['filter' => [
            'slug' => ['comparison' => 'like', 'value' => 'promote-'],
            0 => 'a field must be named',
        ]]);

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame(['slug' => ['comparison' => 'like', 'value' => 'promote-']], $query['filters'] ?? null);
    }
}
