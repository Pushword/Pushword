<?php

namespace Pushword\Api\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class MediaApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** @var list<string> */
    private array $createdMediaFileNames = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'media-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        foreach ($this->createdMediaFileNames as $fileName) {
            $media = $em->getRepository(Media::class)->findOneBy(['fileName' => $fileName]);
            if ($media instanceof Media) {
                $em->remove($media);
            }
        }

        /** @var class-string<User> $userClass */
        $userClass = $container->getParameter('pw.entity_user');
        $user = $em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $em->remove($user);
        }

        $em->flush();
        parent::tearDown();
    }

    public function testListFiltersByKeyword(): void
    {
        $marker = uniqid();
        $this->createMedia('zz-needle-'.$marker.'.jpg', 'Some alt text');
        $this->createMedia('zz-other-'.$marker.'.jpg', 'Alt mentioning needle-'.$marker.' keyword');
        $this->createMedia('zz-unrelated-'.$marker.'.jpg', 'Nothing here');

        $response = $this->request('GET', '/api/media?q=needle-'.$marker);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertSame(2, $data['total'], 'q= should match fileName and alt');
        self::assertIsArray($data['items']);
        $filenames = array_column($data['items'], 'filename');
        sort($filenames);
        self::assertSame(['zz-needle-'.$marker.'.jpg', 'zz-other-'.$marker.'.jpg'], $filenames);
    }

    public function testListAcceptsSearchAsAliasForQ(): void
    {
        $marker = uniqid();
        $this->createMedia('zz-needle-'.$marker.'.jpg', 'Some alt text');
        $this->createMedia('zz-unrelated-'.$marker.'.jpg', 'Nothing here');

        $response = $this->request('GET', '/api/media?search=needle-'.$marker);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertSame(1, $data['total'], 'search= should behave like q=');
        self::assertIsArray($data['items']);
        $item = $data['items'][0];
        self::assertIsArray($item);
        self::assertSame('zz-needle-'.$marker.'.jpg', $item['filename']);
    }

    public function testListKeywordCombinesWithOtherFilters(): void
    {
        $marker = uniqid();
        $this->createMedia('zz-needle-'.$marker.'.jpg', 'Alt', 'image/jpeg');
        $this->createMedia('zz-needle-'.$marker.'.pdf', 'Alt', 'application/pdf');

        $response = $this->request('GET', '/api/media?q=needle-'.$marker.'&mimeType=application/pdf');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertSame(1, $data['total']);
        self::assertIsArray($data['items']);
        $item = $data['items'][0];
        self::assertIsArray($item);
        self::assertSame('zz-needle-'.$marker.'.pdf', $item['filename']);
    }

    public function testRotateSwapsDimensions(): void
    {
        $fileName = 'zz-rotate-'.uniqid().'.jpg';
        $this->createRealImageMedia($fileName, 6, 3);

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, ['rotate' => 90]);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertIsArray($data['image']);
        self::assertSame(3, $data['image']['width'], 'width and height must be swapped');
        self::assertSame(6, $data['image']['height']);
    }

    public function testRotateRejectsNonMultipleOf90(): void
    {
        $fileName = 'zz-rotate-invalid-'.uniqid().'.jpg';
        $this->createRealImageMedia($fileName, 6, 3);

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, ['rotate' => 45]);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRotateRejectsNonImage(): void
    {
        $fileName = 'zz-rotate-'.uniqid().'.pdf';
        $this->createMedia($fileName, 'A document', 'application/pdf');

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, ['rotate' => 90]);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function createRealImageMedia(string $fileName, int $width, int $height): Media
    {
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $gd = imagecreatetruecolor($width, $height);
        self::assertNotFalse($gd);
        imagejpeg($gd, $mediaDir.'/'.$fileName);

        $hash = sha1_file($mediaDir.'/'.$fileName, true);
        self::assertNotFalse($hash);

        $media = new Media();
        $media->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn($mediaDir)
            ->setFileName($fileName)
            ->setAlt('rotation fixture')
            ->setMimeType('image/jpeg')
            ->setDimensions([$width, $height])
            ->setHash($hash);
        $this->em->persist($media);
        $this->em->flush();
        $this->createdMediaFileNames[] = $fileName;

        return $media;
    }

    private function createMedia(string $fileName, string $alt, string $mimeType = 'image/jpeg'): Media
    {
        $media = new Media();
        $media->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn(self::getContainer()->getParameter('pw.media_dir'))
            ->setFileName($fileName)
            ->setAlt($alt)
            ->setMimeType($mimeType)
            ->setSize(1)
            ->setHash(sha1($fileName, true));
        $this->em->persist($media);
        $this->em->flush();
        $this->createdMediaFileNames[] = $fileName;

        return $media;
    }

    private function request(string $method, string $url): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken];
        $this->client->request($method, $url, [], [], $server);

        return $this->client->getResponse();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requestJson(string $method, string $url, array $body): Response
    {
        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
            'CONTENT_TYPE' => 'application/json',
        ];
        $this->client->request($method, $url, [], [], $server, (string) json_encode($body));

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
