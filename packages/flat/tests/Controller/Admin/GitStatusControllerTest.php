<?php

namespace Pushword\Flat\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class GitStatusControllerTest extends AbstractAdminTestClass
{
    /**
     * The history dates go through format_datetime — an int timestamp in, a
     * locale-formatted string out.
     */
    public function testTheHistoryTableRendersItsDates(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $varDir = self::getContainer()->getParameter('pw.var_dir');
        new Filesystem()->dumpFile($varDir.'/git-autocommit-status.json', json_encode([[
            'timestamp' => 1754300000,
            'success' => true,
            'steps' => [['command' => 'git commit', 'success' => true, 'output' => '']],
        ]], \JSON_THROW_ON_ERROR));

        $crawler = $client->request(Request::METHOD_GET, '/admin/git-status');

        self::assertResponseIsSuccessful();
        $dateCell = $crawler->filter('tbody td')->first()->text();
        self::assertMatchesRegularExpression('#\d{1,2}/\d{1,2}/\d{2}.*\d{1,2}:\d{2}:\d{2}#', $dateCell);
    }
}
