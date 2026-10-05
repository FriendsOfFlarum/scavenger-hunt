<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Lookup;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\ScavengerHunt\Event\LookupLimitReached;
use FoF\ScavengerHunt\Setting;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Holds a lookup script to roughly human browsing speed: a per-minute limit for everyone and a
 * daily limit for members. Claims get the same per-minute limit, so junk tokens cannot be flooded. Counters live in the cache, so the limit costs no database queries.
 */
class LookupThrottler
{
    public const ROUTE = 'scavenger-hunt-encounters.lookup';
    public const CLAIM_ROUTE = 'scavenger-hunt-finds.claim';

    public function __construct(
        protected Repository $cache,
        protected SettingsRepositoryInterface $settings,
        protected Dispatcher $events,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ?bool
    {
        $route = $request->getAttribute('routeName');

        if ($route !== self::ROUTE && $route !== self::CLAIM_ROUTE) {
            return null;
        }

        $actor = RequestUtil::getActor($request);
        $now = Carbon::now();

        $who = $actor->isGuest()
            ? 'ip:'.($request->getAttribute('ipAddress') ?? $request->getServerParams()['REMOTE_ADDR'] ?? '')
            : 'user:'.$actor->id;

        $perMinute = $this->hit("fof-scavenger-hunt.throttle.$route.".sha1($who).'.'.$now->format('YmdHi'), 120);

        if ($perMinute > max(1, (int) $this->settings->get(Setting::LOOKUP_RATE_LIMIT))) {
            return true;
        }

        if ($actor->isGuest() || $route === self::CLAIM_ROUTE) {
            return null;
        }

        $limit = max(1, (int) $this->settings->get(Setting::DAILY_LOOKUP_LIMIT));
        $today = $this->hit("fof-scavenger-hunt.daily.$actor->id.".$now->format('Ymd'), 90000);

        if ($today === $limit + 1) {
            $this->events->dispatch(new LookupLimitReached($actor));
        }

        return $today > $limit ? true : null;
    }

    protected function hit(string $key, int $ttl): int
    {
        $this->cache->add($key, 0, $ttl);

        return (int) $this->cache->increment($key);
    }
}
