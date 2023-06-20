<?php

namespace Pushword\TemplateEditor\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\TemplateEditor\ElementRepository;

class ElementAdminTest extends AbstractAdminTestClass
{
    public function testAdmin()
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request('GET', '/admin/template/list');
        $this->assertResponseIsSuccessful();

        $repo = new ElementRepository(self::$kernel->getProjectDir().'/templates');
        $element = $repo->getAll()[0];

        $client->request('GET', '/admin/template/edit/'.$element->getEncodedPath()); // /pushword.piedweb.com/page/_content.html.twig
        $this->assertResponseIsSuccessful();
    }
}
