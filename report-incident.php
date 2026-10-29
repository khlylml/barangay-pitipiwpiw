<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/reports.php';

// Login required to submit an incident report.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$types = [];

// Escape output before displaying it in HTML.
function escapeReport(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Load available incident types using PDO.
try {
    $typeStmt = $pdo->query(
        'SELECT type_id, type_name
         FROM incident_types
         ORDER BY type_name'
    );

    $types = $typeStmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Unable to load incident types. Please try again later.';
}

// Plain form submit (used when JavaScript is off). With JavaScript on, the
// page sends the report to the REST API instead: POST api/reports.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    try {
        $incidentId = createIncidentReport(
            $pdo,
            (int) $_SESSION['user_id'],
            $_POST,
            $_FILES
        );

        header(
            'Location: report-confirmation.php?id=' .
            $incidentId
        );
        exit;

    } catch (InvalidArgumentException | RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $error = 'The report could not be saved. Please check the database tables and try again.';
    }
}

$pageTitle = 'Report an Incident';
$extraHead = '<link rel="stylesheet" href="vendor/leaflet/leaflet.css">';
require 'includes/header.php';
?>

<section class="page-heading">
    <h1>Report an Incident</h1>
    <p>Provide accurate information so the report can be reviewed.</p>
</section>

<?php if ($error !== ''): ?>
    <p class="alert alert-error">
        <?= escapeReport($error) ?>
    </p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="form-layout">

    <div class="form-field">
        <label for="incident_type">Incident Type</label>

        <select id="incident_type" name="incident_type" required>
            <option value="">Select incident type</option>

            <?php foreach ($types as $type): ?>
                <option
                    value="<?= (int) $type['type_id'] ?>"
                    <?= (string) ($_POST['incident_type'] ?? '') ===
                        (string) $type['type_id'] ? 'selected' : '' ?>
                >
                    <?= escapeReport($type['type_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-field" id="otherTypeField" hidden>
        <label for="other_type">Please specify</label>

        <input
            type="text"
            id="other_type"
            name="other_type"
            maxlength="60"
            placeholder="e.g. Illegal dumping"
            value="<?= escapeReport($_POST['other_type'] ?? '') ?>"
        >
    </div>

    <div class="form-field">
        <label for="incident_date">Date and Time of Incident</label>

        <input
            type="datetime-local"
            id="incident_date"
            name="incident_date"
            value="<?= escapeReport($_POST['incident_date'] ?? '') ?>"
            required
        >
    </div>

    <div class="form-field field-full">
        <label for="description">Incident Description</label>

        <textarea
            id="description"
            name="description"
            rows="4"
            required
        ><?= escapeReport($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-field field-full">
        <label for="incident_photo">Photo Evidence (optional)</label>

        <div class="evidence-actions">
            <button type="button" class="button button-primary" id="openCamera">
                Take Photo
            </button>

            <button type="button" class="button button-secondary" id="chooseFile">
                Choose from Gallery
            </button>

            <button type="button" class="button button-outline" id="removePhoto" hidden>
                Remove Photo
            </button>
        </div>

        <!-- This is the file that gets submitted with the form. -->
        <input
            type="file"
            id="incident_photo"
            name="incident_photo"
            accept="image/jpeg,image/png,image/webp"
            hidden
        >

        <!-- Opens the phone's own camera app if the live camera is not available. -->
        <input
            type="file"
            id="cameraFallback"
            accept="image/*"
            capture="environment"
            hidden
        >

        <small>JPG, PNG, or WebP. Large photos are resized automatically.</small>

        <img
            id="photoPreview"
            class="photo-preview"
            alt="Photo preview"
        >
    </div>

    <div class="form-field">
        <label for="incident_video">Video Evidence (optional)</label>

        <input
            type="file"
            id="incident_video"
            name="incident_video"
            accept="video/mp4,video/webm"
        >

        <small>MP4 or WebM. Maximum 10 MB.</small>
    </div>

    <div class="form-field field-full">
        <label>Incident Location</label>

        <button
            type="button"
            class="button button-secondary"
            id="getLocation"
        >
            Use My Current Location
        </button>

        <p id="locationMessage" aria-live="polite">
            Finding your location...
        </p>

        <input
            type="hidden"
            name="latitude"
            id="latitude"
            value="<?= escapeReport($_POST['latitude'] ?? '') ?>"
        >

        <input
            type="hidden"
            name="longitude"
            id="longitude"
            value="<?= escapeReport($_POST['longitude'] ?? '') ?>"
        >

        <div
            id="locationMap"
            class="location-map"
            role="application"
            aria-label="Map. Tap anywhere to place the incident pin."
        ></div>


        <small>
            The pin is where the incident happened. Drag the pin or tap the
            map to adjust it.
        </small>
    </div>

    <div class="form-field">
        <label for="landmark">Nearby Landmark (optional)</label>

        <input
            type="text"
            id="landmark"
            name="landmark"
            value="<?= escapeReport($_POST['landmark'] ?? '') ?>"
            placeholder="e.g. near the barangay hall"
        >
    </div>

    <div class="form-field">
        <label for="location_description">Location Description</label>

        <input
            type="text"
            id="location_description"
            name="location_description"
            value="<?= escapeReport($_POST['location_description'] ?? '') ?>"
            placeholder="Street, purok, or nearby area"
            required
        >
    </div>

    <div class="field-full form-actions">
        <button type="submit" class="button button-primary">
            Submit Report
        </button>

        <a class="button button-secondary" href="dashboard.php">
            Cancel
        </a>
    </div>

</form>

<dialog id="reviewDialog" class="review-dialog" aria-labelledby="reviewTitle">
    <h2 id="reviewTitle">Review your report</h2>
    <p>Please check that everything is correct before you submit.</p>

    <dl class="review-list" id="reviewList"></dl>
    <img id="reviewPhoto" class="review-photo" alt="Attached photo" hidden>

    <p id="reviewError" class="alert alert-error" role="alert" hidden></p>

    <div class="form-actions">
        <button type="button" class="button button-primary" id="confirmSubmit">
            Confirm and Submit
        </button>
        <button type="button" class="button button-secondary" id="editReport">
            Edit Report
        </button>
    </div>
</dialog>

<dialog id="cameraDialog" class="camera-dialog" aria-label="Take a photo of the incident">
    <div class="camera-stage">
        <video id="cameraVideo" autoplay playsinline muted></video>
    </div>

    <p id="cameraMessage" class="camera-message" aria-live="polite"></p>

    <div class="camera-controls">
        <button type="button" class="button button-primary" id="captureBtn">
            Capture
        </button>

        <button type="button" class="button button-secondary" id="flipCamera">
            Flip Camera
        </button>

        <button type="button" class="button button-outline" id="closeCamera">
            Cancel
        </button>
    </div>
</dialog>

<script src="vendor/leaflet/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const locationButton = document.getElementById('getLocation');
    const message = document.getElementById('locationMessage');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');

    const photoInput = document.getElementById('incident_photo');
    const cameraFallback = document.getElementById('cameraFallback');
    const photoPreview = document.getElementById('photoPreview');
    const openCamera = document.getElementById('openCamera');
    const chooseFile = document.getElementById('chooseFile');
    const removePhoto = document.getElementById('removePhoto');

    const dialog = document.getElementById('cameraDialog');
    const video = document.getElementById('cameraVideo');
    const cameraMessage = document.getElementById('cameraMessage');
    const captureBtn = document.getElementById('captureBtn');
    const flipCamera = document.getElementById('flipCamera');
    const closeCamera = document.getElementById('closeCamera');

    const MAX_SIDE = 1600;
    let stream = null;
    let facing = 'environment';
    let previewUrl = null;

    /* ---------------- Location map ---------------- */
    const locationInput = document.getElementById('location_description');

    const map = L.map('locationMap').setView([16.0433, 120.3333], 14);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    let pinTimer = null;
    let addressAutoFilled = false;

    locationInput.addEventListener('input', function () {
        addressAutoFilled = false;
    });

    // Suggest a street/area name for the pin (only if the field is empty).
    async function suggestAddress(lat, lng) {
        if (locationInput.value.trim() !== '' && !addressAutoFilled) {
            return;
        }

        try {
            const response = await fetch(
                'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18' +
                '&accept-language=en&lat=' + lat + '&lon=' + lng
            );
            const data = await response.json();
            const a = data.address || {};

            const parts = [
                a.road || a.pedestrian || a.path,
                a.neighbourhood || a.suburb || a.quarter || a.village,
                a.city || a.town || a.municipality || a.county
            ].filter(Boolean);

            if (
                parts.length &&
                (locationInput.value.trim() === '' || addressAutoFilled)
            ) {
                locationInput.value = parts.join(', ');
                addressAutoFilled = true;
            }
        } catch (error) {
            // Typing the location manually still works.
        }
    }

    function setPin(lat, lng, zoom) {
        latitudeInput.value = lat.toFixed(6);
        longitudeInput.value = lng.toFixed(6);

        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                const point = marker.getLatLng();
                setPin(point.lat, point.lng);
            });
        } else {
            marker.setLatLng([lat, lng]);
        }

        if (zoom) {
            map.setView([lat, lng], zoom);
        }

        message.textContent =
            'Pin set at ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '.';

        clearTimeout(pinTimer);
        pinTimer = setTimeout(function () {
        }, 500);
    }

    map.on('click', function (event) {
        setPin(event.latlng.lat, event.latlng.lng);
    });

    function getCurrentLocation() {
        if (!navigator.geolocation) {
            message.textContent =
                'Location is not available on this browser. Tap the map to place the pin.';
            return;
        }

        message.textContent = 'Getting your location...';

        navigator.geolocation.getCurrentPosition(
            function (position) {
                setPin(position.coords.latitude, position.coords.longitude, 17);
            },
            function () {
                message.textContent =
                    'We could not get your location. Tap the map to place the pin where the incident happened.';
            },
            {
                enableHighAccuracy: true,
                timeout: 10000
            }
        );
    }

    locationButton.addEventListener('click', getCurrentLocation);

    // Keep the pin if the form is shown again after an error.
    const savedLat = parseFloat(latitudeInput.value);
    const savedLng = parseFloat(longitudeInput.value);

    if (!Number.isNaN(savedLat) && !Number.isNaN(savedLng)) {
        setPin(savedLat, savedLng, 17);
    } else {
        getCurrentLocation();
    }

    /* ---------------- Photo helpers ---------------- */

    // Shrink big phone photos so they upload quickly and stay under the limit.
    async function shrinkImage(file) {
        try {
            const bitmap = await createImageBitmap(file, {
                imageOrientation: 'from-image'
            });
            const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));

            if (scale === 1 && file.size <= 1.5 * 1024 * 1024) {
                bitmap.close();
                return file;
            }

            const canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            bitmap.close();

            const blob = await new Promise(function (resolve) {
                canvas.toBlob(resolve, 'image/jpeg', 0.85);
            });

            if (!blob) {
                return file;
            }

            return new File([blob], 'incident-photo-' + Date.now() + '.jpg', {
                type: 'image/jpeg'
            });
        } catch (error) {
            return file;
        }
    }

    function showPhoto(file) {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        if (!file) {
            previewUrl = null;
            photoPreview.removeAttribute('src');
            photoPreview.style.display = 'none';
            removePhoto.hidden = true;
            return;
        }

        previewUrl = URL.createObjectURL(file);
        photoPreview.src = previewUrl;
        photoPreview.style.display = 'block';
        removePhoto.hidden = false;
        openCamera.textContent = 'Retake Photo';
    }

    async function setPhoto(file) {
        const finalFile = await shrinkImage(file);
        const transfer = new DataTransfer();
        transfer.items.add(finalFile);
        photoInput.files = transfer.files;
        showPhoto(finalFile);
    }

    /* ---------------- Live camera ---------------- */
    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }
        video.srcObject = null;
    }

    async function startCamera() {
        stopCamera();
        cameraMessage.textContent = 'Starting camera...';

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: facing },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                },
                audio: false
            });

            video.srcObject = stream;
            cameraMessage.textContent = 'Point at the incident, then tap Capture.';
        } catch (error) {
            // Permission denied or no camera: use the phone's camera app instead.
            if (dialog.open) {
                dialog.close();
            }
            cameraFallback.click();
        }
    }

    openCamera.addEventListener('click', function () {
        const liveCameraSupported =
            navigator.mediaDevices &&
            navigator.mediaDevices.getUserMedia &&
            window.isSecureContext &&
            typeof dialog.showModal === 'function';

        if (!liveCameraSupported) {
            // e.g. page opened over plain http:// on a phone.
            cameraFallback.click();
            return;
        }

        dialog.showModal();
        startCamera();
    });

    flipCamera.addEventListener('click', function () {
        facing = facing === 'environment' ? 'user' : 'environment';
        startCamera();
    });

    captureBtn.addEventListener('click', function () {
        if (!video.videoWidth) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        canvas.toBlob(function (blob) {
            if (!blob) {
                cameraMessage.textContent = 'Could not capture the photo. Please try again.';
                return;
            }

            const file = new File([blob], 'incident-photo-' + Date.now() + '.jpg', {
                type: 'image/jpeg'
            });

            dialog.close();
            setPhoto(file);
        }, 'image/jpeg', 0.9);
    });

    closeCamera.addEventListener('click', function () {
        dialog.close();
    });

    dialog.addEventListener('close', stopCamera);

    /* ---------------- Gallery / fallback / remove ---------------- */
    chooseFile.addEventListener('click', function () {
        photoInput.click();
    });

    photoInput.addEventListener('change', function () {
        const file = photoInput.files[0];

        if (file) {
            setPhoto(file);
        } else {
            showPhoto(null);
        }
    });

    cameraFallback.addEventListener('change', function () {
        const file = cameraFallback.files[0];

        if (file) {
            setPhoto(file);
        }

        cameraFallback.value = '';
    });

    removePhoto.addEventListener('click', function () {
        photoInput.value = '';
        showPhoto(null);
        openCamera.textContent = 'Take Photo';
    });
});
</script>

<script>
// 1) Show a review step first (User reviews report information).
// 2) After "Confirm and Submit", send the report to the REST API (POST api/reports).
// If JavaScript is off, the normal form POST still works.
(function () {
    const form = document.querySelector('form.form-layout');
    if (!form || !window.fetch || !window.FormData) { return; }

    const dialog = document.getElementById('reviewDialog');
    const list = document.getElementById('reviewList');
    const reviewPhoto = document.getElementById('reviewPhoto');
    const reviewError = document.getElementById('reviewError');
    const confirmButton = document.getElementById('confirmSubmit');
    const editButton = document.getElementById('editReport');
    const photoPreview = document.getElementById('photoPreview');

    if (!dialog || typeof dialog.showModal !== 'function') { return; }

    function addRow(label, value) {
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = value && value.trim() !== '' ? value : '-';
        list.appendChild(dt);
        list.appendChild(dd);
    }

    function isOther() {
        const t = form.elements['incident_type'];
        const o = t.options[t.selectedIndex];
        return !!o && o.text.trim().toLowerCase() === 'other';
    }

    function syncOther() {
        const box = document.getElementById('otherTypeField');
        box.hidden = !isOther();
        form.elements['other_type'].required = isOther();
    }

    form.elements['incident_type'].addEventListener('change', syncOther);
    syncOther();

    function fillReview() {
        const type = form.elements['incident_type'];
        const typeText = type.options[type.selectedIndex]
            ? type.options[type.selectedIndex].text : '';
        const when = form.elements['incident_date'].value.replace('T', ' ');
        const video = form.elements['incident_video'];

        list.textContent = '';
        addRow('Incident type', typeText);
        if (isOther()) { addRow('Specified type', form.elements['other_type'].value); }
        addRow('Date and time', when);
        addRow('Description', form.elements['description'].value);
        addRow('Location description', form.elements['location_description'].value);
        addRow('Landmark', form.elements['landmark'].value);
        addRow('Pin on map',
            form.elements['latitude'].value + ', ' + form.elements['longitude'].value);
        addRow('Video', video && video.files.length ? video.files[0].name : '');

        const hasPhoto = photoPreview && photoPreview.getAttribute('src');
        reviewPhoto.hidden = !hasPhoto;
        if (hasPhoto) { reviewPhoto.src = photoPreview.src; }

        reviewError.hidden = true;
        confirmButton.disabled = false;
    }

    form.addEventListener('submit', function (event) {
        if (!window.BarangayApi || !form.checkValidity()) { return; }

        event.preventDefault();
        fillReview();
        dialog.showModal();
    });

    editButton.addEventListener('click', function () {
        dialog.close();
    });

    confirmButton.addEventListener('click', function () {
        confirmButton.disabled = true;
        reviewError.hidden = true;

        window.BarangayApi.post('/reports', new FormData(form))
            .then(function (data) {
                window.location.href =
                    'report-confirmation.php?id=' + data.incident_id;
            })
            .catch(function (error) {
                confirmButton.disabled = false;
                reviewError.textContent = error.message;
                reviewError.hidden = false;
            });
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
