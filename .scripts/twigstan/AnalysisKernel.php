<?php

declare(strict_types=1);

namespace Pushword\TwigStanTool;

use App\Kernel;
use Pushword\TwigStan\AnalysisKernelTrait;

final class AnalysisKernel extends Kernel
{
    use AnalysisKernelTrait;
}
