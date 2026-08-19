# Chatixy AI Chat - Shopware 6 plugin

Adds the [Chatixy](https://chatixy.com) AI chat widget to every page of a
[Shopware 6](https://www.shopware.com) storefront. Install the plugin, activate
it, paste your widget key. No theme fork, no template override to maintain, no
snippet to paste into a CMS block.

## Requirements

| | |
| --- | --- |
| Shopware | **6.5, 6.6 or 6.7** (`shopware/core: ~6.5.0 \|\| ~6.6.0 \|\| ~6.7.0`) |
| Plugin technical name | `ChatixyChat` |
| Licence | MIT |

## Install

```bash
composer require chatixy/shopware6-chat
bin/console plugin:refresh
bin/console plugin:install --activate ChatixyChat
bin/console cache:clear
```

Then open the Administration and go to
**Extensions -> My extensions -> Chatixy AI Chat -> Config**. Leave
**Enable Chatixy widget** on (it defaults to on), paste the **Widget key** from
your Chatixy dashboard, and save.

The key field accepts any of the three shapes the dashboard hands out:

- the bare 64-character key,
- `<key>.js`,
- the whole `<script src="https://chatixy.com/source/<key>.js" async></script>`
  snippet.

The key is extracted from whatever you paste - the first 64-character hex run
wins. Leave it empty and the plugin renders nothing at all.

Both settings live in Shopware's `system_config` under `ChatixyChat.config.*`,
so they can also be set per sales channel. Changing them changes cached
storefront HTML, so clear the cache (`bin/console cache:clear`) if you do not
see the change immediately.

No Chatixy account yet? Start at <https://chatixy.com/register>.

## What it does

On every storefront page it appends one tag at the end of the `<body>`:

```html
<script src="https://chatixy.com/source/<key>.js?platform=shopware" async></script>
```

That is the whole payload.

The tag is placed by a Twig template that does `{% sw_extends %}` on
`@Storefront/storefront/base.html.twig` and appends to the `base_body_inner`
block after `{{ parent() }}`. Extending rather than replacing means the plugin
stacks with your theme's own `base.html.twig` and with other plugins that
extend the same block. The loader is `position: fixed`, so body-end placement
keeps it out of the critical render path without changing where it appears.

## What it deliberately does not touch

- **Nothing in `<head>`**, no CSS, and no additional server-side requests.
- **No database.** There are no entities, no migrations, and no install /
  activate / uninstall lifecycle code - the config card plus the template are
  the entire plugin.
- **No Administration code.** The template extends the storefront base only, so
  the widget never renders inside the admin.
- **Nothing at all when it is not configured.** If the enable switch is off, or
  the stored value contains no valid 64-character hex key, the Twig helper
  returns an empty string and no tag is emitted.

## Security: the origin is pinned

The widget key is public; the **host** is not configurable. The old
*Chatixy host (advanced)* field was removed from the config card on purpose,
because a stored host would build a first-party `<script src>` on every page of
the storefront - i.e. shop-wide stored XSS if it could ever be influenced, and a
CSRF on the settings form would be the way to influence it.

`Chatixy\Chat\Service\ChatixyKey::sanitizeHost()` accepts only an **https**
origin whose host is `chatixy.com` or a subdomain of it, and returns the
canonical `https://chatixy.com` for anything else. The pattern is anchored at
both ends and only ever grows the host to the left of a literal dot, so
`evilchatixy.com`, `chatixy.com.evil.example`, `chatixy.com@evil.example` and
`http://chatixy.com` are all rejected. A `ChatixyChat.config.host` row left
behind by an older version of the plugin is still read - and pinned like
everything else - rather than left unread and unchecked.

There is one escape hatch, for developers working against a local Chatixy
instance: the `CHATIXY_ALLOW_INSECURE_HOST` environment variable. It is a
process-level switch (the shell that starts php-fpm, your Docker env), so
nothing an HTTP request can reach can turn it on. Never set it on a production
shop.

## Support

- Docs and account: <https://chatixy.com>
- Help: <https://chatixy.com/support>
- Email: <support@chatixy.com>

MIT licensed. See [LICENSE](LICENSE).
