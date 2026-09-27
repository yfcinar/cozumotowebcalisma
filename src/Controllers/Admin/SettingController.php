<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Support\ImageUploader;
use App\Support\Session;
use App\Support\SettingsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

final class SettingController extends BaseController
{
    /** Yönetilebilir ayar anahtarları. */
    private const KEYS = [
        'brand_name', 'site_title', 'site_description',
        'phone_primary', 'phone_secondary', 'whatsapp', 'email',
        'address', 'maps_url', 'maps_embed',
        'working_hours',
        'instagram', 'facebook', 'twitter', 'youtube', 'linkedin', 'pinterest',
        'hero_title', 'hero_subtitle',
    ];

    private const LOGO_PREFIX = 'logo-';
    private const LOGO_MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(
        Twig $view,
        private readonly SettingsService $settings,
        private readonly ImageUploader $images
    ) {
        parent::__construct($view);
    }

    public function edit(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/settings.twig', [
            'values' => $this->settings->all(),
            'keys'   => self::KEYS,
        ]);
    }

    public function update(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $pairs = [];
        foreach (self::KEYS as $key) {
            $pairs[$key] = isset($data[$key]) ? trim((string) $data[$key]) : '';
        }

        $pairs['logo_hide_text'] = !empty($data['logo_hide_text']) ? '1' : '0';

        $current = (string) $this->settings->get('logo', '');
        if (!empty($data['logo_remove'])) {
            $this->images->delete($current, self::LOGO_PREFIX);
            $pairs['logo'] = '';
        }

        $file = $request->getUploadedFiles()['logo_file'] ?? null;
        if ($file instanceof UploadedFileInterface && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $error = null;
            $path  = $this->images->store($file, self::LOGO_PREFIX, self::LOGO_MAX_BYTES, $error);
            if ($path !== null) {
                $this->images->delete((string) ($pairs['logo'] ?? $current), self::LOGO_PREFIX);
                $pairs['logo'] = $path;
            } else {
                Session::flash('error', $error ?? 'Logo yüklenemedi.');
            }
        }

        $this->settings->setMany($pairs);
        Session::flash('success', 'Ayarlar kaydedildi.');
        return $this->redirect($response, '/yonetim/ayarlar');
    }
}
