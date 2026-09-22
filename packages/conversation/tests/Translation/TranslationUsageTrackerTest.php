<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Translation;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Conversation\Entity\TranslationUsage;
use Pushword\Conversation\Translation\TranslationUsageTracker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TranslationUsageTrackerTest extends KernelTestCase
{
    public function testUsageSurvivesTheWorkerServiceResetBetweenRequests(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $service = 'worker-reset-'.bin2hex(random_bytes(8));

        $container->get(TranslationUsageTracker::class)->addUsage($service, 5);

        // FrankenPHP performs this reset after every request. Doctrine detaches the
        // managed usage row, so the tracker must not keep returning that stale entity.
        $container->get('services_resetter')->reset();

        $container->get(TranslationUsageTracker::class)->addUsage($service, 7);

        $entityManager = $container->get(EntityManagerInterface::class);
        $usage = $entityManager->getRepository(TranslationUsage::class)->findOneBy([
            'service' => $service,
            'month' => new DateTimeImmutable()->format('Y-m'),
        ]);

        self::assertInstanceOf(TranslationUsage::class, $usage);
        self::assertSame(12, $usage->characterCount);

        $entityManager->remove($usage);
        $entityManager->flush();
    }
}
