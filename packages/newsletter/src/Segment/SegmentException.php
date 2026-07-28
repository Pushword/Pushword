<?php

namespace Pushword\Newsletter\Segment;

use InvalidArgumentException;

/** An unusable segment definition: unknown field, unknown operator, bad duration. */
class SegmentException extends InvalidArgumentException
{
}
