<?php
final class Workspace {
    public const KONVEKSI = 'konveksi';
    public const MARKETPLACE = 'marketplace';

    public static function allowed(): array {
        if (empty($_SESSION['user_id'])) return [];
        $role = (string)($_SESSION['role'] ?? '');
        $defaults = (stripos($role, 'OWNER') !== false || stripos($role, 'ADMIN') !== false)
            ? [self::KONVEKSI, self::MARKETPLACE]
            : [self::KONVEKSI];
        // Optional explicit grants take precedence once the reviewed DDL exists.
        try {
            $st = Database::pdo()->prepare('SELECT workspace_code, is_allowed FROM workspace_user_access WHERE user_key = ?');
            $st->execute([(string)$_SESSION['user_id']]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) return $defaults;
            $granted = array_column(array_filter($rows, static fn($row) => (int)$row['is_allowed'] === 1), 'workspace_code');
            return array_values(array_intersect([self::KONVEKSI, self::MARKETPLACE], $granted));
        } catch (PDOException $e) {
            return $defaults;
        }
    }

    public static function can(string $workspace): bool {
        return in_array($workspace, self::allowed(), true);
    }

    public static function active(): ?string {
        $active = $_SESSION['workspace'] ?? null;
        return is_string($active) && self::can($active) ? $active : null;
    }

    public static function require(string $workspace): void {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            throw new RuntimeException('Silakan login terlebih dahulu.');
        }
        if (!self::can($workspace) || self::active() !== $workspace) {
            http_response_code(403);
            throw new RuntimeException('Ruang kerja tidak diizinkan atau belum dipilih.');
        }
    }

    public static function csrf(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(): void {
        $value = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
        if (!hash_equals(self::csrf(), $value)) {
            http_response_code(403);
            throw new RuntimeException('Token keamanan tidak valid. Muat ulang halaman.');
        }
    }

    public static function select(string $workspace): void {
        if (!self::can($workspace)) {
            http_response_code(403);
            throw new RuntimeException('Anda tidak memiliki akses ke ruang kerja ini.');
        }
        $_SESSION['workspace'] = $workspace;
        // No workspace-specific selection is carried across the switch.
        unset($_SESSION['store_id'], $_SESSION['workspace_filters']);
    }
}
