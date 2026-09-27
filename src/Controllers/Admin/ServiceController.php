<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Repository\ActivityRepository;
use App\Repository\ServiceRepository;
use App\Support\ImageUploader;
use App\Support\Session;
use App\Support\Str;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class ServiceController extends BaseController
{
    private const IMAGE_PREFIX = 'svc-';
    private const IMAGE_MAX_BYTES = 4 * 1024 * 1024;

    public function __construct(
        Twig $view,
        private readonly ServiceRepository $services,
        private readonly ActivityRepository $activity,
        private readonly ImageUploader $images
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/services/index.twig', [
            'services' => $this->services->all(),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/services/form.twig', [
            'service' => null,
            'action'  => '/yonetim/hizmetler',
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $this->validated($request, (array) $request->getParsedBody(), null);
        if ($data === null) {
            return $this->redirect($response, '/yonetim/hizmetler/yeni');
        }
        $this->services->create($data);
        $this->activity->log(Session::user()['name'] ?? null, 'ekledi', 'Hizmet', $data['title']);
        Session::flash('success', 'Hizmet eklendi.');
        return $this->redirect($response, '/yonetim/hizmetler');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $service = $this->services->find((int) $args['id']);
        if ($service === null) {
            throw new HttpNotFoundException($request);
        }
        return $this->render($response, 'admin/services/form.twig', [
            'service' => $service,
            'action'  => '/yonetim/hizmetler/' . $service['id'],
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->services->find($id);
        if ($existing === null) {
            throw new HttpNotFoundException($request);
        }
        $data = $this->validated($request, (array) $request->getParsedBody(), $existing);
        if ($data === null) {
            return $this->redirect($response, '/yonetim/hizmetler/' . $id . '/duzenle');
        }
        $this->services->update($id, $data);
        $this->activity->log(Session::user()['name'] ?? null, 'güncelledi', 'Hizmet', $data['title']);
        Session::flash('success', 'Hizmet güncellendi.');
        return $this->redirect($response, '/yonetim/hizmetler');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $svc = $this->services->find((int) $args['id']);
        $this->images->delete($svc['image'] ?? null, self::IMAGE_PREFIX);
        $this->services->delete((int) $args['id']);
        $this->activity->log(Session::user()['name'] ?? null, 'sildi', 'Hizmet', $svc['title'] ?? ('#' . $args['id']));
        Session::flash('success', 'Hizmet silindi.');
        return $this->redirect($response, '/yonetim/hizmetler');
    }

    private function validated(Request $request, array $d, ?array $existing): ?array
    {
        $title = trim((string) ($d['title'] ?? ''));
        if ($title === '') {
            Session::flash('error', 'Hizmet başlığı zorunludur.');
            return null;
        }
        $slug = trim((string) ($d['slug'] ?? '')) ?: $title;

        $image = $existing['image'] ?? null;
        if (!empty($d['image_remove'])) {
            $this->images->delete($image, self::IMAGE_PREFIX);
            $image = null;
        }

        $file = $request->getUploadedFiles()['image_file'] ?? null;
        if ($file instanceof UploadedFileInterface && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $error = null;
            $path  = $this->images->store($file, self::IMAGE_PREFIX, self::IMAGE_MAX_BYTES, $error);
            if ($path !== null) {
                $this->images->delete($image, self::IMAGE_PREFIX);
                $image = $path;
            } else {
                Session::flash('error', $error ?? 'Görsel yüklenemedi.');
            }
        }

        return [
            'title'            => $title,
            'slug'             => Str::slug($slug),
            'summary'          => trim((string) ($d['summary'] ?? '')) ?: null,
            'body'             => (string) ($d['body'] ?? ''),
            'icon'             => trim((string) ($d['icon'] ?? '')) ?: null,
            'image'            => $image,
            'price'            => trim((string) ($d['price'] ?? '')) ?: null,
            'meta_title'       => trim((string) ($d['meta_title'] ?? '')) ?: null,
            'meta_description' => trim((string) ($d['meta_description'] ?? '')) ?: null,
            'sort_order'       => (int) ($d['sort_order'] ?? 0),
            'is_active'        => isset($d['is_active']) ? 1 : 0,
            'enable_districts' => isset($d['enable_districts']) ? 1 : 0,
        ];
    }
}
