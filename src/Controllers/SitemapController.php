<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repository\DistrictRepository;
use App\Repository\PageRepository;
use App\Repository\ServiceRepository;
use App\Support\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Dinamik sitemap.xml ve robots.txt üretir. Hizmet x İlçe sayfaları otomatik dahil edilir.
 */
final class SitemapController
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly DistrictRepository $districts,
        private readonly PageRepository $pages,
        private readonly array $appConfig,
        private readonly ?SettingsService $settings = null
    ) {
    }

    public function sitemap(Request $request, Response $response): Response
    {
        $base = $this->appConfig['url'] ?: $this->baseFromRequest($request);
        $urls = [];

        $today = date('Y-m-d');
        $lm = static function (?string $ts) use ($today): string {
            return $ts ? substr((string) $ts, 0, 10) : $today;
        };

        $add = static function (string $loc, string $priority = '0.7', string $freq = 'weekly', ?string $lastmod = null) use (&$urls, $today): void {
            $urls[] = ['loc' => $loc, 'priority' => $priority, 'freq' => $freq, 'lastmod' => $lastmod ?: $today];
        };

        $add($base . '/', '1.0', 'daily');
        $add($base . '/hizmetler', '0.9');
        $add($base . '/hakkimizda');
        $add($base . '/galeri');
        $add($base . '/iletisim', '0.8');

        $districts = $this->districts->allActive();

        foreach ($this->services->allActive() as $service) {
            $add($base . '/hizmet/' . $service['slug'], '0.9', 'weekly', $lm($service['updated_at'] ?? null));
            if ((int) $service['enable_districts'] === 1) {
                foreach ($districts as $district) {
                    $add($base . '/hizmet/' . $service['slug'] . '/' . $district['slug'], '0.6', 'weekly', $lm($service['updated_at'] ?? null));
                }
            }
        }

        foreach ($this->pages->all() as $page) {
            // hakkimizda kendi özel URL'inde (/hakkimizda) zaten listelendi.
            if ((int) $page['is_active'] === 1 && $page['slug'] !== 'hakkimizda') {
                $add($base . '/sayfa/' . $page['slug'], '0.7', 'weekly', $lm($page['updated_at'] ?? null));
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
            $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            $xml .= '    <changefreq>' . $u['freq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $u['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        $response->getBody()->write($xml);
        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(Request $request, Response $response): Response
    {
        $base = $this->appConfig['url'] ?: $this->baseFromRequest($request);

        // Panelden özel robots.txt tanımlandıysa onu kullan.
        $custom = $this->settings?->get('robots_custom');
        if ($custom !== null && trim($custom) !== '') {
            $txt = trim($custom) . "\n";
            if (!str_contains($txt, 'Sitemap:')) {
                $txt .= "\nSitemap: {$base}/sitemap.xml\n";
            }
        } else {
            $txt = "User-agent: *\nAllow: /\nDisallow: /yonetim\n\n"
                . "# Yapay zeka / LLM tarayıcılarına açık\n"
                . "User-agent: GPTBot\nAllow: /\n\n"
                . "User-agent: OAI-SearchBot\nAllow: /\n\n"
                . "User-agent: ClaudeBot\nAllow: /\n\n"
                . "User-agent: PerplexityBot\nAllow: /\n\n"
                . "User-agent: Google-Extended\nAllow: /\n\n"
                . "Sitemap: {$base}/sitemap.xml\n";
        }

        $response->getBody()->write($txt);
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * llms.txt — yapay zeka asistanları için sade, Markdown biçimli site özeti
     * (llmstxt.org yakınsaması). İşletme bilgileri ve hizmet listesini sunar.
     */
    public function llms(Request $request, Response $response): Response
    {
        $base = $this->appConfig['url'] ?: $this->baseFromRequest($request);
        $s = $this->settings?->all() ?? [];
        $brand = $s['brand_name'] ?? 'Çözüm Oto Elektrik';
        $desc = $s['site_description'] ?? 'Renault EDC şanzıman, mekatronik beyin onarımı ve oto elektronik servisi.';

        $out = "# {$brand}\n\n";
        $out .= "> {$desc}\n\n";

        $contact = [];
        if (!empty($s['phone_primary'])) $contact[] = "- Telefon: {$s['phone_primary']}";
        if (!empty($s['whatsapp']))      $contact[] = "- WhatsApp: {$s['whatsapp']}";
        if (!empty($s['email']))         $contact[] = "- E-posta: {$s['email']}";
        if (!empty($s['address']))       $contact[] = "- Adres: {$s['address']}";
        if (!empty($s['working_hours'])) $contact[] = "- Çalışma saatleri: {$s['working_hours']}";
        if ($contact) {
            $out .= "## İletişim\n\n" . implode("\n", $contact) . "\n\n";
        }

        $out .= "## Hizmetler\n\n";
        foreach ($this->services->allActive() as $svc) {
            $line = "- [{$svc['title']}]({$base}/hizmet/{$svc['slug']})";
            $summary = trim((string) ($svc['summary'] ?? ''));
            if (!empty($svc['price'])) {
                $line .= ' — ' . $svc['price'];
            }
            if ($summary !== '') {
                $line .= ': ' . $summary;
            }
            $out .= $line . "\n";
        }

        $out .= "\n## Önemli Sayfalar\n\n";
        $out .= "- [Anasayfa]({$base}/)\n";
        $out .= "- [Tüm Hizmetler]({$base}/hizmetler)\n";
        $out .= "- [Hakkımızda]({$base}/hakkimizda)\n";
        $out .= "- [Galeri]({$base}/galeri)\n";
        $out .= "- [İletişim]({$base}/iletisim)\n";
        $out .= "- [Site Haritası]({$base}/sitemap.xml)\n";

        $response->getBody()->write($out);
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    private function baseFromRequest(Request $request): string
    {
        $uri = $request->getUri();
        $base = $uri->getScheme() . '://' . $uri->getHost();
        if ($uri->getPort() && !in_array($uri->getPort(), [80, 443], true)) {
            $base .= ':' . $uri->getPort();
        }
        return $base;
    }
}
