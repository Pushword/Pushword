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
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

        $links = $this->clicks->byUrl($campaign);
        usort($links, static fn (array $first, array $second): int => $second['contacts'] <=> $first['contacts'] ?: $second['clicks'] <=> $first['clicks'] ?: strcmp($first['url'], $second['url']));
        foreach ($links as $index => $link) {
            $links[$index]['host'] = parse_url($link['url'], \PHP_URL_HOST) ?: '';
            $links[$index]['path'] = parse_url($link['url'], \PHP_URL_PATH) ?: '/';
        }

        return $this->render('@PushwordNewsletter/admin/campaign_statistics.html.twig', [
            'newsletterSection' => 'statistics',
            'campaign' => $data,
            'links' => $links,
            'maxLinkContacts' => [] === $links ? 1 : max(array_column($links, 'contacts')),
            'trackingAvailable' => $data['tracking'] || $data['clicks'] > 0,
            'backFilters' => array_intersect_key($request->query->all(), array_flip(['from', 'to', 'audience'])),
            'timezone' => $this->timezone->timezone->getName(),
        ]);
    }

    #[AdminRoute(path: '/newsletter/statistics/export', name: 'newsletter_statistics_export', options: ['methods' => ['GET']])]
    public function export(Request $request): Response
    {
        $form = $this->filterForm($request);
        if (! $form->isValid()) {
            return $this->dashboard($form);
        }

        [$from, $until, $audienceId] = $this->selection($form);
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

    /** @return FormInterface<array{from: string|null, to: string|null, audience: Audience|null}> */
    private function filterForm(Request $request): FormInterface
    {
        $today = new DateTimeImmutable('today', $this->timezone->timezone);
        $defaults = ['from' => $today->modify('-29 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d'), 'audience' => ''];
        $dateOptions = [
            'trim' => false,
            'invalid_message' => $this->translator->trans('newsletter.statistics.invalidDate'),
            'constraints' => [
                new NotBlank(message: $this->translator->trans('newsletter.statistics.requiredDate')),
                new Date(message: $this->translator->trans('newsletter.statistics.invalidDate')),
            ],
        ];
        /** @var FormInterface<array{from: string|null, to: string|null, audience: Audience|null}> $form */
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
            ->getForm();
        $form->submit(array_replace($defaults, array_intersect_key($request->query->all(), $defaults)));
        if ($form->isValid()) {
            /** @var array{from: string, to: string, audience: Audience|null} $data */
            $data = $form->getData();
            if ($data['from'] > $data['to']) {
                $form->get('to')->addError(new FormError($this->translator->trans('newsletter.statistics.invalidRange')));
            }
        }

        return $form;
    }

    /**
     * @param FormInterface<array{from: string|null, to: string|null, audience: Audience|null}> $form
     *
     * @return array{DateTimeImmutable, DateTimeImmutable, int|null}
     */
    private function selection(FormInterface $form): array
    {
        /** @var array{from: string, to: string, audience: Audience|null} $data */
        $data = $form->getData();
        $from = new DateTimeImmutable($data['from'], $this->timezone->timezone);
        $to = new DateTimeImmutable($data['to'], $this->timezone->timezone);
        $serverTimezone = new DateTimeZone(date_default_timezone_get());

        return [$from->setTimezone($serverTimezone), $to->modify('+1 day')->setTimezone($serverTimezone), $data['audience']?->id];
    }

    /** @param FormInterface<array{from: string|null, to: string|null, audience: Audience|null}> $form */
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
            'timezone' => $this->timezone->timezone->getName(),
        ];
        if (! $form->isValid()) {
            return $this->render('@PushwordNewsletter/admin/statistics.html.twig', $context, new Response(status: Response::HTTP_BAD_REQUEST));
        }

        [$from, $until, $audienceId] = $this->selection($form);
        $campaigns = $this->statistics->campaigns($from, $until, $audienceId);
        /** @var array{from: string, to: string, audience: Audience|null} $data */
        $data = $form->getData();

        return $this->render('@PushwordNewsletter/admin/statistics.html.twig', [
            ...$context,
            'audience' => $data['audience'],
            'activePreset' => array_search(['from' => $data['from'], 'to' => $data['to']], $presets, true),
            'fromDate' => $from,
            'toDate' => $until->setTimezone($this->timezone->timezone)->modify('-1 day'),
            'filterContext' => ['from' => $data['from'], 'to' => $data['to'], 'audience' => $audienceId],
            'campaigns' => $campaigns,
            'totals' => $this->statistics->totals($campaigns),
            'trackingAvailable' => array_any($campaigns, static fn (array $campaign): bool => $campaign['tracking'] || $campaign['clicks'] > 0),
            'contacts' => $this->statistics->contacts($audienceId),
            'activity' => $this->statistics->subscriptionActivity($from, $until, $audienceId),
        ]);
    }
}
