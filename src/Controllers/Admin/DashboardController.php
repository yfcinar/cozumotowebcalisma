<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Repository\ActivityRepository;
use App\Repository\DistrictRepository;
use App\Repository\MessageRepository;
use App\Repository\PageRepository;
use App\Repository\ServiceRepository;
use App\Repository\StatsRepository;
use App\Support\SeoAnalyzer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class DashboardController extends BaseController
{
    public function __construct(
        Twig $view,
        private readonly ServiceRepository $services,
        private readonly DistrictRepository $districts,
        private readonly PageRepository $pages,
        private readonly MessageRepository $messages,
        private readonly StatsRepository $stats,
        private readonly ActivityRepository $activity,
        private readonly SeoAnalyzer $seo
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $services  = $this->services->all();
        $districts = $this->districts->all();
        $pages     = $this->pages->all();

        $activeServices  = array_filter($services, fn ($s) => (int) $s['is_active'] === 1);
        $activeDistricts = array_filter($districts, fn ($d) => (int) $d['is_active'] === 1);
        $districtServices = array_filter($activeServices, fn ($s) => (int) ($s['enable_districts'] ?? 1) === 1);

        // Dinamik olarak üretilen SEO landing sayfası sayısı.
        $generatedPages = count($districtServices) * count($activeDistricts);

        $messageTotals = $this->stats->messageTotals();
        $perDay = $this->stats->messagesPerDay(30);
        $content = $this->stats->contentBreakdown();

        // SEO puanı: hizmet + sayfaların ortalaması.
        $seoItems = array_merge($services, $pages);
        $seoScore = $this->seo->averageScore($seoItems);

        // Toplam sayfa/URL sayısı (site haritası büyüklüğü göstergesi).
        $totalUrls = 6 /* sabit sayfalar */ + count($activeServices) + $generatedPages + count($pages);

        return $this->render($response, 'admin/dashboard.twig', [
            'kpi' => [
                'services'       => count($services),
                'services_active' => count($activeServices),
                'districts'      => count($districts),
                'districts_active' => count($activeDistricts),
                'generated'      => $generatedPages,
                'pages'          => count($pages),
                'gallery'        => $content['gallery'],
                'faqs'           => $content['faqs'],
                'total_urls'     => $totalUrls,
                'seo_score'      => $seoScore,
                'seo_grade'      => $this->seo->grade($seoScore),
            ],
            'messages'        => $messageTotals,
            'chart_days'      => array_map(fn ($r) => date('d.m', strtotime($r['date'])), $perDay),
            'chart_counts'    => array_map(fn ($r) => $r['count'], $perDay),
            'content'         => $content,
            'recent_messages' => array_slice($this->messages->all(), 0, 6),
            'recent_activity' => $this->activity->recent(8),
        ]);
    }
}
