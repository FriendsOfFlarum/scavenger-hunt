# FoF Scavenger Hunt

![License](https://img.shields.io/badge/license-MIT-blue.svg)

A [Flarum](https://flarum.org) extension. Hide collectable parts around your forum and let members hunt them down to build a collectable: a snowman in winter, a lantern during Ramadan, a treasure chest during a sponsor's promotion.

## How it works

- A **hunt** builds one **collectable** out of several **parts**. Start a hunt from a built-in template or from scratch.
- Each part hides in one or more **hiding spots**, such as the page corner, a post's footer, a user card or a tag tile. A hiding spot can be on any page, on one page, or on one specific item (for example post #42).
- Each part has a **placement mode**:
  - **Fixed**: the same place for everyone. Fixed parts can have a hint.
  - **Assigned**: every member gets their own secret place, so sharing answers doesn't help.
  - **Wandering**: the part turns up by chance on each page view.
- Members click a part to collect it. The basket in the header shows their progress, and the collection view shows the collectable assembling as parts are found.
- Guests see parts too (unless a hunt is members-only), and clicking one invites them to log in or sign up.

### Fair play

Part locations are decided on the server and never sent to the browser in bulk. A part can only be collected with a short-lived, single-use token issued to the member it appeared for. Every page item a browser reports is checked against what that member can see and against the page it was reported on. Lookups are rate-limited per minute and per day. Hunt managers can see every hiding place, so by default they only preview parts.

## Installation

```sh
composer require fof/scavenger-hunt:"*"
```

## Updating

```sh
composer update fof/scavenger-hunt
php flarum migrate
php flarum cache:clear
```

## Running a hunt

Hunt managers (moderators and admins by default) open **Scavenger hunts** from the session menu or the index sidebar.

1. **New hunt** → pick a template (or Blank) and give it a title.
2. **Parts & locations**: give each part a placement mode, a hint for fixed parts, and one or more hiding places. **Place on the forum** switches on **hide it here** mode: every hiding spot on the forum is outlined, and clicking one hides the part there.
3. **Schedule & audience**: enable the hunt, optionally set when it starts and ends, and choose whether guests can see parts.

## Settings and permissions

In the admin panel you can:
- show or hide the header basket;
- let hunt managers collect parts;
- set the number of parts per page, the claim window, and the per-minute and per-day lookup limits.

There are two permissions:
- **Collect scavenger hunt parts** (Members by default)
- **Manage scavenger hunts** (Moderators by default)

## Built-in hiding spots

| Key | Where |
|---|---|
| `page.header` | Header, every page |
| `page.footer` | Footer, every page |
| `page.corner` | Floating corner, every page |
| `page.sidebar` | Index sidebar |
| `discussion.hero` | Discussion header |
| `post.body-end` | End of a post |
| `post.footer` | Post footer |
| `discussionList.item` | Discussion list item |
| `user.card` | User card |
| `user.sidebar` | User profile sidebar |
| `tags.tile` | Tag tile on the tags page (with flarum/tags) |

## Adding hiding spots from your extension

Register the spot in your `extend.php`, then draw it in your frontend. Declare `fof/scavenger-hunt` under `extra.flarum-extension.optional-dependencies` and wrap the extender in `Extend\Conditional` so your extension works without it.

```php
use Flarum\Extend;
use FoF\ScavengerHunt\Extend\HidingSpots;

return [
    (new Extend\Conditional())
        ->whenExtensionEnabled('fof-scavenger-hunt', fn () => [
            (new HidingSpots())
                // A kind of item a spot can belong to, checked with whereVisibleTo() when reported.
                ->context('article', Article::class)
                ->add(
                    'acme-blog.article-footer',                  // key, namespaced
                    'acme-blog.admin.scavenger.article_footer', // label (translation key)
                    routes: ['blog.article'],                    // forum routes it appears on; null for any, `user*` for a prefix
                    context: 'article',                          // null for spots that take no item
                    constrain: ArticleOnPage::class,             // optional: ties a reported item to its page
                )
                // A frontend-only route name for a page the forum knows by another name.
                ->routeAlias('blog.article.comments', 'blog.article')
                // Pages that shouldn't be offered to hunt managers.
                ->excludeRoute('blog.feed'),
        ]),
];
```

A constraint is an invokable class that narrows the query used to check reported items:

```php
use FoF\ScavengerHunt\HidingSpot\ReportedPage;
use Illuminate\Database\Eloquent\Builder;

class ArticleOnPage
{
    public function __invoke(Builder $query, ReportedPage $page): void
    {
        $query->where('articles.id', $page->intParam('id') ?? 0);
    }
}
```

In your frontend, add a `HidingSpot` wherever the part should appear. It renders nothing until the server places a part there:

```tsx
import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import HidingSpot from 'ext:fof/scavenger-hunt/forum/components/HidingSpot';

app.initializers.add('acme-blog', () => {
  if (!app.initializers.has('fof-scavenger-hunt')) return;

  extend('ext:acme/blog/forum/components/ArticleFooter', 'items', function (items) {
    items.add('scavengerHunt', <HidingSpot spot="acme-blog.article-footer" context={['article', Number(this.attrs.article.id())]} />);
  });
});
```

While a hunt manager has **hide it here** mode on, every `HidingSpot` draws an outlined target with the spot's label, so your spots can be placed by clicking too.

## Links

- [Source code on GitHub](https://github.com/FriendsOfFlarum/scavenger-hunt)
- [Report an issue](https://github.com/FriendsOfFlarum/scavenger-hunt/issues)
