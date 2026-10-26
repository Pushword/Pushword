<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Frontend;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('panther')]
final class AdminMediaFileDropTest extends AbstractPantherAdminTest
{
    public function testDropSelectsAFileOnNewAndEditForms(): void
    {
        $client = $this->createPantherClientWithLogin();

        foreach (['/admin/media/new', '/admin/media/1/edit'] as $url) {
            $client->request(Request::METHOD_GET, $url);
            self::assertTrue($this->pollUntilTrue(
                $client,
                'return document.querySelector("#Media_mediaFile")?.dataset.pwFileDropReady === "1"',
            ));

            $result = $client->executeScript(<<<'JS'
                const input = document.querySelector('#Media_mediaFile');
                const transfer = new DataTransfer();
                transfer.items.add(new File(['audio'], 'dropped.mp3', { type: 'audio/mpeg' }));
                transfer.items.add(new File(['extra'], 'extra.mp3', { type: 'audio/mpeg' }));
                let changes = 0;
                input.form.addEventListener('change', event => {
                    if (event.target === input) changes++;
                });
                const drop = new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: transfer });
                document.querySelector('h1').dispatchEvent(drop);
                return {
                    prevented: drop.defaultPrevented,
                    names: Array.from(input.files, file => file.name),
                    submitted: new FormData(input.form).get(input.name).name,
                    focused: document.activeElement === input,
                    changes,
                };
                JS);

            self::assertIsArray($result);
            self::assertTrue($result['prevented']);
            self::assertSame(['dropped.mp3'], $result['names']);
            self::assertSame('dropped.mp3', $result['submitted']);
            self::assertTrue($result['focused']);
            self::assertSame(1, $result['changes']);
        }
    }
}
