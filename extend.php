<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt;

use Flarum\Api\Resource\ForumResource;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\User\User;
use FoF\ScavengerHunt\Extend\HidingSpots;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/resources/less/forum.less')
        ->route('/hunts/{slug}', 'fof-scavenger-hunt.hunt')
        ->route('/scavenger-hunt/dashboard', 'fof-scavenger-hunt.dashboard')
        ->route('/scavenger-hunt/dashboard/{id:\d+}', 'fof-scavenger-hunt.dashboard.hunt'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\ServiceProvider())
        ->register(ScavengerHuntServiceProvider::class),

    (new Extend\Event())
        ->subscribe(HuntMap\ClearHuntMap::class)
        ->listen(Event\HuntCompleted::class, Listener\AwardRewardGroup::class)
        ->listen(Event\HuntCompleted::class, Listener\SendCompletionNotification::class),

    (new Extend\Notification())
        ->type(Notification\HuntCompletedBlueprint::class, ['alert', 'email']),

    (new Extend\View())
        ->namespace('fof-scavenger-hunt', __DIR__.'/views'),

    (new Extend\Console())
        ->command(Console\PruneEncountersCommand::class)
        ->schedule(Console\PruneEncountersCommand::class, function (ScheduledEvent $event): void {
            $event->daily();
        }),

    (new HidingSpots())
        ->context('discussion', Discussion::class)
        ->context('post', Post::class)
        ->context('user', User::class)
        ->add('page.header', 'fof-scavenger-hunt.admin.spots.page_header')
        ->add('page.footer', 'fof-scavenger-hunt.admin.spots.page_footer')
        ->add('page.corner', 'fof-scavenger-hunt.admin.spots.page_corner')
        ->add('page.sidebar', 'fof-scavenger-hunt.admin.spots.page_sidebar')
        ->add('discussion.hero', 'fof-scavenger-hunt.admin.spots.discussion_hero', ['discussion'], 'discussion', HidingSpot\Constraint\IsPageDiscussion::class)
        ->add('post.body-end', 'fof-scavenger-hunt.admin.spots.post_body_end', ['discussion'], 'post', HidingSpot\Constraint\PostInPageDiscussion::class)
        ->add('post.footer', 'fof-scavenger-hunt.admin.spots.post_footer', ['discussion'], 'post', HidingSpot\Constraint\PostInPageDiscussion::class)
        ->add('discussionList.item', 'fof-scavenger-hunt.admin.spots.discussion_list_item', null, 'discussion')
        ->add('user.card', 'fof-scavenger-hunt.admin.spots.user_card', null, 'user')
        ->add('user.sidebar', 'fof-scavenger-hunt.admin.spots.user_sidebar', ['user*'], 'user', HidingSpot\Constraint\IsPageUser::class)
        ->routeAlias('discussion.near', 'discussion')
        ->routeAlias('user.posts', 'user')
        ->routeAlias('user.discussions', 'user')
        ->routeAlias('user.security', 'user')
        ->excludeRoute('logoutPage', 'globalLogout', 'notifications.unsubscribe', 'confirmEmail', 'resetPassword', 'maintenance.login')
        ->excludeRoute('fof-scavenger-hunt.dashboard', 'fof-scavenger-hunt.dashboard.hunt')
        // The frontend gives the home page the default action's own route name, never `default`.
        ->excludeRoute('default'),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-tags', fn () => [
            (new HidingSpots())
                ->context('tag', Tag::class)
                ->add('tags.tile', 'fof-scavenger-hunt.admin.spots.tags_tile', ['tags'], 'tag'),
        ]),

    // AbstractModel takes defaults from this registry, not from Eloquent's $attributes.
    (new Extend\Model(Hunt::class))
        ->default('is_enabled', false)
        ->default('guests_can_see', true),

    (new Extend\Model(Part::class))
        ->default('mode', PlacementMode::Fixed->value)
        ->default('sort', 0),

    (new Extend\Model(User::class))
        ->hasMany('scavengerFinds', Find::class, 'user_id')
        ->hasMany('scavengerCompletions', Completion::class, 'user_id'),

    (new Extend\ModelVisibility(Hunt::class))
        ->scope(Access\ScopeHuntVisibility::class),

    (new Extend\ModelVisibility(Part::class))
        ->scope(Access\ScopePartVisibility::class),

    (new Extend\ModelVisibility(Location::class))
        ->scope(Access\ScopeLocationVisibility::class),

    new Extend\ApiResource(Api\Resource\HuntResource::class),
    new Extend\ApiResource(Api\Resource\TemplateResource::class),
    new Extend\ApiResource(Api\Resource\PartResource::class),
    new Extend\ApiResource(Api\Resource\HidingSpotResource::class),
    new Extend\ApiResource(Api\Resource\LocationResource::class),
    new Extend\ApiResource(Api\Resource\EncounterResource::class),
    new Extend\ApiResource(Api\Resource\FindResource::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumAttributes::class),

    (new Extend\ThrottleApi())
        ->set('fof-scavenger-hunt.lookups', Lookup\LookupThrottler::class),

    (new Extend\Settings())
        ->default(Setting::TOKEN_LIFETIME, 10)
        ->default(Setting::MAX_PARTS_PER_PAGE, 1)
        ->default(Setting::LOOKUP_RATE_LIMIT, 30)
        ->default(Setting::DAILY_LOOKUP_LIMIT, 1500)
        ->default(Setting::MANAGERS_CAN_PLAY, false)
        ->default(Setting::SHOW_HEADER_INDICATOR, true)
        ->serializeToForum('fof-scavenger-hunt.showHeaderIndicator', Setting::SHOW_HEADER_INDICATOR, 'boolval'),
];
