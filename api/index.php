<?php
// Small RESTful API for incident reports. Every answer is JSON.
//
//   GET    /api/incident-types           list incident types
//   GET    /api/reports                  list reports (filters: status, q, page, per_page)
//   GET    /api/reports/{id}             one report with location, evidence and history
//   POST   /api/reports                  submit a new report
//   PATCH  /api/reports/{id}/status      change a report's status (officials only)
//
// Logged-in users only (same login session as the website). Residents only
// see their own reports; staff, admin and super admin see all reports.
// POST and PATCH need the header  X-CSRF-Token  (the website adds it for you).

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reports.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, array $payload, array $headers = []): void
{
    http_response_code($status);

    foreach ($headers as $header) {
        header($header);
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $message, array $extra = []): void
{
    respond($status, ['error' => $message] + $extra);
}

// ---- Which URL was asked for? -------------------------------------------
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!preg_match('#/api(?:/index\.php)?(/.*)?$#', $uriPath, $match)) {
    fail(404, 'Not found.');
}

$route = trim($match[1] ?? '', '/');
$parts = $route === '' ? [] : explode('/', $route);

// ---- Login and CSRF -------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    fail(401, 'Please log in first.');
}

$userId = (int) $_SESSION['user_id'];
$isStaff = isStaffRole(currentRole());

if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
    if (!csrfIsValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        fail(403, 'Your session expired. Please reload the page and try again.');
    }
}

// ---- Read the request body (JSON or form data) ----------------------------
function requestBody(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($type, 'application/json') !== false) {
        $data = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($data)) {
            fail(400, 'The request body must be valid JSON.');
        }

        return $data;
    }

    return $_POST;
}

function methodNotAllowed(array $allowed): void
{
    fail(405, 'Method not allowed. Use: ' . implode(', ', $allowed));
}

// ---- Helpers that shape the JSON ---------------------------------------
function shapeReport(array $row, bool $isStaff): array
{
    $report = [
        'incident_id' => (int) $row['incident_id'],
        'type' => $row['type_name'] ?? null,
        'incident_date' => $row['incident_date'],
        'location_description' => $row['location_description'],
        'status' => normalizeStatus($row['status']),
        'created_at' => $row['created_at'],
        'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
        'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
    ];

    if ($isStaff) {
        $report['reporter'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }

    return $report;
}

try {
    // GET /api
    if ($parts === []) {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        respond(200, ['data' => [
            'name' => 'Barangay Incident Reporting API',
            'endpoints' => [
                'GET /api/incident-types',
                'GET /api/reports',
                'GET /api/reports/{id}',
                'POST /api/reports',
                'PATCH /api/reports/{id}/status',
            ],
        ]]);
    }

    // GET /api/incident-types
    if ($parts === ['incident-types']) {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        $types = $pdo->query(
            'SELECT type_id, type_name FROM incident_types ORDER BY type_name'
        )->fetchAll();

        respond(200, ['data' => array_map(static fn($t) => [
            'type_id' => (int) $t['type_id'],
            'type_name' => $t['type_name'],
        ], $types)]);
    }

    if (($parts[0] ?? '') !== 'reports') {
        fail(404, 'Not found.');
    }

    // /api/reports
    if (count($parts) === 1) {
        if ($method === 'GET') {
            $status = (string) ($_GET['status'] ?? '');
            $search = trim((string) ($_GET['q'] ?? ''));
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));

            $where = [];
            $params = [];

            if (!$isStaff) {
                $where[] = 'i.reporter_id = ?';
                $params[] = $userId;
            }

            if ($status !== '') {
                if (!in_array($status, REPORT_STATUSES, true)) {
                    fail(422, 'Unknown status. Use one of: ' . implode(', ', REPORT_STATUSES));
                }

                if ($status === 'Resolved') {
                    $where[] = "i.status IN ('Resolved', 'Completed')";
                } else {
                    $where[] = 'i.status = ?';
                    $params[] = $status;
                }
            }

            if ($search !== '') {
                $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ?
                             OR i.location_description LIKE ? OR t.type_name LIKE ?)';
                $like = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like);
            }

            $from = 'FROM incidents i
                     LEFT JOIN incident_types t ON i.incident_type_id = t.type_id
                     LEFT JOIN users u ON i.reporter_id = u.user_id
                     LEFT JOIN incident_locations l ON l.incident_id = i.incident_id'
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

            $countStmt = $pdo->prepare('SELECT COUNT(*) ' . $from);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $listStmt = $pdo->prepare(
                'SELECT i.incident_id, i.incident_date, i.status, i.created_at,
                        i.location_description, t.type_name,
                        u.first_name, u.last_name, l.latitude, l.longitude '
                . $from
                . ' ORDER BY i.created_at DESC, i.incident_id DESC'
                . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
            );
            $listStmt->execute($params);

            respond(200, [
                'data' => array_map(
                    static fn($row) => shapeReport($row, $isStaff),
                    $listStmt->fetchAll()
                ),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ]);
        }

        if ($method === 'POST') {
            try {
                $incidentId = createIncidentReport($pdo, $userId, requestBody(), $_FILES);
            } catch (InvalidArgumentException | RuntimeException $e) {
                fail(422, $e->getMessage());
            }

            respond(
                201,
                ['data' => ['incident_id' => $incidentId, 'status' => 'Pending']],
                ['Location: reports/' . $incidentId]
            );
        }

        methodNotAllowed(['GET', 'POST']);
    }

    $incidentId = filter_var($parts[1], FILTER_VALIDATE_INT);

    if ($incidentId === false || $incidentId < 1) {
        fail(404, 'Incident report not found.');
    }

    // /api/reports/{id}
    if (count($parts) === 2) {
        if ($method !== 'GET') {
            methodNotAllowed(['GET']);
        }

        $stmt = $pdo->prepare(
            'SELECT i.incident_id, i.reporter_id, i.incident_date, i.description,
                    i.status, i.location_description, i.created_at, i.updated_at,
                    t.type_name, l.latitude, l.longitude, l.landmark, l.map_url,
                    u.first_name, u.last_name, u.email, u.contact_no, u.address
             FROM incidents i
             LEFT JOIN incident_types t ON i.incident_type_id = t.type_id
             LEFT JOIN incident_locations l ON l.incident_id = i.incident_id
             LEFT JOIN users u ON i.reporter_id = u.user_id
             WHERE i.incident_id = ? LIMIT 1'
        );
        $stmt->execute([$incidentId]);
        $row = $stmt->fetch();

        if (!$row) {
            fail(404, 'Incident report not found.');
        }

        if (!$isStaff && (int) $row['reporter_id'] !== $userId) {
            fail(403, 'You can only view reports that you submitted.');
        }

        $report = shapeReport($row, $isStaff) + [
            'description' => $row['description'],
            'landmark' => $row['landmark'],
            'map_url' => $row['map_url'],
            'updated_at' => $row['updated_at'],
        ];

        if ($isStaff) {
            $report['reporter'] = [
                'user_id' => (int) $row['reporter_id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'email' => $row['email'],
                'contact_no' => $row['contact_no'],
                'address' => $row['address'],
            ];
        }

        $evidence = $pdo->prepare(
            'SELECT photo_path, video_path FROM incident_evidence WHERE incident_id = ?'
        );
        $evidence->execute([$incidentId]);
        $report['evidence'] = $evidence->fetchAll();

        $history = $pdo->prepare(
            'SELECT old_status, new_status, remarks, updated_at
             FROM incident_status_history
             WHERE incident_id = ?
             ORDER BY updated_at DESC, history_id DESC'
        );
        $history->execute([$incidentId]);
        $report['status_history'] = $history->fetchAll();

        respond(200, ['data' => $report]);
    }

    // /api/reports/{id}/status
    if (count($parts) === 3 && $parts[2] === 'status') {
        if ($method !== 'PATCH' && $method !== 'PUT') {
            methodNotAllowed(['PATCH']);
        }

        if (!$isStaff) {
            fail(403, 'Only barangay officials can change a report status.');
        }

        $body = requestBody();

        try {
            $result = updateIncidentStatus(
                $pdo,
                $incidentId,
                (string) ($body['status'] ?? ''),
                (string) ($body['remarks'] ?? ''),
                $userId
            );
        } catch (OutOfBoundsException $e) {
            fail(404, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            fail(422, $e->getMessage());
        }

        respond(200, ['data' => $result]);
    }

    fail(404, 'Not found.');

} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    fail(500, 'Something went wrong. Please try again later.');
}
