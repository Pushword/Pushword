<?php

namespace Pushword\Conversation\Translation;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Conversation\Entity\TranslationUsage;

final class TranslationUsageTracker
{
    /** @var array<string, TranslationUsage> */
    private array $cache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getCurrentMonthUsage(string $service): int
    {
        return $this->getOrCreateUsage($service)->characterCount;
    }

    public function addUsage(string $service, int $characterCount): void
    {
        $usage = $this->getOrCreateUsage($service);
        $usage->addCharacters($characterCount);

        $this->entityManager->persist($usage);
        $this->entityManager->flush();
    }

    public function isWithinLimit(string $service, int $limit): bool
    {
        if (0 === $limit) {
            return true; // Unlimited
        }

        return $this->getCurrentMonthUsage($service) < $limit;
    }

    public function getRemainingCharacters(string $service, int $limit): int
    {
        if (0 === $limit) {
            return \PHP_INT_MAX;
        }

        return max(0, $limit - $this->getCurrentMonthUsage($service));
    }

    private function getOrCreateUsage(string $service): TranslationUsage
    {
        $currentMonth = new DateTimeImmutable()->format('Y-m');
        $cacheKey = $service.'_'.$currentMonth;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $repository = $this->entityManager->getRepository(TranslationUsage::class);
        $usage = $repository->findOneBy([
            'service' => $service,
            'month' => $currentMonth,
        ]);

        if (null === $usage) {
            $usage = new TranslationUsage();
            $usage->service = $service;
            $usage->month = $currentMonth;
            $this->entityManager->persist($usage);
            $this->entityManager->flush();
        }

        $this->cache[$cacheKey] = $usage;

        return $usage;
    }
}
