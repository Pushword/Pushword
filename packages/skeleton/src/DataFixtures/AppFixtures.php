<?php

namespace App\DataFixtures;

use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Entity\EntityClassRegistry;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Repurpose\Entity\SocialPost;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Service\Attribute\Required;

class AppFixtures extends Fixture
{
    private ParameterBagInterface $params;

    private SiteRegistry $apps;

    #[Required]
    public function setParameterBag(ParameterBagInterface $params): void
    {
        $this->params = $params;
    }

    #[Required]
    public function setApps(SiteRegistry $apps): void
    {
        $this->apps = $apps;
    }

    public function load(ObjectManager $manager): void
    {
        $userClass = EntityClassRegistry::getUserClass();
        $user = new $userClass();
        $user->email = 'contact@piedweb.com';
        $user->username = 'John Doe';
        $user->setRoles([$userClass::ROLE_DEFAULT]);

        $manager->persist($user);

        $medias = [
            'Pied Web Logo' => ['file' => 'piedweb-logo.png', 'mime' => 'image/png'],
            'Demo 1' => ['file' => '1.jpg', 'mime' => 'image/jpeg'],
            'Demo 2' => ['file' => '2.jpg', 'mime' => 'image/jpeg'],
            'Demo 3' => ['file' => '3.jpg', 'mime' => 'image/jpeg'],
            'SVG Logo' => ['file' => 'logo.svg', 'mime' => 'image/svg+xml'],
        ];
        $media = [];
        foreach ($medias as $name => $data) {
            $media[$name] = new Media()
            ->setProjectDir($this->params->get('kernel.project_dir'))
            ->setStoreIn($this->params->get('pw.media_dir'))
            ->setMimeType($data['mime'])
            ->setSize(2)
            ->setDimensions([1000, 1000])
            ->setFileName($data['file'])
            ->setAlt($name)
            ->setHash();

            $manager->persist($media[$name]);
        }

        $manager->flush();

        // Store the final content for version history
        $finalContent = (string) file_get_contents(__DIR__.'/WelcomePage.md');

        // VERSION 1: Create homepage with minimal initial content
        $homepage = new Page();
        $homepage->setH1('Welcome to Pushword');
        $homepage->setSlug('homepage1');
        $homepage->setMainImage($media['Demo 2']);
        $homepage->locale = 'en';
        $homepage->createdAt = new DateTime('2 days ago');
        $homepage->updatedAt = new DateTime('2 days ago');
        $homepage->setMainContent("# Welcome\n\nThis is the initial version of the homepage.");
        $homepage->setCustomProperty('mainImageFormat', 1);

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $homepage->host = 'localhost.dev';
        }

        $manager->persist($homepage);
        $manager->flush();

        // VERSION 2: Add more content
        $homepage->setSlug('homepage2');
        $homepage->setMainImage($media['Demo 1']);
        $homepage->setMainContent("# Welcome to Pushword\n\nThis is version 2 with more content.\n\n## Features\n- Fast\n- Flexible");
        $homepage->updatedAt = new DateTime('1 day ago');

        $manager->flush();

        // VERSION 3 (final): Set the real homepage content
        $homepage->setH1('Welcome to Pushword !');
        $homepage->setSlug('homepage');
        $homepage->setMainImage($media['Demo 2']);
        $homepage->setMainContent($finalContent);
        $homepage->updatedAt = new DateTime('now');

        $manager->flush();

        $ksPage = new Page();
        $ksPage->setH1('Demo Page - Kitchen Sink  Markdown + Twig');
        $ksPage->setSlug('kitchen-sink');
        $ksPage->setMainImage($media['Demo 1']);
        $ksPage->locale = 'en';
        $ksPage->setParentPage($homepage);
        $ksPage->createdAt = new DateTime('1 day ago');
        $ksPage->updatedAt = new DateTime('1 day ago');
        $ksPage->setMainContent((string) file_get_contents(__DIR__.'/KitchenSink.md'));

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $ksPage->host = 'localhost.dev';
        }

        $manager->persist($ksPage);

        // Quiz block demo: a client-side QCM with an end-of-quiz conversion form.
        $quizPage = new Page();
        $quizPage->setH1('Quiz — Montagnes du monde');
        $quizPage->setTitle('Quiz — Montagnes du monde | Demo');
        $quizPage->setSlug('quiz-montagnes');
        $quizPage->setMainImage($media['Demo 3']);
        $quizPage->locale = 'fr';
        $quizPage->setParentPage($homepage);
        $quizPage->createdAt = new DateTime('1 day ago');
        $quizPage->updatedAt = new DateTime('1 day ago');
        $quizPage->setMainContent((string) file_get_contents(__DIR__.'/Quiz.md'));

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $quizPage->host = 'localhost.dev';
        }

        $manager->persist($quizPage);

        // Variant pages demo (localhost.dev): a master stay and a partner variant
        // that consolidates onto it (canonical → master, link rewriting, exclusions).
        if ('localhost.dev' === $this->apps->getMainHost()) {
            $variantMaster = new Page();
            $variantMaster->setH1('Mountain Lodge — 3-night stay');
            $variantMaster->setTitle('Mountain Lodge — 3-night stay | Variant pages demo');
            $variantMaster->setSlug('demo-variant-master');
            $variantMaster->locale = 'en';
            $variantMaster->host = 'localhost.dev';
            $variantMaster->createdAt = new DateTime('1 day ago');
            $variantMaster->updatedAt = new DateTime('1 day ago');
            $variantMaster->setTags('mountain-lodge');
            $variantMaster->setMainContent(
                "This is the **master** page for this stay — the one search engines index.\n\n"
                .'Several partners resell the same stay with their own wording. See '
                ."[Partner B's version](/demo-variant-partner): it is a **variant** of this page. "
                .'In the HTML source that link is rewritten to point here (the master), keeping the '
                .'variant URL on a `data-variant` hook — so crawlers and no-JS visitors consolidate onto the master.'
            );

            $variantPartner = new Page();
            $variantPartner->setH1('Mountain Lodge getaway, curated by Partner B');
            $variantPartner->setTitle('Mountain Lodge getaway — Partner B | Variant pages demo');
            $variantPartner->setSlug('demo-variant-partner');
            $variantPartner->locale = 'en';
            $variantPartner->host = 'localhost.dev';
            $variantPartner->createdAt = new DateTime('1 day ago');
            $variantPartner->updatedAt = new DateTime('1 day ago');
            $variantPartner->setTags('mountain-lodge');
            $variantPartner->setMainContent(
                "Partner B's own pitch for the **same** stay: different wording, identical product.\n\n"
                .'This page renders fully on its own URL but its canonical points to '
                .'[the master](/demo-variant-master), it emits no hreflang, and it is excluded from the '
                .'sitemap, the internal search and the menus. Use **Promote to master** in the admin to swap roles.'
            );
            $variantPartner->setVariantOf($variantMaster);

            $manager->persist($variantMaster);
            $manager->persist($variantPartner);
        }

        if (\in_array('admin-block-editor.test', $this->apps->getHosts(), true)) {
            $ksBlockPage = new Page();
            $ksBlockPage->setH1('Demo Page - Kitchen Sink Block');
            $ksBlockPage->setSlug('kitchen-sink');
            $ksBlockPage->setMainImage($media['Demo 1']);
            $ksBlockPage->locale = 'en';
            $ksBlockPage->setParentPage($homepage);
            $ksBlockPage->createdAt = new DateTime('1 day ago');
            $ksBlockPage->updatedAt = new DateTime('1 day ago');
            $ksBlockPage->setMainContent((string) file_get_contents(__DIR__.'/KitchenSink.md'));
            $ksBlockPage->host = 'admin-block-editor.test';

            $manager->persist($ksBlockPage);
        }

        $redirectionPage = new Page();
        $redirectionPage->setH1('Redirection');
        $redirectionPage->setSlug('pushword');
        $redirectionPage->locale = 'en';
        $redirectionPage->createdAt = new DateTime('1 day ago');
        $redirectionPage->updatedAt = new DateTime('1 day ago');
        $redirectionPage->setMainContent('Location: https://pushword.piedweb.com');

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $redirectionPage->host = 'localhost.dev';
        }

        $manager->persist($redirectionPage);

        // French page for localhost.dev to test multilingual menu
        $homepageFr = new Page();
        $homepageFr->setH1('Bienvenue sur Pushword !');
        $homepageFr->setSlug('fr/homepage');
        $homepageFr->setMainImage($media['Demo 3']);
        $homepageFr->locale = 'fr';
        $homepageFr->createdAt = new DateTime('2 days ago');
        $homepageFr->updatedAt = new DateTime('2 days ago');
        $homepageFr->setMainContent('Ceci est la page d\'accueil en français.');

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $homepageFr->host = 'localhost.dev';
        }

        $manager->persist($homepageFr);

        // Canadian French page to test locale with region code (fr-CA)
        $homepageFrCa = new Page();
        $homepageFrCa->setH1('Bienvenue sur Pushword !');
        $homepageFrCa->setSlug('fr-ca/homepage');
        $homepageFrCa->setMainImage($media['Demo 3']);
        $homepageFrCa->locale = 'fr-CA';
        $homepageFrCa->createdAt = new DateTime('2 days ago');
        $homepageFrCa->updatedAt = new DateTime('2 days ago');
        $homepageFrCa->setMainContent('Ceci est la page d\'accueil en français canadien.');

        if ('localhost.dev' === $this->apps->getMainHost()) {
            $homepageFrCa->host = 'localhost.dev';
        }

        $manager->persist($homepageFrCa);

        // Set up translations between homepage variants
        $homepage->addTranslation($homepageFr);
        $homepage->addTranslation($homepageFrCa);

        $manager->flush();

        if ('localhost.dev' === $this->apps->getMainHost() && class_exists(Message::class)) {
            $message = new Message();
            $message->setContent('This is a default conversation message for localhost.dev. You can use this to test the conversation features.');
            $message->setAuthorName('Demo User');
            $message->setAuthorEmail('demo@localhost.dev');
            $message->host = 'localhost.dev';
            $message->setPublishedAt(new DateTime('1 day ago'));

            $manager->persist($message);

            // Create reviews from YAML file
            /** @var array<array{title: string, content: string, authorName: string, authorEmail: string, rating: int, publishedAt: string, reply?: string, replyAuthor?: string, media?: list<string>}>[] $reviewsData */
            $reviewsData = Yaml::parseFile(__DIR__.'/reviews.yaml');

            foreach ($reviewsData['reviews'] as $reviewData) {
                $review = new Review();
                $review->setTitle($reviewData['title']);
                $review->setContent($reviewData['content']);
                $review->setAuthorName($reviewData['authorName']);
                $review->setAuthorEmail($reviewData['authorEmail']);
                $review->setRating($reviewData['rating']);
                $review->setReply($reviewData['reply'] ?? null);
                $review->setReplyAuthor($reviewData['replyAuthor'] ?? null);
                $review->host = 'localhost.dev';
                $review->setPublishedAt(new DateTime($reviewData['publishedAt']));
                $review->setTags('kitchen-sink');

                foreach ($reviewData['media'] ?? [] as $mediaName) {
                    if (isset($media[$mediaName])) {
                        $review->addMedia($media[$mediaName]);
                    }
                }

                $manager->persist($review);
            }

            $manager->flush();
        }

        $this->loadRepurposeDemo($manager);
    }

    /**
     * A demo social carousel repurposing the homepage, visible in the admin at
     * /admin/repurpose and previewed in the studio.
     */
    private function loadRepurposeDemo(ObjectManager $manager): void
    {
        if (! class_exists(SocialPost::class)) {
            return;
        }

        $spec = [
            'page' => 'homepage',
            'network' => 'linkedin',
            'format' => 'linkedin-4-5',
            'status' => 'draft',
            'fontPairing' => 'playfair-chivo',
            'palette' => ['bg' => '#0b1120', 'text' => '#f8fafc', 'accent' => '#38bdf8'],
            'counter' => ['style' => 'dots', 'align' => 'right'],
            'creator' => 'robin',
            'creatorOnSlides' => 'intro-outro',
            'caption' => 'Turn any article into a scroll-stopping carousel — rendered server-side, pixel-exact.',
            'hashtags' => ['pushword', 'contentmarketing', 'carousel'],
            'slides' => [
                ['layout' => 'bottom', 'align' => 'left', 'tagline' => 'Repurpose', 'title' => 'Turn your article into a scroll-stopping carousel', 'paragraph' => 'Server-side SVG, pixel-exact text, focal-point crops.', 'swipe' => true, 'background' => 'blobs', 'overlay' => 0.45, 'image' => ['media' => '1.jpg', 'focusX' => 0.5, 'focusY' => 0.35, 'zoom' => 1.1]],
                ['layout' => 'center', 'align' => 'center', 'title' => 'One spec, every network', 'paragraph' => 'LinkedIn, Instagram, Pinterest — the same JSON, re-cropped per format.', 'background' => 'blobs'],
                ['layout' => 'bottom', 'align' => 'left', 'tagline' => 'Your move', 'title' => 'Draft, validate, export', 'paragraph' => 'An agent writes it, you nudge it, the studio exports the PNGs.', 'background' => 'blobs', 'overlay' => 0.5, 'image' => ['media' => '2.jpg', 'focusX' => 0.5, 'focusY' => 0.5, 'zoom' => 1.0]],
            ],
        ];

        $post = new SocialPost();
        $post->host = 'localhost.dev';
        $post->setSpec($spec);

        $manager->persist($post);
        $manager->flush();
    }
}
