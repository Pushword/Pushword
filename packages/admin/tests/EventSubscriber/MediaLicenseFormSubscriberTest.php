<?php

namespace Pushword\Admin\Tests\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\EventSubscriber\MediaLicenseFormSubscriber;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Image\License\MediaLicense;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The seven fields are `mapped => false`, so this subscriber is the only thing that
 * persists them.
 */
final class MediaLicenseFormSubscriberTest extends TestCase
{
    /** @param array<string, mixed> $submitted */
    private function submit(Media $media, array $submitted): void
    {
        $request = new Request(request: ['Media' => $submitted]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        new MediaLicenseFormSubscriber($requestStack)
            ->applyLicense(new BeforeEntityUpdatedEvent($media));
    }

    public function testASubmittedValueIsStored(): void
    {
        $media = new Media();
        $this->submit($media, [MediaLicense::CREDIT_TEXT => 'Altimood']);

        self::assertSame('Altimood', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
    }

    /** The collection editor submits one row per creator, each with its own type. */
    public function testTheCreatorRowsAreStoredWithTheirTypes(): void
    {
        $media = new Media();
        $this->submit($media, [MediaLicense::CREATOR => [
            ['name' => ' Robin ', 'type' => 'Person'],
            ['name' => 'Altimood', 'type' => 'Organization'],
            ['name' => '', 'type' => 'Person'],
        ]]);

        self::assertSame([
            ['name' => 'Robin', 'type' => 'Person'],
            ['name' => 'Altimood', 'type' => 'Organization'],
        ], MediaLicense::creators($media));
    }

    /** Anything with a single input to give may still hand over the compact form. */
    public function testACompactCreatorStringIsAccepted(): void
    {
        $media = new Media();
        $this->submit($media, [MediaLicense::CREATOR => ' Robin , Etienne ,, Robin ']);

        self::assertSame([
            ['name' => 'Robin', 'type' => 'Person'],
            ['name' => 'Etienne', 'type' => 'Person'],
        ], MediaLicense::creators($media));
    }

    /**
     * A collection with no rows posts nothing under its own name, so without the
     * hidden marker "every creator removed" would read as "field not on this form".
     */
    public function testRemovingEveryRowClearsTheCreators(): void
    {
        $media = new Media();
        $media->setCustomProperty(MediaLicense::CREATOR, [['name' => 'Robin', 'type' => 'Person']]);

        $this->submit($media, [MediaLicenseFormSubscriber::CREATOR_MARKER => '1']);

        self::assertFalse($media->hasCustomProperty(MediaLicense::CREATOR));
    }

    /** A bare hostname would be rejected by the field's own validation on the next save. */
    public function testUrlsAreNormalized(): void
    {
        $media = new Media();
        $this->submit($media, [MediaLicense::LICENSE => 'altimood.test/terms']);

        self::assertSame('https://altimood.test/terms', $media->getCustomPropertyScalar(MediaLicense::LICENSE));
    }

    /** Submitted but empty is how the "clear" button makes a media stop emitting. */
    public function testAnEmptySubmittedFieldRemovesTheKey(): void
    {
        $media = new Media();
        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'Altimood');
        $media->setCustomProperty(MediaLicense::CREATOR, [['name' => 'Robin', 'type' => 'Person']]);

        $this->submit($media, [MediaLicense::CREDIT_TEXT => '', MediaLicense::CREATOR => '  ']);

        self::assertFalse($media->hasCustomProperty(MediaLicense::CREDIT_TEXT));
        self::assertFalse($media->hasCustomProperty(MediaLicense::CREATOR));
    }

    /**
     * Absent is not the same as empty: a form that never showed the field (an API-ish
     * partial submit) must leave the stored value alone.
     */
    public function testAnAbsentFieldLeavesItsValueAlone(): void
    {
        $media = new Media();
        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'Altimood');

        $this->submit($media, [MediaLicense::LICENSE => 'https://altimood.test/terms']);

        self::assertSame('Altimood', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
    }

    /** Every EasyAdmin save goes through this subscriber, including the page form. */
    public function testARequestWithoutAnyLicenseFieldIsANoOp(): void
    {
        $media = new Media();
        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'Altimood');

        $this->submit($media, ['alt' => 'Refuge', 'tags' => 'montagne']);

        self::assertSame('Altimood', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
    }

    public function testANonMediaEntityIsIgnored(): void
    {
        $page = new Page();
        $request = new Request(request: ['Page' => [MediaLicense::CREDIT_TEXT => 'Altimood']]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        new MediaLicenseFormSubscriber($requestStack)
            ->applyLicense(new BeforeEntityUpdatedEvent($page));

        self::assertFalse($page->hasCustomProperty(MediaLicense::CREDIT_TEXT));
    }
}
