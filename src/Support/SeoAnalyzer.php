<?php

declare(strict_types=1);

namespace App\Support;

/**
 * İçeriklerin SEO uygunluğunu değerlendirir: başlık/açıklama uzunlukları,
 * eksik meta alanları, içerik uzunluğu vb. Her öğe için 0-100 puan üretir.
 */
final class SeoAnalyzer
{
    // Google için ideal aralıklar.
    private const TITLE_MIN = 30;
    private const TITLE_MAX = 60;
    private const DESC_MIN = 70;
    private const DESC_MAX = 160;

    /**
     * Tek bir içeriği analiz eder.
     * $item: ['title'=>, 'meta_title'=>, 'meta_description'=>, 'body'=>, 'slug'=>] bekler.
     */
    public function analyze(array $item): array
    {
        $issues = [];
        $score = 100;

        $metaTitle = trim((string) ($item['meta_title'] ?? ''));
        $effectiveTitle = $metaTitle !== '' ? $metaTitle : trim((string) ($item['title'] ?? ''));
        $desc = trim((string) ($item['meta_description'] ?? ''));
        $summary = trim((string) ($item['summary'] ?? ''));
        $effectiveDesc = $desc !== '' ? $desc : $summary;
        $bodyText = trim(strip_tags((string) ($item['body'] ?? '')));
        $wordCount = $bodyText === '' ? 0 : count(preg_split('/\s+/', $bodyText));

        // Meta başlık.
        if ($metaTitle === '') {
            $issues[] = ['level' => 'warn', 'text' => 'Özel meta başlık yok (sayfa başlığı kullanılıyor).'];
            $score -= 10;
        }
        $tLen = mb_strlen($effectiveTitle);
        if ($tLen === 0) {
            $issues[] = ['level' => 'error', 'text' => 'Başlık boş.'];
            $score -= 30;
        } elseif ($tLen < self::TITLE_MIN) {
            $issues[] = ['level' => 'warn', 'text' => "Başlık kısa ({$tLen} kr.), en az " . self::TITLE_MIN . ' önerilir.'];
            $score -= 10;
        } elseif ($tLen > self::TITLE_MAX) {
            $issues[] = ['level' => 'warn', 'text' => "Başlık uzun ({$tLen} kr.), en fazla " . self::TITLE_MAX . ' önerilir.'];
            $score -= 8;
        }

        // Meta açıklama.
        if ($desc === '') {
            $issues[] = ['level' => 'error', 'text' => 'Meta açıklama yok.'];
            $score -= 25;
        } else {
            $dLen = mb_strlen($effectiveDesc);
            if ($dLen < self::DESC_MIN) {
                $issues[] = ['level' => 'warn', 'text' => "Açıklama kısa ({$dLen} kr.), en az " . self::DESC_MIN . ' önerilir.'];
                $score -= 10;
            } elseif ($dLen > self::DESC_MAX) {
                $issues[] = ['level' => 'warn', 'text' => "Açıklama uzun ({$dLen} kr.), en fazla " . self::DESC_MAX . ' önerilir.'];
                $score -= 8;
            }
        }

        // İçerik uzunluğu.
        if (array_key_exists('body', $item)) {
            if ($wordCount === 0) {
                $issues[] = ['level' => 'error', 'text' => 'İçerik boş.'];
                $score -= 20;
            } elseif ($wordCount < 120) {
                $issues[] = ['level' => 'warn', 'text' => "İçerik kısa ({$wordCount} kelime), 300+ önerilir."];
                $score -= 10;
            }
        }

        // Slug.
        $slug = (string) ($item['slug'] ?? '');
        if ($slug !== '' && !preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $issues[] = ['level' => 'warn', 'text' => 'Slug SEO dostu değil (küçük harf/tire kullanın).'];
            $score -= 5;
        }

        $score = max(0, min(100, $score));

        return [
            'score'        => $score,
            'grade'        => $this->grade($score),
            'issues'       => $issues,
            'title_len'    => $tLen,
            'desc_len'     => mb_strlen($effectiveDesc),
            'word_count'   => $wordCount,
        ];
    }

    /** Birden çok içeriğin ortalama SEO puanı. */
    public function averageScore(array $items): int
    {
        if (!$items) {
            return 0;
        }
        $sum = 0;
        foreach ($items as $item) {
            $sum += $this->analyze($item)['score'];
        }
        return (int) round($sum / count($items));
    }

    public function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 40 => 'D',
            default      => 'F',
        };
    }
}
