<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Service;

use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Repurpose\Model\AudioMix;
use Pushword\Repurpose\Model\AudioTrack;
use Pushword\Repurpose\Service\AudioAssetResolver;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\FormatRegistry;
use Pushword\Repurpose\Service\MotionProjectExporter;
use Pushword\Repurpose\Service\SlideRenderer;
use Pushword\Repurpose\Service\VideoBuilder;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

#[Group('integration')]
final class AudioExportTest extends KernelTestCase
{
    private function resolver(): AudioAssetResolver
    {
        $storage = new MediaStorageAdapter(new Flysystem(new InMemoryFilesystemAdapter()), '/unused', false);
        $storage->write('voice.wav', $this->wav(4, 440, true));
        $storage->write('music.wav', $this->wav(0.5, 1000));

        $repository = self::createStub(MediaRepository::class);
        $repository->method('findOneByFileNameOrHistory')->willReturnCallback(static fn (string $name): ?Media => match ($name) {
            'voice.wav', 'old-voice.wav' => new Media()->setFileName('voice.wav')->setMimeType('audio/wav'),
            'music.wav', 'missing.wav' => new Media()->setFileName($name)->setMimeType('audio/wav'),
            'photo.mp3' => new Media()->setFileName($name)->setMimeType('image/jpeg'),
            'no-mime.wav' => new Media()->setFileName($name),
            default => null,
        });

        return new AudioAssetResolver($repository, $storage);
    }

    public function testResolverRejectsInvalidMissingAndNonAudioMedia(): void
    {
        $resolver = $this->resolver();
        self::assertSame($this->wav(4, 440, true), $resolver->read(new AudioTrack('old-voice.wav')));
        foreach (['../voice.wav', 'unknown.wav', 'missing.wav', 'photo.mp3', 'no-mime.wav'] as $filename) {
            try {
                $resolver->read(new AudioTrack($filename));
                self::fail('The audio resolver accepted '.$filename);
            } catch (RuntimeException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testMotionExportCopiesSeparateSourcesAndMixSettings(): void
    {
        self::bootKernel();
        $exporter = new MotionProjectExporter(self::getContainer()->get(SlideRenderer::class), new FormatRegistry(), $this->resolver());
        $spec = ['page' => 'x', 'network' => 'instagram', 'format' => 'story-9-16', 'slides' => [['title' => 'Audio']], 'audio' => [
            'voice' => ['media' => 'old-voice.wav', 'volume' => 0, 'fadeOut' => 0.5],
            'music' => ['media' => 'music.wav', 'fadeIn' => 1], 'ducking' => false,
        ]];
        $files = $exporter->files(new CarouselFactory()->fromArray($spec), $spec);
        self::assertSame($this->wav(4, 440, true), $files['public/audio/voice.wav']);
        self::assertSame($this->wav(0.5, 1000), $files['public/audio/music.wav']);
        $timeline = json_decode($files['timeline.json'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($timeline);
        self::assertIsArray($timeline['audio']);
        self::assertSame(['src' => 'audio/voice.wav', 'volume' => 0, 'fadeIn' => 0, 'fadeOut' => 0.5], $timeline['audio']['voice']);
        self::assertSame(['src' => 'audio/music.wav', 'volume' => 0.3, 'fadeIn' => 1, 'fadeOut' => 0], $timeline['audio']['music']);
        self::assertFalse($timeline['audio']['ducking']);
        self::assertSame($files, $exporter->files(new CarouselFactory()->fromArray($spec), $spec));
    }

    public function testEncodedAudioHasIndependentLevelsFadesLoopsAndSignalDrivenDucking(): void
    {
        $builder = new VideoBuilder(audioAssets: $this->resolver());
        if (! $builder->available()) {
            $this->expectException(RuntimeException::class);
            $builder->build(['PNG'], 64, 64, new AudioMix(music: new AudioTrack('music.wav')));

            return;
        }

        $music = new AudioTrack('music.wav', 0.5);
        $plain = $this->samples($builder, new AudioMix(music: $music));
        self::assertGreaterThanOrEqual(9 * 8000, \count($plain));
        $level = $this->tone($plain, 1.5, 1000);
        self::assertEqualsWithDelta(0.2, $level, 0.015);
        self::assertEqualsWithDelta($level, $this->tone($plain, 6, 1000), 0.01, 'Short music must loop to the video end.');

        $quiet = $this->samples($builder, new AudioMix(music: new AudioTrack('music.wav', 0.25)));
        self::assertEqualsWithDelta($level / 2, $this->tone($quiet, 1.5, 1000), 0.01);
        $muted = $this->samples($builder, new AudioMix(music: new AudioTrack('music.wav', 0)));
        self::assertLessThan(0.001, $this->tone($muted, 1.5, 1000));

        $faded = $this->samples($builder, new AudioMix(music: new AudioTrack('music.wav', 0.5, 1, 1)));
        self::assertLessThan($level / 2, $this->tone($faded, 0.1, 1000));
        self::assertLessThan($level / 2, $this->tone($faded, 8.7, 1000));
        self::assertEqualsWithDelta($level, $this->tone($faded, 5, 1000), 0.01);

        $voice = new AudioTrack('voice.wav');
        $ducked = $this->samples($builder, new AudioMix($voice, $music));
        $unDucked = $this->samples($builder, new AudioMix($voice, $music, false));
        self::assertLessThan($level / 3, $this->tone($ducked, 1.5, 1000), 'Music must duck while voice is audible.');
        self::assertEqualsWithDelta($level, $this->tone($unDucked, 1.5, 1000), 0.015);
        self::assertGreaterThan($level * 0.8, $this->tone($ducked, 2.7, 1000), 'Music must recover during a pause.');
        self::assertEqualsWithDelta($level, $this->tone($ducked, 6, 1000), 0.01);
        self::assertEqualsWithDelta($this->tone($unDucked, 1.5, 440), $this->tone($ducked, 1.5, 440), 0.015, 'Ducking must leave voice unchanged.');
        $mutedVoice = $this->samples($builder, new AudioMix(new AudioTrack('voice.wav', 0), $music));
        self::assertEqualsWithDelta($level, $this->tone($mutedVoice, 1.5, 1000), 0.01);
        $voiceOnly = $this->samples($builder, new AudioMix(new AudioTrack('voice.wav', 0.5, 2, 1)));
        self::assertLessThan(0.15, $this->tone($voiceOnly, 1.1, 440));
        self::assertLessThan(0.15, $this->tone($voiceOnly, 3.2, 440));
        self::assertLessThan(0.001, $this->tone($voiceOnly, 6, 440));
    }

    private function wav(float $seconds, int $frequency, bool $pauses = false): string
    {
        $pcm = '';
        for ($i = 0; $i < (int) ($seconds * 8000); ++$i) {
            $time = $i / 8000;
            $audible = ! $pauses || ($time >= 1 && $time < 2) || ($time >= 3 && $time < 3.5);
            $sample = $audible ? (int) round(sin(2 * \M_PI * $frequency * $time) * 0.4 * 32767) : 0;
            $pcm .= pack('v', $sample & 0xFFFF);
        }

        return 'RIFF'.pack('V', 36 + \strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', \strlen($pcm)).$pcm;
    }

    /** @return list<float> */
    private function samples(VideoBuilder $builder, AudioMix $mix): array
    {
        $image = imagecreatetruecolor(64, 64);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        $path = (string) tempnam(sys_get_temp_dir(), 'pw-audio-test-');

        try {
            file_put_contents($path, $builder->build([$png, $png, $png], 64, 64, $mix));
            $process = new Process(['ffmpeg', '-v', 'error', '-i', $path, '-vn', '-f', 'f32le', '-ar', '8000', '-ac', '1', 'pipe:1']);
            $process->mustRun();

            /** @var array<int, float> $samples */
            $samples = unpack('g*', $process->getOutput());

            return array_values($samples);
        } finally {
            unlink($path);
        }
    }

    /** @param list<float> $samples */
    private function tone(array $samples, float $start, int $frequency): float
    {
        $real = 0.0;
        $imaginary = 0.0;
        $offset = (int) ($start * 8000);
        for ($i = 0; $i < 1600; ++$i) {
            $angle = 2 * \M_PI * $frequency * $i / 8000;
            $real += $samples[$offset + $i] * cos($angle);
            $imaginary += $samples[$offset + $i] * sin($angle);
        }

        return 2 * sqrt($real ** 2 + $imaginary ** 2) / 1600;
    }
}
