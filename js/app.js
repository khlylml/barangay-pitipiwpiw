document.addEventListener("DOMContentLoaded", function () {
    /* ---------- Hamburger menu ---------- */
    const menuButton = document.querySelector(".menu-toggle");
    const navigation = document.querySelector("#site-navigation");

    function setMenu(open) {
        navigation.classList.toggle("is-open", open);
        menuButton.classList.toggle("is-open", open);
        menuButton.setAttribute("aria-expanded", String(open));
        menuButton.setAttribute(
            "aria-label",
            open ? "Close navigation menu" : "Open navigation menu"
        );
    }

    if (menuButton && navigation) {
        menuButton.addEventListener("click", function (event) {
            event.stopPropagation();
            setMenu(!navigation.classList.contains("is-open"));
        });

        // Close after choosing a link.
        navigation.addEventListener("click", function (event) {
            if (event.target.closest("a")) {
                setMenu(false);
            }
        });

        // Close when tapping outside the menu.
        document.addEventListener("click", function (event) {
            if (
                navigation.classList.contains("is-open") &&
                !navigation.contains(event.target)
            ) {
                setMenu(false);
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                setMenu(false);
            }
        });

        window.addEventListener("resize", function () {
            if (window.innerWidth > 900) {
                setMenu(false);
            }
        });
    }

    /* ---------- Password match check ---------- */
    [
        ["#password", "#confirm_password"],
        ["#new_password", "#confirm_new_password"]
    ].forEach(function (pair) {
        const first = document.querySelector(pair[0]);
        const second = document.querySelector(pair[1]);

        if (!first || !second) {
            return;
        }

        function check() {
            if (second.value && first.value !== second.value) {
                second.setCustomValidity("Passwords do not match.");
            } else {
                second.setCustomValidity("");
            }
        }

        first.addEventListener("input", check);
        second.addEventListener("input", check);
    });

    /* ---------- Tables become stacked cards on phones ---------- */
    document.querySelectorAll("table").forEach(function (table) {
        const headers = Array.from(
            table.querySelectorAll("thead th")
        ).map(function (th) {
            return th.textContent.trim();
        });

        if (!headers.length) {
            return;
        }

        table.classList.add("stack-table");

        table.querySelectorAll("tbody tr").forEach(function (row) {
            Array.from(row.children).forEach(function (cell, index) {
                if (cell.colSpan > 1 || !headers[index]) {
                    return;
                }
                cell.setAttribute("data-label", headers[index]);
            });
        });
    });
});

// Scroll animation: sections fade and slide up as they come into view.
// Skipped for people who turned off motion, and nothing is hidden if
// JavaScript or IntersectionObserver is not available.
document.addEventListener("DOMContentLoaded", function () {
    if (
        !("IntersectionObserver" in window) ||
        window.matchMedia("(prefers-reduced-motion: reduce)").matches ||
        document.body.classList.contains("fit-screen")
    ) {
        return;
    }

    var targets = document.querySelectorAll(
        ".hero-card, .feature-section, .feature-item, .page-heading, " +
        ".stat-grid, .section-block, .details-card, .history-item, " +
        ".track-list li, .data-table, .profile-section"
    );

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    targets.forEach(function (el, index) {
        el.classList.add("reveal");
        // small stagger for items that appear together
        el.style.transitionDelay = (index % 4) * 70 + "ms";
        observer.observe(el);
    });
});


// Light / dark mode toggle (remembered in this browser)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.theme-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dark = document.documentElement.getAttribute('data-theme') !== 'dark';
            if (dark) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }
            try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
        });
    });
});

// Show / hide password (eye button on every password field)
document.addEventListener('DOMContentLoaded', function () {
    var eye = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    var eyeOff = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 6.2A10 10 0 0112 6c6 0 10 6 10 6a17 17 0 01-3.2 3.9M6.6 7.6A16 16 0 002 12s4 7 10 7a9.7 9.7 0 004.4-1"/><path d="M9.9 9.9a3 3 0 004.2 4.2"/></svg>';

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        var wrap = document.createElement('div');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.setAttribute('aria-label', 'Show password');
        btn.innerHTML = eye;
        wrap.appendChild(btn);

        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? eyeOff : eye;
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
});
