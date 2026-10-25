<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use DateTimeImmutable;
use DateTimeZone;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ClickEventRepository;
use Pushword\Newsletter\Repository\StatisticsRepository;
use Pushword\Newsletter\Statistics\LocalStatisticsProvider;
use Pushword\Newsletter\Statistics\Report;
use Pushword\Newsletter\Statistics\ReportQuery;
use Pushword\Newsletter\Statistics\StatisticsDashboard;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
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
        private readonly StatisticsDashboard $dashboard,
        private readonly LocalStatisticsProvider $localStatistics,
    ) {
    }

    #[AdminRoute(path: '/newsletter/statistics', name: 'newsletter_statistics', options: ['methods' => ['GET']])]
    public function index(Request $request): Response
    {
        return $this->dashboard($this->filterForm($request));
    }

    #[AdminRoute(path: '/newsletter/statistics/campaign/{id}', name: 'newsletter_statistics_campaign', options: ['methods' => ['GET']])]
    public function campaign(Request $request, Campaign $campaign): Response
    {
        $data = $this->statistics->campaign($campaign->id ?? throw $this->createNotFoundException());
        if (null === $data) {
            throw $this->createNotFoundException();
        }

        $query = $this->reportQuery($this->filterForm($request));
        $report = $this->localStatistics->campaign($campaign->id, $query);
        $comparisons = [];
        foreach ([7, 30] as $days) {
            $comparisons[$days] = $this->localStatistics->campaign($campaign->id, new ReportQuery($query->from, $query->until, $query->audienceId, $query->basis, $days, $query->groupBy, $query->asOf));
        }

        $links = $this->clicks->byUrl($campaign, $query);
        usort($links, static fn (array $first, array $second): int => $second['contacts'] <=> $first['contacts'] ?: $second['clicks'] <=> $first['clicks'] ?: strcmp($first['url'], $second['url']));
        foreach ($links as $index => $link) {
            $links[$index]['host'] = parse_url($link['url'], \PHP_URL_HOST) ?: '';
            $links[$index]['path'] = parse_url($link['url'], \PHP_URL_PATH) ?: '/';
        }

        return $this->render('@PushwordNewsletter/admin/campaign_statistics.html.twig', [
            'newsletterSection' => 'statistics',
            'campaign' => $data,
            'report' => $report,
            'comparisons' => $comparisons,
            'links' => $links,
            'maxLinkContacts' => [] === $links ? 1 : max(array_column($links, 'contacts')),
            'trackingAvailable' => $data['tracking'] || $data['clicks'] > 0,
            'backFilters' => array_intersect_key($request->query->all(), array_flip(['from', 'to', 'audience', 'basis', 'window', 'groupBy'])),
            'timezone' => $this->timezone->timezone->getName(),
        ]);
    }

    #[AdminRoute(path: '/newsletter/statistics/report/{source}/{reportId}', name: 'newsletter_statistics_report', options: ['methods' => ['GET']])]
    public function report(Request $request, string $source, string $reportId): Response
    {
        $query = $this->reportQuery($this->filterForm($request));
        $selected = null;
        foreach ($this->dashboard->reports($query) as $report) {
            if ($report->source === $source && $report->id === $reportId) {
                $selected = $report;

                break;
            }
        }

        if (null === $selected) {
            throw $this->createNotFoundException();
        }

        $comparisons = [];
        foreach ([7, 30] as $days) {
            $window = new ReportQuery($query->from, $query->until, $query->audienceId, $query->basis, $days, $query->groupBy, $query->asOf);
            foreach ($this->dashboard->reports($window) as $report) {
                if ($report->source === $source && $report->id === $reportId) {
                    $comparisons[$days] = $report;
                }
            }
        }

        return $this->render('@PushwordNewsletter/admin/source_statistics.html.twig', [
            'newsletterSection' => 'statistics',
            'report' => $selected,
            'comparisons' => $comparisons,
            'backFilters' => $request->query->all(),
        ]);
    }

    #[AdminRoute(path: '/newsletter/statistics/export', name: 'newsletter_statistics_export', options: ['methods' => ['GET']])]
    public function export(Request $request): Response
    {
        $form = $this->filterForm($request);
        if (! $form->isValid()) {
            return $this->dashboard($form);
        }

        $query = $this->reportQuery($form);
        $rates = ['clickRate', 'filteredClickRate', 'coverage', 'deliveryRate', 'bounceRate', 'unsubscribeRate'];
        $headers = ['source', 'sourceId', 'managementUrl', 'subject', 'kind', 'date', 'basis', 'window', 'groupBy', 'from', 'until', 'asOf'];
        foreach (Report::METRICS as $name) {
            $headers[] = $name;
            $headers[] = $name.'State';
        }

        foreach ($rates as $name) {
            $headers[] = $name;
            $headers[] = $name.'Numerator';
            $headers[] = $name.'Denominator';
            $headers[] = $name.'State';
        }

        $headers[] = 'qualificationMethod';
        $headers[] = 'trackingScope';
        $headers[] = 'mature';
        $rows = [];
        foreach ($this->dashboard->reports($query) as $report) {
            $row = [
                'source' => $report->source,
                'sourceId' => $report->id,
                'managementUrl' => $report->managementUrl,
                'subject' => $report->label,
                'kind' => $report->kind,
                'date' => $report->date?->format(\DATE_ATOM),
                'basis' => $query->basis,
                'window' => $query->windowDays ?? 'lifetime',
                'groupBy' => $query->groupBy,
                'from' => $query->from->format(\DATE_ATOM),
                'until' => $query->until->format(\DATE_ATOM),
                'asOf' => $query->asOf->format(\DATE_ATOM),
            ];
            foreach (Report::METRICS as $name) {
                $row[$name] = $report->metric($name)->value;
                $row[$name.'State'] = $report->metric($name)->state;
            }

            foreach ($rates as $name) {
                $rate = $report->rate($name);
                $row[$name] = $rate['value'];
                $row[$name.'Numerator'] = $rate['numerator']->value;
                $row[$name.'Denominator'] = $rate['denominator']->value;
                $row[$name.'State'] = $rate['state'];
            }

            $row['qualificationMethod'] = $report->qualificationMethod;
            $row['trackingScope'] = $report->trackingScope;
            $row['mature'] = $report->mature ? 'yes' : 'no';
            $rows[] = $row;
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

    /** @return FormInterface<array{from: string|null, to: string|null, audience: Audience|null, basis: string, window: string|null, groupBy: string}> */
    private function filterForm(Request $request): FormInterface
    {
        $today = new DateTimeImmutable('today', $this->timezone->timezone);
        $defaults = ['from' => $today->modify('-29 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d'), 'audience' => '', 'basis' => 'sent', 'window' => '', 'groupBy' => 'campaign'];
        $dateOptions = [
            'trim' => false,
            'invalid_message' => $this->translator->trans('newsletter.statistics.invalidDate'),
            'constraints' => [
                new NotBlank(message: $this->translator->trans('newsletter.statistics.requiredDate')),
                new Date(message: $this->translator->trans('newsletter.statistics.invalidDate')),
            ],
        ];
        /** @var FormInterface<array{from: string|null, to: string|null, audience: Audience|null, basis: string, window: string|null, groupBy: string}> $form */
        $form = $this->container->get('form.factory')->createNamedBuilder('', FormType::class, null, [
            'method' => 'GET',
            'action' => $this->generateUrl('admin_newsletter_statistics'),
            'csrf_protection' => false,
        ])
            ->add('from', TextType::class, ['label' => 'newsletter.statistics.from', ...$dateOptions])
            ->add('to', TextType::class, ['label' => 'newsletter.statistics.to', ...$dateOptions])
            ->add('audience', EntityType::class, [
                'class' => Audience::class,
                'choices' => $this->audiences->findBy([], ['name' => 'ASC']),
                'choice_label' => 'name',
                'placeholder' => 'newsletter.statistics.allAudiences',
                'label' => 'newsletter.statistics.audience',
                'invalid_message' => $this->translator->trans('newsletter.statistics.invalidAudience'),
                'required' => false,
            ])
            ->add('basis', ChoiceType::class, ['label' => 'newsletter.statistics.basis', 'choices' => ['newsletter.statistics.sentPeriod' => 'sent', 'newsletter.statistics.eventPeriod' => 'events']])
            ->add('window', ChoiceType::class, ['label' => 'newsletter.statistics.window', 'choices' => ['newsletter.statistics.lifetime' => '', 'newsletter.statistics.day7' => '7', 'newsletter.statistics.day30' => '30'], 'required' => false, 'placeholder' => false])
            ->add('groupBy', ChoiceType::class, ['label' => 'newsletter.statistics.groupBy', 'choices' => ['newsletter.statistics.campaigns' => 'campaign', 'newsletter.statistics.automations' => 'automation', 'newsletter.statistics.steps' => 'step', 'newsletter.statistics.occurrences' => 'occurrence']])
            ->getForm();
        $form->submit(array_replace($defaults, array_intersect_key($request->query->all(), $defaults)));
        if ($form->isValid()) {
            /** @var array{from: string, to: string, audience: Audience|null, basis: string, window: string|null, groupBy: string} $data */
            $data = $form->getData();
            if ($data['from'] > $data['to']) {
                $form->get('to')->addError(new FormError($this->translator->trans('newsletter.statistics.invalidRange')));
            }
        }

        return $form;
    }

    /**
     * @param FormInterface<array{from: string|null, to: string|null, audience: Audience|null, basis: string, window: string|null, groupBy: string}> $form
     *
     * @return array{DateTimeImmutable, DateTimeImmutable, int|null}
     */
    private function selection(FormInterface $form): array
    {
        /** @var array{from: string, to: string, audience: Audience|null, basis: string, window: string|null, groupBy: string} $data */
        $data = $form->getData();
        $from = new DateTimeImmutable($data['from'], $this->timezone->timezone);
        $to = new DateTimeImmutable($data['to'], $this->timezone->timezone);
        $serverTimezone = new DateTimeZone(date_default_timezone_get());

        return [$from->setTimezone($serverTimezone), $to->modify('+1 day')->setTimezone($serverTimezone), $data['audience']?->id];
    }

    /** @param FormInterface<array{from: string|null, to: string|null, audience: Audience|null, basis: string, window: string|null, groupBy: string}> $form */
    private function reportQuery(FormInterface $form): ReportQuery
    {
        if (! $form->isValid()) {
            throw $this->createNotFoundException();
        }

        [$from, $until, $audienceId] = $this->selection($form);
        $data = $form->getData();

        return new ReportQuery($from, $until, $audienceId, $data['basis'], null === $data['window'] || '' === $data['window'] ? null : (int) $data['window'], $data['groupBy']);
    }

    /** @param FormInterface<array{from: string|null, to: string|null, audience: Audience|null, basis: string, window: string|null, groupBy: string}> $form */
    private function dashboard(FormInterface $form): Response
    {
        $today = new DateTimeImmutable('today', $this->timezone->timezone);
        $presets = [];
        foreach ([7, 30, 90] as $days) {
            $presets[$days] = ['from' => $today->modify('-'.($days - 1).' days')->format('Y-m-d'), 'to' => $today->format('Y-m-d')];
        }

        $audience = $form->get('audience')->getData();
        $context = [
            'newsletterSection' => 'statistics',
            'filters' => $form->createView(),
            'validFilters' => $form->isValid(),
            'presets' => $presets,
            'audienceId' => $audience instanceof Audience ? $audience->id : null,
            'activePreset' => null,
            'reportFilters' => ['basis' => $form->get('basis')->getData(), 'window' => $form->get('window')->getData(), 'groupBy' => $form->get('groupBy')->getData()],
            'timezone' => $this->timezone->timezone->getName(),
        ];
        if (! $form->isValid()) {
            return $this->render('@PushwordNewsletter/admin/statistics.html.twig', $context, new Response(status: Response::HTTP_BAD_REQUEST));
        }

        [$from, $until, $audienceId] = $this->selection($form);
        $reports = $this->dashboard->reports($this->reportQuery($form));
        /** @var array{from: string, to: string, audience: Audience|null, basis: string, window: string|null, groupBy: string} $data */
        $data = $form->getData();

        return $this->render('@PushwordNewsletter/admin/statistics.html.twig', [
            ...$context,
            'audience' => $data['audience'],
            'activePreset' => array_search(['from' => $data['from'], 'to' => $data['to']], $presets, true),
            'fromDate' => $from,
            'toDate' => $until->setTimezone($this->timezone->timezone)->modify('-1 day'),
            'filterContext' => ['from' => $data['from'], 'to' => $data['to'], 'audience' => $audienceId, 'basis' => $data['basis'], 'window' => $data['window'], 'groupBy' => $data['groupBy']],
            'reports' => $reports,
            'reportTotals' => $this->dashboard->totals($reports),
            'contacts' => $this->statistics->contacts($audienceId),
            'activity' => $this->statistics->subscriptionActivity($from, $until, $audienceId),
        ]);
    }
}
