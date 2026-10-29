// Tiny helper for the site's RESTful API (see API.md).
// Adds the CSRF token and sends/reads JSON for you.
(function () {
    function meta(name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    }

    function request(method, path, body) {
        var options = {
            method: method,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': meta('csrf-token') }
        };

        if (body instanceof FormData) {
            options.body = body;
        } else if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        return fetch(meta('api-base') + path, options).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (json) {
                if (!response.ok) {
                    var error = new Error(json.error || 'Something went wrong.');
                    error.status = response.status;
                    throw error;
                }
                return json.data;
            });
        });
    }

    window.BarangayApi = {
        get: function (path) { return request('GET', path); },
        post: function (path, body) { return request('POST', path, body); },
        patch: function (path, body) { return request('PATCH', path, body); }
    };

    // "Archive" buttons in the admin report list.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-archive]');
        if (!button) { return; }

        if (!window.confirm('Archive this resolved report?')) { return; }

        button.disabled = true;
        window.BarangayApi
            .patch('/reports/' + button.getAttribute('data-archive') + '/status',
                { status: 'Archived', remarks: 'Archived by official' })
            .then(function () { window.location.reload(); })
            .catch(function (error) {
                button.disabled = false;
                window.alert(error.message);
            });
    });
})();
