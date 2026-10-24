<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Event;

use LogicException;
use Symfony\Contracts\EventDispatcher\Event;

/** Local rows are populated before listeners add sources, notices or navigation links. */
final class CollectCampaignIndex extends Event
{
    /** @var array<string, CampaignIndexItem> */
    public array $items = [];

    /** @var array<string, string> Source identifier mapped to a cache/availability notice. */
    public array $notices = [];

    /** @var array<string, string> Creation labels mapped to navigation URLs, even for empty sources. */
    public array $creationActions = [];

    public function add(CampaignIndexItem $item): void
    {
        if (isset($this->items[$item->key])) {
            throw new LogicException('Duplicate campaign index identity: '.$item->key);
        }

        $this->items[$item->key] = $item;
    }
}
