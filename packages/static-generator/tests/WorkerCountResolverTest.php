<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\StaticGenerator\WorkerCountResolver;

final class WorkerCountResolverTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, int}> */
    public static function workerLimits(): iterable
    {
        yield 'explicit workers capped' => [8, 100, 4, 4];
        yield 'page count still caps explicit workers' => [8, 2, 4, 2];
        yield 'explicit sequential' => [1, 100, 4, 1];
        yield 'small auto build stays sequential' => [0, 5, 4, 1];
        yield 'large auto build can be forced sequential' => [0, 100, 1, 1];
        yield 'zero leaves explicit workers unchanged' => [8, 100, 0, 8];
    }

    #[DataProvider('workerLimits')]
    public function testWorkerLimit(int $requested, int $pages, int $maximum, int $expected): void
    {
        self::assertSame($expected, WorkerCountResolver::resolve($requested, $pages, $maximum));
    }

    public function testAutoWorkersRespectTheCapWithoutDisablingParallelism(): void
    {
        $unlimited = WorkerCountResolver::resolve(0, 100);
        self::assertSame(min(2, $unlimited), WorkerCountResolver::resolve(0, 100, 2));
    }
}
