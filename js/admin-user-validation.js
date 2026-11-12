document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("createUserForm");
    if (!form) { return; }

    const patterns = {
        mobile: /^(09\d{9}|\+639\d{9})$/,
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        password: /^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S{8,}$/
    };

    const rules = {
        first_name: function (v) {
            return v.trim().length >= 2 ? "" : "Enter the first name (at least 2 characters).";
        },
        last_name: function (v) {
            return v.trim().length >= 2 ? "" : "Enter the last name (at least 2 characters).";
        },
        email: function (v) {
            return patterns.email.test(v.trim()) ? "" : "Enter a valid email, like name@example.com.";
        },
        contact_no: function (v) {
            return patterns.mobile.test(v.trim()) ? "" :
                "Use 09 followed by 9 digits, or +639 followed by 9 digits. No spaces or hyphens.";
        },
        address: function (v) {
            return v.trim().length >= 5 ? "" : "Enter the complete address.";
        },
        role: function (v) {
            return v !== "" ? "" : "Choose a role.";
        },
        password: function (v) {
            return patterns.password.test(v) ? "" :
                "At least 8 characters with 1 capital letter, 1 number, and 1 special character (like @ $ ! #). No spaces.";
        }
    };

    const fields = Object.keys(rules).map(function (name) {
        const input = form.elements[name];
        const message = document.createElement("small");
        message.className = "field-error";
        message.setAttribute("aria-live", "polite");
        input.closest(".form-field").appendChild(message);
        return { name: name, input: input, message: message };
    });

    function check(field) {
        const error = rules[field.name](field.input.value);
        field.input.classList.toggle("is-invalid", error !== "");
        field.input.classList.toggle("is-valid", error === "");
        field.input.setAttribute("aria-invalid", error !== "" ? "true" : "false");
        field.message.textContent = error;
        return error === "";
    }

    fields.forEach(function (field) {
        field.input.addEventListener("input", function () { check(field); });
        field.input.addEventListener("change", function () { check(field); });
        field.input.addEventListener("blur", function () { check(field); });
    });

    form.addEventListener("submit", function (event) {
        let firstBad = null;

        fields.forEach(function (field) {
            if (!check(field) && !firstBad) { firstBad = field; }
        });

        if (firstBad) {
            event.preventDefault();
            firstBad.input.scrollIntoView({ behavior: "smooth", block: "center" });
            firstBad.input.focus({ preventScroll: true });
        }
    });
});
