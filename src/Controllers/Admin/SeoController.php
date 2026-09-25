<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Repository\ActivityRepository;
use App\Repository\DistrictRepository;
use App\Repository\PageRepository;
use App\Repository\ServiceRepository;
use App\Support\SeoAnalyzer;
use App\Support\Session;
use App\Support\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class SeoController extends BaseController
{
    /** SEO / analytics ile ilgili yönetilebilir ayar anahtarları. */
    public const SEO_KEYS = [
        'ga4_id', 'gtm_id', 'gsc_verification', 'bing_verification',
        'og_image', 'seo_index', 'robots_custom', 'seo_keywords',
    ];

    public function __construct(
        Twig $view,
        private readonly ServiceRepository $services,
        private readonly PageRepository $pages,
        private readonly DistrictRepository $districts,
        private readonly SettingsService $settings,
        private readonly SeoAnalyzer $seo,
        private readonly ActivityRepository $activity,
        private readonly array $app
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $rows = [];

        foreach ($this->services->all() as $s) {
            $a = $this->seo->analyze($s);
            $rows[] = [
                'type'  => 'Hizmet',
                'title' => $s['title'],
                'url'   => '/hizmet/' . $s['slug'],
                'analysis' => $a,
            ];
        }
        foreach ($this->pages->all() as $p) {
            $a = $this->seo->analyze($p);
            $rows[] = [
                'type'  => 'Sayfa',
                'title' => $p['title'],
                'url'   => $p['slug'] === 'hakkimizda' ? '/hakkimizda' : '/sayfa/' . $p['slug'],
                'analysis' => $a,
            ];
        }

        // Puana göre sırala (en zayıf üstte).
        usort($rows, fn ($a, $b) => $a['analysis']['score'] <=> $b['analysis']['score']);

        $avg = (int) round(array_sum(array_map(fn ($r) => $r['analysis']['score'], $rows)) / max(1, count($rows)));

        $issueCount = 0;
        foreach ($rows as $r) {
            foreach ($r['analysis']['issues'] as $i) {
                if ($i['level'] === 'error') {
                    $issueCount++;
                }
            }
        }

        $activeServices = array_filter($this->services->all(), fn ($s) => (int) $s['is_active'] === 1);
        $activeDistricts = $this->districts->allActive();
        $districtServices = array_filter($activeServices, fn ($s) => (int) ($s['enable_districts'] ?? 1) === 1);

        return $this->render($response, 'admin/seo/index.twig', [
            'rows'          => $rows,
            'avg'           => $avg,
            'grade'         => $this->seo->grade($avg),
            'error_count'   => $issueCount,
            'sitemap_urls'  => 3 + count($activeServices) + (count($districtServices) * count($activeDistricts)) + count($this->pages->all()),
            'values'        => $this->settings->all(),
            'app_url'       => $this->app['url'] ?? '',
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $pairs = [];
        foreach (self::SEO_KEYS as $key) {
            $pairs[$key] = isset($data[$key]) ? trim((string) $data[$key]) : '';
        }
        $this->settings->setMany($pairs);
        $this->activity->log(Session::user()['name'] ?? null, 'güncelledi', 'SEO ayarları', 'Analytics ve SEO ayarları kaydedildi');
        Session::flash('success', 'SEO ve analytics ayarları kaydedildi.');
        return $this->redirect($response, '/yonetim/seo');
    }
}
