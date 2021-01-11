<?php

namespace Pushword\TemplateEditor\Tests;

use Pushword\Admin\Tests\AbstractAdminTest;

class ElementAdminTest extends AbstractAdminTest
{
    public function testAdmin()
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request('GET', '/admin/template/list');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/admin/template/edit/d75972ad5182b92398cb571e2e223deb'); ///pushword.piedweb.com/page/_content.html.twig
        $this->assertResponseIsSuccessful();
    }
}
