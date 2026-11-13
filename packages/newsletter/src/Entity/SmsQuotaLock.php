<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Entity;

use Doctrine\ORM\Mapping as ORM;

/** One durable lock shared by every campaign and test in this installation. */
#[ORM\Entity]
#[ORM\Table(name: 'newsletter_sms_quota_lock')]
class SmsQuotaLock
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 1;
}
