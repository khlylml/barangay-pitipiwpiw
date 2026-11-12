(function () {
    var api = window.BarangayApi;
    if (!api) { return; }

    var apiBase = (document.querySelector('meta[name="api-base"]') || {}).content || '';
    var siteBase = apiBase.replace(/api$/, '');
    var userId = document.body.getAttribute('data-user-id') || '0';
    var storageKey = 'notif-popup-' + userId;
    var baseTitle = document.title;
    var failures = 0;
    var timer = null;
    var stack = null;

    function readLast() {
        try {
            var value = localStorage.getItem(storageKey);
            return value === null ? null : parseInt(value, 10);
        } catch (e) {
            return null;
        }
    }

    function saveLast(value) {
        try { localStorage.setItem(storageKey, String(value)); } catch (e) {}
    }

    var lastPopupId = readLast();

    function ensureStack() {
        if (stack) { return stack; }
        stack = document.createElement('div');
        stack.className = 'notif-stack';
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
        return stack;
    }

    function updateBadges(count) {
        var text = count > 99 ? '99+' : String(count);

        document.querySelectorAll('[data-notif-count]').forEach(function (badge) {
            badge.textContent = text;
            badge.hidden = count === 0;
        });

        document.title = count > 0 ? '(' + count + ') ' + baseTitle : baseTitle;
    }

    function removeToast(toast) {
        if (toast && toast.parentNode) { toast.parentNode.removeChild(toast); }
    }

    function line(tag, className, text) {
        var el = document.createElement(tag);
        if (className) { el.className = className; }
        el.textContent = text;
        return el;
    }

    function showToast(item) {
        var toast = document.createElement('div');
        toast.className = 'notif-toast priority-' + item.priority;
        toast.setAttribute('role', item.priority === 'critical' ? 'alert' : 'status');

        var head = document.createElement('div');
        head.className = 'notif-head';
        head.appendChild(line('span', 'notif-label', item.priority_label));

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'notif-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '×';
        close.addEventListener('click', function () { removeToast(toast); });
        head.appendChild(close);
        toast.appendChild(head);

        toast.appendChild(line('strong', 'notif-title', 'Incident #' + item.incident_id + ' · ' + item.type));
        toast.appendChild(line('p', 'notif-line', 'Location: ' + (item.location || 'Not specified')));
        toast.appendChild(line('p', 'notif-line', 'Incident date: ' + item.incident_date));
        toast.appendChild(line('p', 'notif-line', 'Reported: ' + item.reported_at));

        if (item.ai_type) {
            var ai = 'AI suggests: ' + item.ai_type + ' (' + item.ai_confidence + '% sure)';
            if (item.raised_by_ai) { ai += '. Alert level raised by AI.'; }
            ai += ' Still pending review.';
            toast.appendChild(line('p', 'notif-ai', ai));
        }

        var actions = document.createElement('div');
        actions.className = 'notif-actions';

        var view = document.createElement('a');
        view.className = 'button button-primary';
        view.href = siteBase + 'report-details.php?id=' + item.incident_id;
        view.textContent = 'View Report';
        actions.appendChild(view);

        var ack = document.createElement('button');
        ack.type = 'button';
        ack.className = 'button button-outline';
        ack.textContent = 'Acknowledge';
        ack.addEventListener('click', function () {
            ack.disabled = true;
            api.post('/notifications/read', { ids: [item.incident_id] })
                .then(function (data) { removeToast(toast); render(data, true); })
                .catch(function () { ack.disabled = false; });
        });
        actions.appendChild(ack);
        toast.appendChild(actions);

        var holder = ensureStack();
        holder.insertBefore(toast, holder.firstChild);

        while (holder.children.length > 3) {
            holder.removeChild(holder.lastChild);
        }

        if (item.priority !== 'critical') {
            setTimeout(function () { removeToast(toast); }, 12000);
        }
    }

    function render(data, quiet) {
        updateBadges(data.unread_count);

        if (lastPopupId === null) {
            lastPopupId = data.latest_id;
            saveLast(lastPopupId);
            return;
        }

        if (quiet) { return; }

        var fresh = data.items.filter(function (item) {
            return !item.read && item.incident_id > lastPopupId;
        }).sort(function (a, b) { return a.incident_id - b.incident_id; });

        fresh.slice(-3).forEach(showToast);

        if (data.latest_id > lastPopupId) {
            lastPopupId = data.latest_id;
            saveLast(lastPopupId);
        }

        if (fresh.length && document.querySelector('[data-notif-page]')) {
            var note = document.querySelector('[data-notif-refresh]');
            if (note) { note.hidden = false; }
        }
    }

    function schedule() {
        var delay = document.hidden ? 30000 : 8000;
        if (failures > 0) { delay = Math.min(60000, delay * (failures + 1)); }
        clearTimeout(timer);
        timer = setTimeout(poll, delay);
    }

    function poll() {
        api.get('/notifications')
            .then(function (data) { failures = 0; render(data, false); })
            .catch(function () { failures++; })
            .then(schedule);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { clearTimeout(timer); poll(); }
    });

    document.addEventListener('click', function (event) {
        var one = event.target.closest('[data-ack-id]');
        var all = event.target.closest('[data-ack-all]');
        var body = null;

        if (one) { body = { ids: [parseInt(one.getAttribute('data-ack-id'), 10)] }; }
        if (all) { body = { all: true }; }
        if (!body) { return; }

        var button = one || all;
        button.disabled = true;

        api.post('/notifications/read', body)
            .then(function () { window.location.reload(); })
            .catch(function (error) {
                button.disabled = false;
                window.alert(error.message);
            });
    });

    poll();
})();
