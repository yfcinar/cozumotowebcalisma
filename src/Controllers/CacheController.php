<?php

declare(strict_types=1);

namespace App\Controllers;

use FilesystemIterator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * /clearcache — Twig'in derlenmiş şablon önbelleğini temizler.
 * Yeni bir sürüm deploy edildikten sonra eski görünümün kalmaması için kullanılır.
 */
final class CacheController
{
    public function __construct(private readonly array $appConfig)
    {
    }

    public function clear(Request $request, Response $response): Response
    {
        $dir = $this->appConfig['root'] . '/var/cache/twig';
        $count = $this->purge($dir);

        $message = $count > 0
            ? "Önbellek temizlendi ({$count} dosya silindi)."
            : 'Önbellek zaten boştu, silinecek bir şey yok.';

        $response->getBody()->write($message);
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    private function purge(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }
            if (@unlink($item->getPathname())) {
                $count++;
            }
        }

        return $count;
    }
}
