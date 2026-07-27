<?php

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Utils\FlashBag;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class MediaLicenseAdminTest extends AbstractAdminTestClass
{
    private const array SEED = [
        'license' => 'https://altimood.test/mentions-legales',
        'creditText' => 'Altimood',
        'creator' => [['name' => 'Altimood', 'type' => 'Organization']],
    ];

    /** @param array<string, mixed> $license */
    private function createMedia(array $license = []): Media
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $fileName = 'test-license-admin-'.uniqid().'.png';
        $image = imagecreatetruecolor(4, 2);
        \assert(false !== $image);
        imagepng($image, $mediaDir.'/'.$fileName);

        $media = new Media()
            ->setProjectDir($projectDir)
            ->setStoreIn($mediaDir)
            ->setMimeType('image/png')
            ->setDimensions([4, 2])
            ->setSize(1)
            ->setFileName($fileName)
            ->setAlt('__license_admin_test__');

        foreach ($license as $key => $value) {
            $media->setCustomProperty($key, $value);
        }

        $em->persist($media);
        $em->flush();

        return $media;
    }

    private function remove(Media $media): void
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');
        $fileName = $media->getFileName();

        $managed = $em->getRepository(Media::class)->find($media->id);
        if ($managed instanceof Media) {
            $em->remove($managed);
            $em->flush();
        }

        @unlink($mediaDir.'/'.$fileName);
    }

    /** @param array<string, mixed> $seed */
    private function configureSeed(array $seed): void
    {
        /** @var SiteRegistry $apps */
        $apps = self::getContainer()->get(SiteRegistry::class);
        $apps->get()->setCustomProperty('media_default_license_seed', $seed);
    }

    /**
     * The creator field is a collection, which DomCrawler cannot assign to, so the
     * license values go in through the raw payload the browser would post.
     *
     * @param array<string, mixed> $license
     */
    private function submitLicense(Form $form, array $license): void
    {
        $values = $form->getPhpValues();
        /** @var array<string, mixed> $media */
        $media = $values['Media'] ?? [];

        $values['Media'] = [...$media, ...$license];

        $this->client?->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());
    }

    private function editCrawler(Media $media): Crawler
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $router = self::getContainer()->get('router');

        return $client->request(
            Request::METHOD_GET,
            $router->generate('admin_media_edit', ['entityId' => $media->id]),
        );
    }

    public function testTheEditFormExposesEveryLicenseField(): void
    {
        $media = $this->createMedia([MediaLicense::CREDIT_TEXT => 'Altimood']);

        $crawler = $this->editCrawler($media);

        foreach (MediaLicense::KEYS as $key) {
            self::assertGreaterThan(
                0,
                $crawler->filter('[data-pw-license-field="'.$key.'"]')->count(),
                $key.' is missing from the media form',
            );
        }

        $this->remove($media);
    }

    /**
     * Registering the keys as managed is what keeps the CustomProperties YAML textarea
     * from offering a second, conflicting way to edit them.
     */
    public function testLicenseKeysStayOutOfTheCustomPropertiesTextarea(): void
    {
        $media = $this->createMedia([
            MediaLicense::CREDIT_TEXT => 'Altimood',
            'unrelatedKey' => 'kept',
        ]);

        $crawler = $this->editCrawler($media);
        $textarea = $crawler->filter('textarea[data-editor="yaml"]')->text();

        self::assertStringContainsString('unrelatedKey', $textarea);
        self::assertStringNotContainsString(MediaLicense::CREDIT_TEXT, $textarea);

        $this->remove($media);
    }

    /** The buttons are built client-side from the seed handed over as data attributes. */
    public function testTheSeedIsExposedToTheApplyButton(): void
    {
        // Log in first: createClient() boots a fresh kernel, which would discard a
        // seed configured on the previous container.
        $this->loginUser()->disableReboot();
        $this->configureSeed(self::SEED);
        $media = $this->createMedia();

        $crawler = $this->editCrawler($media);
        $field = $crawler->filter('[data-pw-license-field="'.MediaLicense::LICENSE.'"]');

        self::assertSame('https://altimood.test/mentions-legales', $field->attr('data-pw-license-seed-license'));
        self::assertSame('Altimood', $field->attr('data-pw-license-seed-credittext'));
        // Creators go over as JSON: applying the seed adds one collection row per name,
        // each with its own type.
        self::assertSame(
            '[{"name":"Altimood","type":"Organization"}]',
            $field->attr('data-pw-license-seed-creator'),
        );

        $this->remove($media);
        $this->configureSeed([]);
    }

    /** Empty licensing fields on a third-party media look like a bug unless explained. */
    public function testAThirdPartyMediaCarriesItsStateForTheExplanatoryNote(): void
    {
        $media = $this->createMedia([MediaLicense::CREATOR => ['Enrico Romanzi']]);
        $media->setLicenseState(MediaLicense::STATE_THIRD_PARTY);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();

        $crawler = $this->editCrawler($media);

        self::assertSame(
            MediaLicense::STATE_THIRD_PARTY,
            $crawler->filter('[data-pw-license-field="'.MediaLicense::LICENSE.'"]')->attr('data-pw-license-state'),
        );

        $this->remove($media);
    }

    public function testSubmittingTheFormPersistsTheLicenseAndFlipsTheState(): void
    {
        $media = $this->createMedia();
        $mediaId = $media->id;

        $crawler = $this->editCrawler($media);
        $form = $crawler->filter('form[name="Media"]')->form();

        // The creator collection is compound, so the rows go in through the raw
        // payload — which is also what the browser posts.
        $this->submitLicense($form, [
            MediaLicense::CREDIT_TEXT => 'Altimood',
            MediaLicense::LICENSE => 'altimood.test/terms',
            MediaLicense::CREATOR => [
                ['name' => 'Robin', 'type' => 'Person'],
                ['name' => 'Altimood', 'type' => 'Organization'],
            ],
        ]);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->clear();

        $saved = $em->getRepository(Media::class)->find($mediaId);
        self::assertInstanceOf(Media::class, $saved);

        self::assertSame('Altimood', $saved->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
        // Each row keeps its own type all the way to storage.
        self::assertSame([
            ['name' => 'Robin', 'type' => 'Person'],
            ['name' => 'Altimood', 'type' => 'Organization'],
        ], MediaLicense::creators($saved));
        // A bare hostname would fail the UrlField's own validation on the next save.
        self::assertSame('https://altimood.test/terms', $saved->getCustomPropertyScalar(MediaLicense::LICENSE));
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $saved->getLicenseState());

        $this->remove($saved);
    }

    /** What the "clear" button leaves behind: emptied fields drop their keys. */
    public function testSubmittingEmptyFieldsClearsTheLicense(): void
    {
        $media = $this->createMedia([
            MediaLicense::CREDIT_TEXT => 'Altimood',
            MediaLicense::CREATOR => ['Robin'],
        ]);
        $media->setLicenseState(MediaLicense::STATE_SEEDED);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();

        $mediaId = $media->id;

        $crawler = $this->editCrawler($media);
        $form = $crawler->filter('form[name="Media"]')->form();

        // Removing every row posts no `creator` key at all — the hidden marker is what
        // tells that apart from a form that never showed the field.
        $this->submitLicense($form, [MediaLicense::CREDIT_TEXT => '', MediaLicense::CREATOR => []]);

        $em->clear();
        $saved = $em->getRepository(Media::class)->find($mediaId);
        self::assertInstanceOf(Media::class, $saved);

        self::assertSame([], MediaLicense::extract($saved));
        self::assertSame(MediaLicense::STATE_NONE, $saved->getLicenseState());

        $this->remove($saved);
    }

    /**
     * Discarding a hand-written license is the one case a replacement must not do
     * silently — the values described the previous file, and only the editor knows
     * whether they still hold.
     */
    public function testReplacingTheFileOfAnAssertedMediaWarnsTheEditor(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $media = $this->createMedia([MediaLicense::CREDIT_TEXT => 'Photo maison']);
        $media->setLicenseState(MediaLicense::STATE_OVERRIDDEN);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();

        $mediaId = $media->id;

        $replacement = sys_get_temp_dir().'/replacement-'.uniqid().'.png';
        $image = imagecreatetruecolor(6, 3);
        \assert(false !== $image);
        imagepng($image, $replacement);

        $crawler = $this->editCrawler($media);
        $form = $crawler->filter('form[name="Media"]')->form();
        $fileField = $form['Media[mediaFile]'];
        self::assertInstanceOf(FileFormField::class, $fileField);
        $fileField->upload($replacement);
        $client->submit($form);

        $flashBag = FlashBag::get($client->getRequest());
        self::assertNotNull($flashBag);

        $messages = [];
        foreach ($flashBag->peekAll() as $typeMessages) {
            foreach ((array) $typeMessages as $message) {
                if (\is_string($message)) {
                    $messages[] = $message;
                }
            }
        }

        self::assertStringContainsString(
            self::getContainer()->get('translator')->trans('mediaLicenseWasReset'),
            implode(' ', $messages),
            'replacing the file of an asserted media must say the license was reset',
        );

        $em->clear();
        $saved = $em->getRepository(Media::class)->find($mediaId);
        self::assertInstanceOf(Media::class, $saved);
        self::assertNotSame('Photo maison', $saved->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));

        @unlink($replacement);
        $this->remove($saved);
    }

    public function testTheIndexOffersALicenseStateFilter(): void
    {
        $media = $this->createMedia([MediaLicense::CREDIT_TEXT => 'Altimood']);

        $client = $this->loginUser();
        $client->catchExceptions(false);

        $router = self::getContainer()->get('router');

        $client->request(Request::METHOD_GET, $router->generate('admin_media_index', [
            'filters' => ['licenseState' => ['comparison' => '=', 'value' => [MediaLicense::STATE_THIRD_PARTY]]],
        ]));

        self::assertResponseIsSuccessful();

        $this->remove($media);
    }

    /**
     * The media index is a hand-written template, not EasyAdmin's field renderer, so a
     * field declared in configureFields() shows up only if the template mirrors it.
     * Creating a media redirects here, which makes this row the editor's first look at
     * what the upload decided.
     */
    public function testTheIndexShowsTheLicenseStateOfEachMedia(): void
    {
        $media = $this->createMedia([MediaLicense::CREATOR => ['Enrico Romanzi']]);
        $media->setLicenseState(MediaLicense::STATE_THIRD_PARTY);

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();

        $client = $this->loginUser();
        $client->catchExceptions(false);

        $router = self::getContainer()->get('router');
        $crawler = $client->request(Request::METHOD_GET, $router->generate('admin_media_index', [
            'query' => '__license_admin_test__',
        ]));

        self::assertStringContainsString(
            self::getContainer()->get('translator')->trans('adminMediaLicenseStateThirdParty'),
            $crawler->filter('.pw-media-table')->html(),
        );

        $this->remove($media);
    }
}
