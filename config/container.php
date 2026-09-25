<?php

declare(strict_types=1);

use App\Controllers\Admin\GalleryController as AdminGalleryController;
use App\Controllers\SitemapController;
use App\Database\Database;
use App\Repository\ServiceRepository;
use App\Support\SettingsService;
use App\Support\ViewGlobals;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;

/**
 * PHP-DI tanımları. public/index.php tarafından container'a yüklenir.
 */
return [
    'settings' => fn () => require __DIR__ . '/settings.php',

    PDO::class => function (ContainerInterface $c): PDO {
        return Database::fromConfig($c->get('settings')['db']);
    },

    Twig::class => function (ContainerInterface $c): Twig {
        $settings = $c->get('settings')['twig'];
        $twig = Twig::create($settings['templates'], [
            'cache' => $settings['cache'],
            'auto_reload' => true,
        ]);

        // Tüm şablonlara site ayarlarını ve yardımcıları enjekte et.
        /** @var ViewGlobals $globals */
        $globals = $c->get(ViewGlobals::class);
        $globals->apply($twig);

        return $twig;
    },

    SettingsService::class => fn (ContainerInterface $c) => new SettingsService($c->get(PDO::class)),

    ViewGlobals::class => fn (ContainerInterface $c) => new ViewGlobals(
        $c->get(SettingsService::class),
        $c->get(ServiceRepository::class),
        $c->get('settings')['app'],
        $c->get(App\Repository\MessageRepository::class)
    ),

    SitemapController::class => fn (ContainerInterface $c) => new SitemapController(
        $c->get(ServiceRepository::class),
        $c->get(App\Repository\DistrictRepository::class),
        $c->get(App\Repository\PageRepository::class),
        $c->get('settings')['app'],
        $c->get(SettingsService::class)
    ),

    AdminGalleryController::class => fn (ContainerInterface $c) => new AdminGalleryController(
        $c->get(Twig::class),
        $c->get(App\Repository\GalleryRepository::class),
        $c->get('settings')['app']['root'] . '/public/assets/uploads'
    ),

    App\Controllers\Admin\SeoController::class => fn (ContainerInterface $c) => new App\Controllers\Admin\SeoController(
        $c->get(Twig::class),
        $c->get(ServiceRepository::class),
        $c->get(App\Repository\PageRepository::class),
        $c->get(App\Repository\DistrictRepository::class),
        $c->get(SettingsService::class),
        $c->get(App\Support\SeoAnalyzer::class),
        $c->get(App\Repository\ActivityRepository::class),
        $c->get('settings')['app']
    ),
];
