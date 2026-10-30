<?php

namespace App\Audit;

use App\Auth\LegacyUser;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class AuditLogger
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        LegacyUser $user,
        string $module,
        string $action,
        ?string $recordId,
        ?string $referenceId,
        ?array $before,
        ?array $after,
        string $notes = '',
    ): void {
        if (! Schema::hasTable('system_audit_logs')) {
            throw new RuntimeException('Audit table belum tersedia.');
        }

        $userId = $user->getSource() === 'auth_users_v2'
            ? 'AUTHV2-'.$user->getLegacyUserId()
            : $user->getLegacyUserId();

        $this->database->connection()->table('system_audit_logs')->insert([
            'event_id' => 'AUD-'.now()->format('YmdHis').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
            'event_time' => now(),
            'user_id' => $userId,
            'username' => $user->getUsername(),
            'module' => $module,
            'action' => $action,
            'record_id' => $recordId,
            'reference_id' => $referenceId,
            'before_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'after_json' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'status' => 'SUCCESS',
            'notes' => $notes,
        ]);
    }
}
