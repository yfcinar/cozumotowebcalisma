<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Yüklenen görselleri gerçek içeriğine bakarak doğrular ve public/assets/uploads altına kaydeder.
 * İstemcinin bildirdiği MIME/uzantıya değil, getimagesize() sonucuna güvenir.
 */
final class ImageUploader
{
    private const TYPES = [
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];

    public function __construct(private readonly string $uploadDir)
    {
    }

    /**
     * Dosyayı doğrulayıp kaydeder. Başarılıysa public'e göre göreli yolu döner.
     * Başarısızsa null döner ve $error'a kullanıcıya gösterilecek mesajı yazar.
     */
    public function store(UploadedFileInterface $file, string $prefix, int $maxBytes, ?string &$error = null): ?string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $error = 'Dosya yüklenemedi (boyut sınırını aşıyor olabilir).';
            return null;
        }
        if ($file->getSize() > $maxBytes) {
            $error = sprintf('Dosya %d MB sınırını aşıyor.', (int) ($maxBytes / 1024 / 1024));
            return null;
        }

        $tmp = $this->uploadDir . '/' . $prefix . bin2hex(random_bytes(6)) . '.tmp';
        $file->moveTo($tmp);

        // İstemcinin bildirdiği türe değil, dosyanın gerçek içeriğine bak.
        $info = @getimagesize($tmp);
        $ext  = is_array($info) ? (self::TYPES[$info[2]] ?? null) : null;
        if ($ext === null) {
            @unlink($tmp);
            $error = 'Görsel PNG, JPG, WEBP veya GIF formatında olmalı.';
            return null;
        }

        $name = $prefix . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!@rename($tmp, $this->uploadDir . '/' . $name)) {
            @unlink($tmp);
            $error = 'Görsel kaydedilemedi, klasör yazma izinlerini kontrol edin.';
            return null;
        }

        return 'assets/uploads/' . $name;
    }

    /** Yalnızca bu yükleyicinin ürettiği (verilen önek ile başlayan) dosyaları siler. */
    public function delete(?string $relative, string $prefix): void
    {
        if ($relative === null || $relative === '') {
            return;
        }
        $name = basename($relative);
        if (!str_starts_with($name, $prefix)) {
            return;
        }
        $full = $this->uploadDir . '/' . $name;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
