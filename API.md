# Barangay Incident Reporting – REST API (small version)

All answers are JSON: `{"data": ...}` on success, `{"error": "..."}` on failure.
You must be logged in (same session cookie as the website). Residents only
see their own reports; staff / admin / super admin see all.
`POST` and `PATCH` need the header `X-CSRF-Token` (the website sends it for you
through `js/api.js`).

| Method | URL | What it does |
|---|---|---|
| GET | `/api/incident-types` | List incident types |
| GET | `/api/reports?status=&q=&page=&per_page=` | List reports (status: Pending, Verified, In Progress, Resolved, Archived) |
| GET | `/api/reports/{id}` | One report: location, evidence, status history (+ reporter info for officials) |
| POST | `/api/reports` | Submit a report (multipart form or JSON). Needs incident_type_id, description, incident_date, location_description, latitude, longitude. Optional files: photo, video. Returns 201 |
| PATCH | `/api/reports/{id}/status` | Officials only. Body: `{"status":"Verified","remarks":"..."}` |

Status codes: 200, 201, 400, 401 (not logged in), 403 (not allowed / bad CSRF),
404, 405, 422 (invalid input), 500.

Archive rule: a report can be archived only when it is Resolved; an archived
report can only be restored to Resolved.

Needs `api/.htaccess` (URL rewrite) – works on Namecheap/cPanel (Apache).
