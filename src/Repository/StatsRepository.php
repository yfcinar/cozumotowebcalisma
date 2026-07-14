<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Yönetim paneli dashboard için toplu istatistikler ve zaman serileri üretir.
 */
final class StatsRepository extends BaseRepository
{
    /** Tek bir tablodaki satır sayısı (opsiyonel WHERE ile). */
    public function count(string $table, string $where = ''): int
    {
        $sql = "SELECT COUNT(*) FROM {$table}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /**
     * Son N günlük mesaj sayıları (gün => adet). Grafik için kullanılır.
     * Eksik günler 0 ile doldurulur.
     */
    public function messagesPerDay(int $days = 30): array
    {
        $rows = $this->pdo->query(
            "SELECT substr(created_at, 1, 10) AS d, COUNT(*) AS c
             FROM messages GROUP BY substr(created_at, 1, 10)"
        )->fetchAll();

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r['d']] = (int) $r['c'];
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $out[] = ['date' => $day, 'count' => $byDay[$day] ?? 0];
        }
        return $out;
    }

    /** Bugün, bu hafta, bu ay gelen mesaj sayıları. */
    public function messageTotals(): array
    {
        $today = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-6 days'));
        $monthAgo = date('Y-m-d', strtotime('-29 days'));

        return [
            'total'  => $this->count('messages'),
            'unread' => $this->count('messages', 'is_read = 0'),
            'today'  => (int) $this->scalarPrepared(
                "SELECT COUNT(*) FROM messages WHERE substr(created_at,1,10) = :d", ['d' => $today]
            ),
            'week'   => (int) $this->scalarPrepared(
                "SELECT COUNT(*) FROM messages WHERE substr(created_at,1,10) >= :d", ['d' => $weekAgo]
            ),
            'month'  => (int) $this->scalarPrepared(
                "SELECT COUNT(*) FROM messages WHERE substr(created_at,1,10) >= :d", ['d' => $monthAgo]
            ),
        ];
    }

    /** İçerik dağılımı (grafik için). */
    public function contentBreakdown(): array
    {
        return [
            'services'  => $this->count('services'),
            'districts' => $this->count('districts'),
            'pages'     => $this->count('pages'),
            'faqs'      => $this->count('faqs'),
            'gallery'   => $this->count('gallery'),
        ];
    }

    private function scalarPrepared(string $sql, array $params): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
