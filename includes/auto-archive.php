<?php
// Resolved reports move to Archived automatically after 30 days.
// No cron job needed: this runs (quickly) whenever one of the report pages is opened.

const AUTO_ARCHIVE_DAYS = 30;

function autoArchiveResolvedReports(PDO $pdo): int
{
    $cutoff = date('Y-m-d H:i:s', time() - AUTO_ARCHIVE_DAYS * 86400);
    $archived = 0;

    try {
        // updated_at changes only when the status changes, so it is the day the report was resolved.
        $find = $pdo->prepare(
            "SELECT incident_id, status FROM incidents
             WHERE status IN ('Resolved', 'Completed') AND updated_at <= ?"
        );
        $find->execute([$cutoff]);
        $due = $find->fetchAll();

        foreach ($due as $report) {
            $incidentId = (int) $report['incident_id'];

            // Credit the history line to the last official who updated this report.
            $last = $pdo->prepare(
                'SELECT updated_by FROM incident_status_history
                 WHERE incident_id = ? ORDER BY history_id DESC LIMIT 1'
            );
            $last->execute([$incidentId]);
            $updatedBy = $last->fetchColumn();

            try {
                $pdo->beginTransaction();

                $update = $pdo->prepare(
                    "UPDATE incidents SET status = 'Archived', updated_at = CURRENT_TIMESTAMP
                     WHERE incident_id = ? AND status IN ('Resolved', 'Completed')"
                );
                $update->execute([$incidentId]);

                if ($updatedBy !== false && $updatedBy !== null) {
                    $history = $pdo->prepare(
                        'INSERT INTO incident_status_history
                            (incident_id, updated_by, old_status, new_status, remarks)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $history->execute([
                        $incidentId,
                        (int) $updatedBy,
                        $report['status'] === 'Completed' ? 'Resolved' : $report['status'],
                        'Archived',
                        'Archived automatically after ' . AUTO_ARCHIVE_DAYS . ' days',
                    ]);
                }

                $pdo->commit();
                $archived++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Auto-archive failed for report ' . $incidentId . ': ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Auto-archive error: ' . $e->getMessage());
    }

    return $archived;
}
