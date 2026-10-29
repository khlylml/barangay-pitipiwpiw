// Live validation for the Register form.
// A field turns red as soon as the person types or leaves it with a mistake,
// and turns green when it is correct. The server checks the same rules again.
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("registrationForm");
    if (!form) { return; }

    const patterns = {
        mobile: /^(09\d{9}|\+639\d{9})$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        password: /^(?=.*[A-Z])(?=.*\d)(?=.*[@$!])\S{8,}$/
    };

    // Each rule returns an error message, or "" when the value is fine.
    const rules = {
        first_name: function (v) {
            return v.trim().length >= 2 ? "" : "Enter your first name (at least 2 characters).";
        },
        last_name: function (v) {
            return v.trim().length >= 2 ? "" : "Enter your last name (at least 2 characters).";
        },
        contact_no: function (v) {
            return patterns.mobile.test(v) ? "" :
                "Use 09 followed by 9 digits, or +639 followed by 9 digits. No spaces or hyphens.";
        },
        email: function (v) {
            return patterns.email.test(v) ? "" : "Enter a valid email, like name@example.com.";
        },
        address: function (v) {
            return v.trim().length >= 5 ? "" : "Enter your complete address.";
        },
        password: function (v) {
            return patterns.password.test(v) ? "" :
                "At least 8 characters with 1 capital letter, 1 number, and one of @ $ !. No spaces.";
        },
        confirm_password: function (v) {
            const password = form.elements["password"].value;
            if (v === "") { return "Confirm your password."; }
            return v === password ? "" : "Passwords do not match.";
        }
    };

    const fields = Object.keys(rules).map(function (name) {
        const input = form.elements[name];
        const message = document.createElement("small");
        message.className = "field-error";
        message.setAttribute("aria-live", "polite");
        input.parentNode.appendChild(message);
        return { name: name, input: input, message: message, touched: false };
    });

    function check(field, show) {
        const error = rules[field.name](field.input.value);

        if (show) {
            field.input.classList.toggle("is-invalid", error !== "");
            field.input.classList.toggle("is-valid", error === "");
            field.input.setAttribute("aria-invalid", error !== "" ? "true" : "false");
            field.message.textContent = error;
        }

        return error === "";
    }

    fields.forEach(function (field) {
        field.input.addEventListener("input", function () {
            field.touched = true;
            check(field, true);

            // typing in password also re-checks the confirm box
            if (field.name === "password") {
                const confirm = fields[fields.length - 1];
                if (confirm.touched) { check(confirm, true); }
            }
        });

        field.input.addEventListener("blur", function () {
            field.touched = true;
            check(field, true);
        });
    });

    form.addEventListener("submit", function (event) {
        let firstBad = null;

        fields.forEach(function (field) {
            if (!check(field, true) && !firstBad) { firstBad = field; }
        });

        if (firstBad) {
            event.preventDefault();
            firstBad.input.focus();
        }
    });
});
