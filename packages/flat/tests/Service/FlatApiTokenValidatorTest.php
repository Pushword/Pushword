<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Pushword\Core\Entity\User;
use Pushword\Flat\Service\FlatApiTokenValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class FlatApiTokenValidatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private FlatApiTokenValidator $validator;

    private ?User $testUser = null;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        $validator = self::getContainer()->get(FlatApiTokenValidator::class);
        \assert($validator instanceof FlatApiTokenValidator);
        $this->validator = $validator;
    }

    #[Override]
    protected function tearDown(): void
    {
        if (null !== $this->testUser) {
            $this->em->remove($this->testUser);
            $this->em->flush();
            $this->testUser = null;
        }

        parent::tearDown();
    }

    public function testValidateTokenReturnsNullForEmptyToken(): void
    {
        $result = $this->validator->validateToken('');

        self::assertNull($result);
    }

    public function testValidateTokenReturnsNullForInvalidToken(): void
    {
        $result = $this->validator->validateToken('invalid-token-that-does-not-exist');

        self::assertNull($result);
    }

    public function testValidateTokenReturnsUserForValidToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->testUser = $this->createUserWithToken($token);

        $result = $this->validator->validateToken($token);

        self::assertNotNull($result);
        self::assertNotNull($this->testUser);
        self::assertSame($this->testUser->id, $result->id);
    }

    public function testExtractTokenFromRequestReturnsNullWithoutHeader(): void
    {
        $request = Request::create('/api/test');

        $result = $this->validator->extractTokenFromRequest($request);

        self::assertNull($result);
    }

    public function testExtractTokenFromRequestReturnsNullWithInvalidHeader(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        $result = $this->validator->extractTokenFromRequest($request);

        self::assertNull($result);
    }

    public function testExtractTokenFromRequestReturnsBearerToken(): void
    {
        $token = 'my-test-token-12345';
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer '.$token);

        $result = $this->validator->extractTokenFromRequest($request);

        self::assertSame($token, $result);
    }

    public function testValidateRequestReturnsNullWithoutAuthorization(): void
    {
        $request = Request::create('/api/test');

        $result = $this->validator->validateRequest($request);

        self::assertNull($result);
    }

    public function testValidateRequestReturnsNullWithInvalidToken(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $result = $this->validator->validateRequest($request);

        self::assertNull($result);
    }

    public function testValidateRequestReturnsUserWithValidToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->testUser = $this->createUserWithToken($token);

        $request = Request::create('/api/test');
        $request->headers->set('Authorization', 'Bearer '.$token);

        $result = $this->validator->validateRequest($request);

        self::assertNotNull($result);
        self::assertNotNull($this->testUser);
        self::assertSame($this->testUser->id, $result->id);
    }

    private function createUserWithToken(string $token): User
    {
        $user = new User();
        $user->email = 'test-api-'.uniqid().'@example.com';
        $user->setPassword('hashed-password');
        $user->apiToken = $token;

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
