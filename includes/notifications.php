<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/reports.php';

const NOTIFICATION_DAYS = 30;
const NOTIFICATION_LIMIT = 100;
const NOTIFICATION_ACK_KEEP = 1000;

function notificationNormalizeState(PDO $pdo, $state): array
{
    if (!is_array($state) || !isset($state['baseline'])) {
        $baseline = (int) $pdo->query('SELECT COALESCE(MAX(incident_id), 0) FROM incidents')->fetchColumn();

        return ['baseline' => $baseline, 'acked' => []];
    }

    $state['baseline'] = (int) $state['baseline'];
    $state['acked'] = array_values(array_map('intval', (array) ($state['acked'] ?? [])));

    return $state;
}

function notificationStateUpdate(PDO $pdo, int $userId, callable $change): array
{
    $dir = dirname(__DIR__) . '/uploads/notifications';
    $file = $dir . '/user-' . $userId . '.json';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents(
            $dir . '/.htaccess',
            "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
        );
    }

    $handle = is_dir($dir) ? @fopen($file, 'c+') : false;

    if ($handle === false) {
        $state = notificationNormalizeState($pdo, $_SESSION['notification_state'][$userId] ?? null);
        $state = $change($state);
        $_SESSION['notification_state'][$userId] = $state;

        return $state;
    }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = notificationNormalizeState($pdo, json_decode((string) $raw, true));
    $state = $change($state);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $state;
}

function notificationLevelOf(?string $typeName): int
{
    $name = strtolower(trim((string) $typeName));

    if ($name === '') {
        return 0;
    }

    if (strpos($name, 'fire') !== false || strpos($name, 'accident') !== false) {
        return 2;
    }

    if (strpos($name, 'flood') !== false) {
        return 1;
    }

    return 0;
}

function notificationPriority(?string $typeName, ?string $aiType, $aiScore): array
{
    $level = notificationLevelOf($typeName);
    $raisedByAi = false;

    if ($aiType !== null && (float) $aiScore >= 0.8) {
        $aiLevel = notificationLevelOf($aiType);

        if ($aiLevel > $level) {
            $level = $aiLevel;
            $raisedByAi = true;
        }
    }

    $names = [
        0 => ['normal', 'Normal Alert'],
        1 => ['high', 'High Alert'],
        2 => ['critical', 'Critical Alert'],
    ];

    return [
        'key' => $names[$level][0],
        'label' => $names[$level][1],
        'raised_by_ai' => $raisedByAi,
    ];
}

function fetchNotifications(PDO $pdo, int $userId): array
{
    $state = notificationStateUpdate($pdo, $userId, static fn(array $s) => $s);
    $acked = array_flip($state['acked']);
    $cutoff = date('Y-m-d H:i:s', time() - NOTIFICATION_DAYS * 86400);

    $stmt = $pdo->prepare(
        'SELECT i.incident_id, i.status, i.created_at, i.incident_date,
                i.location_description, i.description, t.type_name,
                (SELECT c.predicted_type FROM incident_ai_classifications c
                 WHERE c.incident_id = i.incident_id
                 ORDER BY c.classification_id DESC LIMIT 1) AS ai_type,
                (SELECT c.confidence_score FROM incident_ai_classifications c
                 WHERE c.incident_id = i.incident_id
                 ORDER BY c.classification_id DESC LIMIT 1) AS ai_score
         FROM incidents i
         LEFT JOIN incident_types t ON i.incident_type_id = t.type_id
         WHERE i.reporter_id <> ? AND i.created_at >= ?
         ORDER BY i.incident_id DESC
         LIMIT ' . NOTIFICATION_LIMIT
    );
    $stmt->execute([$userId, $cutoff]);

    $items = [];
    $unread = 0;
    $latest = 0;

    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['incident_id'];
        [$typeLabel] = splitOtherType($row['type_name'] ?? null, (string) ($row['description'] ?? ''));
        $priority = notificationPriority(
            $row['type_name'] ?? null,
            $row['ai_type'] ?? null,
            $row['ai_score'] ?? null
        );
        $read = isset($acked[$id]) || $id <= $state['baseline'];
        $created = strtotime((string) $row['created_at']);
        $incidentDate = strtotime((string) $row['incident_date']);

        if (!$read) {
            $unread++;
        }

        $latest = max($latest, $id);

        $items[] = [
            'incident_id' => $id,
            'type' => $typeLabel !== '' ? $typeLabel : 'Uncategorized',
            'location' => (string) ($row['location_description'] ?? ''),
            'incident_date' => $incidentDate ? date('M j, Y', $incidentDate) : (string) $row['incident_date'],
            'reported_at' => $created ? date('M j, Y g:i A', $created) : '',
            'status' => normalizeStatus($row['status'] ?? ''),
            'priority' => $priority['key'],
            'priority_label' => $priority['label'],
            'raised_by_ai' => $priority['raised_by_ai'],
            'ai_type' => $row['ai_type'] ?? null,
            'ai_confidence' => $row['ai_score'] !== null ? (int) round(((float) $row['ai_score']) * 100) : null,
            'read' => $read,
        ];
    }

    return ['unread_count' => $unread, 'latest_id' => $latest, 'items' => $items];
}

function acknowledgeReports(PDO $pdo, int $userId, array $ids): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id) => $id > 0)));

    if ($ids === []) {
        return;
    }

    notificationStateUpdate($pdo, $userId, static function (array $state) use ($ids): array {
        $merged = array_values(array_unique(array_merge($state['acked'], $ids)));
        rsort($merged);
        $state['acked'] = array_slice($merged, 0, NOTIFICATION_ACK_KEEP);

        return $state;
    });
}

function acknowledgeAllReports(PDO $pdo, int $userId): void
{
    $data = fetchNotifications($pdo, $userId);
    $ids = [];

    foreach ($data['items'] as $item) {
        if (!$item['read']) {
            $ids[] = $item['incident_id'];
        }
    }

    acknowledgeReports($pdo, $userId, $ids);
}
