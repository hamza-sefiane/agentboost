document.addEventListener("DOMContentLoaded", () => {
    const input = document.querySelector(".js-password-input");
    const bars = document.querySelectorAll(".password-strength span");
    const label = document.getElementById("password-strength-label");

    if (!input) {
        return;
    }

    function updateStrength() {
        const password = input.value;

        let score = 0;

        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password) || /[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        bars.forEach((bar, index) => {
            bar.style.backgroundColor = index < score ? "#198754" : "#e5e7eb";
        });

        if (label) {
            label.textContent =
                score <= 1
                    ? "Mot de passe faible"
                    : score === 2
                      ? "Mot de passe moyen"
                      : score === 3
                        ? "Mot de passe fort"
                        : "Mot de passe très fort";
        }
    }

    input.addEventListener("input", updateStrength);

    document.querySelectorAll(".password-toggle").forEach((button) => {
        button.addEventListener("click", () => {
            const field = button
                .closest(".password-wrapper")
                ?.querySelector("input");

            if (!field) {
                return;
            }

            field.type = field.type === "password" ? "text" : "password";

            button.innerHTML =
                field.type === "password"
                    ? '<i class="bi bi-eye"></i>'
                    : '<i class="bi bi-eye-slash"></i>';
        });
    });

    updateStrength();
});
