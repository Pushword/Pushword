<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Newsletter\Entity\Campaign;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class CampaignCalendarController extends AbstractController
{
    #[AdminRoute(path: '/newsletter/calendar/{id}/date', name: 'newsletter_campaign_calendar_date', options: ['methods' => ['POST']])]
    public function move(int $id, Request $request, EntityManagerInterface $entityManager, EditorialTimezone $timezone): JsonResponse
    {
        if (! $this->isCsrfTokenValid('newsletter_calendar', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $campaign = $entityManager->find(Campaign::class, $id) ?? throw $this->createNotFoundException();
        if (! $campaign->isDraft() || null !== $campaign->automation) {
            return $this->json(['error' => 'readonly'], 409);
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $request->request->getString('date'));
        if (false === $date || false !== DateTimeImmutable::getLastErrors()) {
            return $this->json(['error' => 'invalidDate'], 422);
        }

        try {
            $entityManager->lock($campaign, LockMode::OPTIMISTIC, $request->request->getInt('version'));
            $campaign->scheduledAt = DateTimeImmutable::createFromMutable($timezone->parse($date->format(DateTimeInterface::ATOM)));
            $entityManager->flush();
        } catch (OptimisticLockException) {
            return $this->json(['error' => 'conflict'], 409);
        }

        return $this->json(['date' => $campaign->scheduledAt->format(DateTimeInterface::ATOM), 'version' => $campaign->version]);
    }
}
