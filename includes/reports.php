<?php
// Shared incident-report logic.
// Used by the web pages AND by the REST API (api/index.php), so the rules
// (validation, saving, status changes) live in one place.

const REPORT_STATUSES = ['Pending', 'Verified', 'In Progress', 'Resolved', 'Archived'];

// "Completed" is an older name for "Resolved".
function normalizeStatus(?string $status): string
{
    return $status === 'Completed' ? 'Resolved' : (string) $status;
}

// Statuses an official may switch to from the current one.
// A report can only be archived once it is Resolved, and an archived report
// can only be restored to Resolved.
function allowedNextStatuses(string $current): array
{
    $current = normalizeStatus($current);

    if ($current === 'Archived') {
        return ['Resolved'];
    }

    $options = ['Pending', 'Verified', 'In Progress', 'Resolved'];

    if ($current === 'Resolved') {
        $options[] = 'Archived';
    }

    return array_values(array_filter(
        $options,
        static fn(string $status) => $status !== $current
    ));
}

// Save one optional evidence file (photo or video) and return its web path.
function saveEvidenceFile(
    array $files,
    string $field,
    array $allowedMimes,
    string $folder
): ?string {
    if (
        !isset($files[$field]) ||
        $files[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($files[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('An evidence file could not be uploaded.');
    }

    if ($files[$field]['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Each evidence file must be 10 MB or smaller.');
    }

    $tmp = $files[$field]['tmp_name'];

    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('One of the evidence files has an unsupported file type.');
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    $extension = $extensions[$mime] ?? null;

    if ($extension === null) {
        throw new RuntimeException('Unsupported evidence format.');
    }

    $absoluteFolder = dirname(__DIR__) . '/' . trim($folder, '/') . '/';

    if (
        !is_dir($absoluteFolder) &&
        !mkdir($absoluteFolder, 0755, true) &&
        !is_dir($absoluteFolder)
    ) {
        throw new RuntimeException('The upload folder could not be created.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

    if (!move_uploaded_file($tmp, $absoluteFolder . $fileName)) {
        throw new RuntimeException('The evidence file could not be saved.');
    }

    return trim($folder, '/') . '/' . $fileName;
}

// Accepts "2026-10-22T07:00", "2026-10-22 07:00" or "2026-10-22".
function parseIncidentDate(string $value): ?DateTime
{
    foreach (['!Y-m-d\TH:i', '!Y-m-d H:i', '!Y-m-d'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        $errors = DateTime::getLastErrors();

        if (
            $date !== false &&
            ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) &&
            $date->format(ltrim($format, '!')) === $value
        ) {
            return $date;
        }
    }

    return null;
}

// Validate and save a new incident report. Returns the new incident id.
// Throws InvalidArgumentException (bad input) or RuntimeException (file problem).
function createIncidentReport(PDO $pdo, int $reporterId, array $input, array $files = []): int
{
    $typeId = (int) ($input['incident_type'] ?? $input['incident_type_id'] ?? 0);
    $description = trim((string) ($input['description'] ?? ''));
    $incidentDate = trim((string) ($input['incident_date'] ?? ''));
    $landmark = trim((string) ($input['landmark'] ?? ''));
    $locationDescription = trim((string) ($input['location_description'] ?? ''));
    $latitudeRaw = trim((string) ($input['latitude'] ?? ''));
    $longitudeRaw = trim((string) ($input['longitude'] ?? ''));

    $dateObject = parseIncidentDate($incidentDate);

    if (
        $typeId < 1 ||
        $description === '' ||
        $dateObject === null ||
        $locationDescription === ''
    ) {
        throw new InvalidArgumentException(
            'Please complete the incident type, date and time, description, and location description.'
        );
    }

    if ($latitudeRaw === '' || $longitudeRaw === '') {
        throw new InvalidArgumentException(
            'Please pin the incident location on the map. Use "Use My Current Location" or tap the map.'
        );
    }

    $latitude = filter_var($latitudeRaw, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($longitudeRaw, FILTER_VALIDATE_FLOAT);

    if (
        $latitude === false || $latitude < -90 || $latitude > 90 ||
        $longitude === false || $longitude < -180 || $longitude > 180
    ) {
        throw new InvalidArgumentException(
            'Please provide a valid latitude and longitude, or leave both blank.'
        );
    }

    $typeCheck = $pdo->prepare('SELECT type_name FROM incident_types WHERE type_id = ? LIMIT 1');
    $typeCheck->execute([$typeId]);
    $typeRow = $typeCheck->fetch();

    if (!$typeRow) {
        throw new InvalidArgumentException('Please choose a valid incident type.');
    }

    // "Other" needs a short label. It is saved as the first line of the
    // description, so no database change is needed.
    if (strtolower(trim((string) $typeRow['type_name'])) === 'other') {
        $otherType = trim((string) ($input['other_type'] ?? ''));

        if ($otherType === '') {
            throw new InvalidArgumentException('Please specify the incident type for "Other".');
        }

        $description = 'Other: ' . mb_substr($otherType, 0, 60) . "\n" . $description;
    }

    $savedFiles = [];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO incidents
                (reporter_id, incident_type_id, location_description,
                 incident_date, description, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $reporterId,
            $typeId,
            $locationDescription,
            $dateObject->format('Y-m-d'),
            $description,
            'Pending',
        ]);

        $incidentId = (int) $pdo->lastInsertId();

        $mapUrl = 'https://www.google.com/maps?q=' .
            rawurlencode((float) $latitude . ',' . (float) $longitude);

        $locationStmt = $pdo->prepare(
            'INSERT INTO incident_locations
                (incident_id, latitude, longitude, landmark, location_description, map_url)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $locationStmt->execute([
            $incidentId,
            (float) $latitude,
            (float) $longitude,
            $landmark !== '' ? $landmark : null,
            $locationDescription,
            $mapUrl,
        ]);

        $folder = 'uploads/incident-images';

        $photoPath = saveEvidenceFile(
            $files,
            'incident_photo',
            ['image/jpeg', 'image/png', 'image/webp'],
            $folder
        );

        if ($photoPath !== null) {
            $savedFiles[] = $photoPath;
        }

        $videoPath = saveEvidenceFile(
            $files,
            'incident_video',
            ['video/mp4', 'video/webm'],
            $folder
        );

        if ($videoPath !== null) {
            $savedFiles[] = $videoPath;
        }

        if ($photoPath !== null || $videoPath !== null) {
            $evidenceStmt = $pdo->prepare(
                'INSERT INTO incident_evidence (incident_id, photo_path, video_path)
                 VALUES (?, ?, ?)'
            );
            $evidenceStmt->execute([$incidentId, $photoPath, $videoPath]);
        }

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Do not keep files of a report that was not saved.
        foreach ($savedFiles as $path) {
            @unlink(dirname(__DIR__) . '/' . $path);
        }

        throw $e;
    }

    // The report is saved. Now ask the AI for a suggested type (never fails the report).
    require_once __DIR__ . '/ai.php';
    saveAiClassification($pdo, $incidentId, $description, $photoPath);

    return $incidentId;
}

// Change a report's status and record it in incident_status_history.
// Throws OutOfBoundsException (report not found) or InvalidArgumentException.
function updateIncidentStatus(
    PDO $pdo,
    int $incidentId,
    string $newStatus,
    string $remarks,
    int $updatedBy
): array {
    $find = $pdo->prepare('SELECT status FROM incidents WHERE incident_id = ? LIMIT 1');
    $find->execute([$incidentId]);
    $current = $find->fetchColumn();

    if ($current === false) {
        throw new OutOfBoundsException('Incident report not found.');
    }

    $newStatus = trim($newStatus);

    if (!in_array($newStatus, REPORT_STATUSES, true)) {
        throw new InvalidArgumentException('Please select a valid status.');
    }

    if (normalizeStatus($current) === $newStatus) {
        throw new InvalidArgumentException('Choose a status different from the current status.');
    }

    if (!in_array($newStatus, allowedNextStatuses($current), true)) {
        throw new InvalidArgumentException(
            $newStatus === 'Archived'
                ? 'Only Resolved reports can be archived.'
                : 'This status change is not allowed.'
        );
    }

    try {
        $pdo->beginTransaction();

        $update = $pdo->prepare(
            'UPDATE incidents SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE incident_id = ?'
        );
        $update->execute([$newStatus, $incidentId]);

        $history = $pdo->prepare(
            'INSERT INTO incident_status_history
                (incident_id, updated_by, old_status, new_status, remarks)
             VALUES (?, ?, ?, ?, ?)'
        );
        $history->execute([$incidentId, $updatedBy, $current, $newStatus, trim($remarks)]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    return [
        'incident_id' => $incidentId,
        'old_status' => $current,
        'new_status' => $newStatus,
    ];
}
