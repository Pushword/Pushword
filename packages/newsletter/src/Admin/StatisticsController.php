<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use DateTimeImmutable;
use DateTimeZone;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ClickEventRepository;
use Pushword\Newsletter\Repository\StatisticsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_EDITOR')]
final class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly StatisticsRepository $statistics,
        private readonly AudienceRepository $audiences,
        private readonly ClickEventRepository $clicks,
        private readonly EditorialTimezone $timezone,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(path: '/newsletter/statistics', name: 'newsletter_statistics', options: ['methods' => ['GET']])]
    public function index(Request $request): Response
    {
        [$from, $until, $audienceId] = $this->filters($request);
        $campaigns = $this->statistics->campaigns($from, $until, $audienceId);

        return $this->render('@PushwordNewsletter/admin/statistics.html.twig', [
            'newsletterSection' => 'statistics',
            'from' => $this->timezone->format($from, 'Y-m-d'),
            'to' => $until->setTimezone($this->timezone->timezone)->modify('-1 day')->format('Y-m-d'),
            'audienceId' => $audienceId,
            'audiences' => $this->audiences->findBy([], ['name' => 'ASC']),
            'campaigns' => $campaigns,
            'totals' => $this->statistics->totals($campaigns),
            'contacts' => $this->statistics->contacts($audienceId),
            'activity' => $this->statistics->subscriptionActivity($from, $until, $audienceId),
            'timezone' => $this->timezone->timezone->getName(),
        ]);
    }

    #[AdminRoute(path: '/newsletter/statistics/campaign/{id}', name: 'newsletter_statistics_campaign', options: ['methods' => ['GET']])]
    public function campaign(Request $request, Campaign $campaign): Response
    {
        $data = $this->statistics->campaign($campaign->id ?? throw $this->createNotFoundException());
        if (null === $data) {
            throw $this->createNotFoundException();
        }

        return $this->render('@PushwordNewsletter/admin/campaign_statistics.html.twig', [
            'newsletterSection' => 'statistics',
            'campaign' => $data,
            'links' => $this->clicks->byUrl($campaign),
            'backFilters' => array_intersect_key($request->query->all(), array_flip(['from', 'to', 'audience'])),
            'timezone' => $this->timezone->timezone->getName(),
        ]);
    }

    #[AdminRoute(path: '/newsletter/statistics/export', name: 'newsletter_statistics_export', options: ['methods' => ['GET']])]
    public function export(Request $request): Response
    {
        [$from, $until, $audienceId] = $this->filters($request);
        $columns = ['date', 'subject', 'audience', 'status', 'recipients', 'sent', 'pending', 'skipped', 'failed', 'bounced', 'unsubscribed', 'clicks', 'clickers', 'clickRate', 'bounceRate', 'unsubscribeRate'];
        $headers = array_map(fn (string $column): string => $this->translator->trans('newsletter.statistics.'.$column).(str_ends_with($column, 'Rate') ? ' (%)' : ''), $columns);
        $rows = [];
        foreach ($this->statistics->campaigns($from, $until, $audienceId) as $campaign) {
            $row = [];
            foreach ($columns as $column) {
                $value = $campaign[$column];
                if ('date' === $column && \is_string($value)) {
                    $value = $this->timezone->format(new DateTimeImmutable($value));
                }

                if (\in_array($column, ['clicks', 'clickers'], true) && ! $campaign['tracking'] && 0 === $campaign['clicks']) {
                    $value = null;
                }

                $row[] = $value;
            }

            $rows[] = array_combine($headers, $row);
        }

        $csv = new CsvEncoder()->encode($rows, 'csv', [
            CsvEncoder::HEADERS_KEY => $headers,
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
            CsvEncoder::OUTPUT_UTF8_BOM_KEY => true,
        ]);

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'newsletter-statistics.csv'),
        ]);
    }

    /** @return array{DateTimeImmutable, DateTimeImmutable, int|null} */
    private function filters(Request $request): array
    {
        $today = new DateTimeImmutable('today', $this->timezone->timezone);
        $from = $this->date($request->query->getString('from', $today->modify('-29 days')->format('Y-m-d')));
        $to = $this->date($request->query->getString('to', $today->format('Y-m-d')));
        if ($from > $to) {
            throw new BadRequestHttpException('The start date must not be after the end date.');
        }

        $audience = $request->query->getString('audience');
        $audienceId = null;
        if ('' !== $audience) {
            $audienceId = filter_var($audience, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (false === $audienceId || null === $this->audiences->find($audienceId)) {
                throw new BadRequestHttpException('Unknown audience.');
            }
        }

        $serverTimezone = new DateTimeZone(date_default_timezone_get());

        return [$from->setTimezone($serverTimezone), $to->modify('+1 day')->setTimezone($serverTimezone), $audienceId];
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone->timezone);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException('Expected a valid date in YYYY-MM-DD format.');
        }

        return $date;
    }
}
