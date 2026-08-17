<?php declare(strict_types=1);

namespace Chatixy\Chat;

use Shopware\Core\Framework\Plugin;

/**
 * Chatixy AI Chat - Shopware 6 plugin entry point.
 *
 * The plugin needs no install/activate lifecycle code: the storefront loader is
 * injected by a Twig template that extends `@Storefront/storefront/base.html.twig`
 * (see src/Resources/views), reading the widget key from the plugin config
 * (`ChatixyChat.config.*`). All key/URL logic lives in Service\ChatixyKey.
 */
class ChatixyChat extends Plugin
{
}
