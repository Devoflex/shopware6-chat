<?php declare(strict_types=1);

namespace Chatixy\Chat\Storefront\Framework\Twig;

use Chatixy\Chat\Service\ChatixyKey;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes a `chatixy_loader(widgetKey, host)` Twig function to the storefront.
 * It sanitises the configured key + host and returns the async loader <script>
 * tag (or '' when the key is invalid), so the template stays dumb. The src is
 * HTML-escaped in ChatixyKey::scriptTag, hence the function is marked html-safe.
 */
class ChatixyTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('chatixy_loader', [$this, 'loader'], ['is_safe' => ['html']]),
        ];
    }

    public function loader(?string $widgetKey, ?string $host = null): string
    {
        $key = ChatixyKey::sanitizeWidgetKey((string) $widgetKey);
        if (!ChatixyKey::isValidWidgetKey($key)) {
            return '';
        }
        return ChatixyKey::scriptTag(ChatixyKey::sanitizeHost((string) $host), $key, 'shopware');
    }
}
