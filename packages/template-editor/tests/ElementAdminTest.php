<?php

namespace Pushword\TemplateEditor\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\TemplateEditor\ElementRepository;

class ElementAdminTest extends AbstractAdminTestClass
{
    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request('GET', '/admin/template/list');
        self::assertResponseIsSuccessful();

        $repo = new ElementRepository(self::bootKernel()->getProjectDir().'/templates', [], false);
        $element = $repo->getAll()[0];

        $client->request('GET', '/admin/template/edit/'.$element->getEncodedPath()); // /pushword.piedweb.com/page/_content.html.twig
        self::assertResponseIsSuccessful();
    }
}
