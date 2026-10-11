<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Painting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * خريطة الموقع.
 *
 * تُولَّد من قاعدة البيانات لا تُكتب يدوياً: الفنانة تضيف أعمالاً أسبوعياً،
 * وأي ملف ثابت سيتخلّف عن المعرض خلال أيام.
 *
 * تُخدَم من Laravel لأنه يملك البيانات، ويوجَّه إليها من نطاق المتجر في
 * الإنتاج — انظر docs/DEPLOYMENT.md
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap:xml', now()->addHour(), function (): string {
            $base = rtrim((string) config('platform.urls.web'), '/');

            $urls = [
                ['loc' => $base . '/', 'priority' => '1.0', 'freq' => 'daily'],
                ['loc' => $base . '/paintings', 'priority' => '0.9', 'freq' => 'daily'],
                ['loc' => $base . '/about', 'priority' => '0.5', 'freq' => 'monthly'],
            ];

            foreach (Category::where('is_active', true)->get() as $category) {
                $urls[] = [
                    'loc' => $base . '/paintings?category=' . rawurlencode($category->slug),
                    'priority' => '0.7',
                    'freq' => 'weekly',
                    'lastmod' => $category->updated_at?->toAtomString(),
                ];
            }

            Painting::published()->select(['slug', 'updated_at'])->chunk(200, function ($chunk) use (&$urls, $base): void {
                foreach ($chunk as $painting) {
                    $urls[] = [
                        'loc' => $base . '/paintings/' . rawurlencode($painting->slug),
                        'priority' => '0.8',
                        'freq' => 'weekly',
                        'lastmod' => $painting->updated_at?->toAtomString(),
                    ];
                }
            });

            $body = '';

            foreach ($urls as $url) {
                $body .= '  <url>' . PHP_EOL
                    . '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . '</loc>' . PHP_EOL
                    . (isset($url['lastmod']) ? '    <lastmod>' . $url['lastmod'] . '</lastmod>' . PHP_EOL : '')
                    . '    <changefreq>' . $url['freq'] . '</changefreq>' . PHP_EOL
                    . '    <priority>' . $url['priority'] . '</priority>' . PHP_EOL
                    . '  </url>' . PHP_EOL;
            }

            return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL
                . $body
                . '</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
