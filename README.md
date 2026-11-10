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

1. **New hunt** → pick a template (or Blank) and give it a title. There are twelve templates: spring, summer, autumn, winter, Easter, Ramadan, Thanksgiving, Christmas, New Year, Halloween, a treasure chest for promotions, and a birthday cake for your forum's anniversary. Templates carry no dates, since seasons and moving holidays differ between forums.
2. **Details**: the title, description, and the collectable's images. Pick any template image or an image uploaded in the admin panel.
3. **Parts & locations**: give each part an image, a placement mode, a hint for fixed parts, and one or more hiding places. Arrange the collectable by dragging parts on the canvas, resizing them from the corner handle and changing their stacking order, then **Save layout**. **Place on the forum** switches on **hide it here** mode: every hiding spot on the forum is outlined, and clicking one hides the part there.
4. **Rewards**: a message revealed only to finishers, and a group finishers join automatically. Only members who can edit user groups can choose the group, because joining it grants its permissions.
5. **Schedule & audience**: enable the hunt, optionally set when it starts and ends, and choose whether guests can see parts.

## Settings and permissions

In the admin panel you can:
- show or hide the header basket;
- let hunt managers collect parts;
- set the number of parts per page, the claim window, and the per-minute and per-day lookup limits;
- manage the **image library**: upload PNG, JPEG, GIF, WebP or SVG images up to 2 MB for hunt managers to use, rename them, and delete the ones no hunt or part uses. Uploaded SVGs are cleaned of scripts, event handlers and references to other sites.

There are two permissions:
- **Collect scavenger hunt parts** (Members by default)
- **Manage scavenger hunts** (Moderators by default)

### Serving uploaded images safely (recommended)

Uploaded images are served from your forum's own domain, under `assets/scavenger-hunt/`. Uploaded SVGs are already cleaned, but you can add a second layer of protection: tell browsers to treat these files as plain images that can't run scripts or load anything else. Add one of these to your web server configuration and reload it. If Flarum is installed in a subfolder, put that folder in front of `/assets`.

**nginx**, inside your Flarum `server` block. The `^~` makes this location win over the image block in Flarum's `.nginx.conf`, so it repeats that block's cache header:

```nginx
location ^~ /assets/scavenger-hunt/ {
    add_header Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Cache-Control "max-age=2592000";
    access_log off;
}
```

**Apache** (2.4, with `mod_headers`), in your virtual host or in Flarum's `public/.htaccess`:

```apache
<If "%{REQUEST_URI} =~ m#^/assets/scavenger-hunt/#">
    Header always set Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox"
    Header always set X-Content-Type-Options "nosniff"
</If>
```

If your forum's assets are stored on S3 or served through a CDN, set the same two headers there for the `scavenger-hunt/` folder instead.

## Integrations

All optional; each works once the other extension is enabled.

- **[flarum/tags](https://docs.flarum.org/extensions/tags)**: hide parts on tag tiles.
- **[flarum/audit](https://docs.flarum.org/extensions/audit)**: logs hunt, part and location changes, completions, progress resets, image uploads and deletions, and members reaching the daily lookup limit.
- **[flarum/gdpr](https://docs.flarum.org/extensions/gdpr)**: a member's data export includes the parts they found, the hunts they completed and the parts they were shown. Anonymizing deletes the record of parts shown and keeps finds and completions, so a hunt's first finishers stay intact. Deleting removes all three.
- **[fof/gamification](https://github.com/FriendsOfFlarum/gamification)**: ranks members by parts found and hunts completed on the leaderboard.

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
