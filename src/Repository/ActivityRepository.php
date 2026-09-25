<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Yönetim panelindeki işlemleri (ekle/düzenle/sil) denetim günlüğüne yazar.
 */
final class ActivityRepository extends BaseRepository
{
    public function log(?string $userName, string $action, ?string $entity = null, ?string $detail = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO activity_log (user_name, action, entity, detail, created_at)
                 VALUES (:u, :a, :e, :d, :c)'
            );
            $stmt->execute([
                'u' => $userName,
                'a' => $action,
                'e' => $entity,
                'd' => $detail !== null ? mb_substr($detail, 0, 400) : null,
                'c' => $this->now(),
            ]);
        } catch (\Throwable) {
            // Günlük yazımı asıl işlemi bozmamalı.
        }
    }

    public function recent(int $limit = 15): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM activity_log ORDER BY created_at DESC, id DESC LIMIT :l'
        );
        $stmt->bindValue('l', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function all(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM activity_log ORDER BY created_at DESC, id DESC LIMIT :l'
        );
        $stmt->bindValue('l', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
