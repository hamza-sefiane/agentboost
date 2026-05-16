document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registerForm");

    if (!form) {
        return;
    }

    const bindPasswordToggle = (buttonId, inputId) => {
        const button = document.getElementById(buttonId);
        const input = document.getElementById(inputId);

        if (!button || !input) {
            return;
        }

        button.addEventListener("click", () => {
            input.type = input.type === "password" ? "text" : "password";
        });
    };

    bindPasswordToggle("togglePassword", "password");
    bindPasswordToggle("toggleConfirmPassword", "confirmPassword");

    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const submitButton = document.getElementById("submitButton");

    const emailError = document.getElementById("emailError");
    const passwordError = document.getElementById("passwordError");
    const confirmPasswordError = document.getElementById(
        "confirmPasswordError",
    );

    const showError = (element, message) => {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.style.display = "block";
    };

    const hideError = (element) => {
        if (!element) {
            return;
        }

        element.textContent = "";
        element.style.display = "none";
    };

    [emailInput, passwordInput, confirmPasswordInput].forEach((input) => {
        if (!input) {
            return;
        }

        input.addEventListener("input", () => {
            hideError(emailError);
            hideError(passwordError);
            hideError(confirmPasswordError);
        });
    });

    form.addEventListener("submit", (event) => {
        hideError(emailError);
        hideError(passwordError);
        hideError(confirmPasswordError);

        let hasError = false;

        const email = emailInput ? emailInput.value.trim() : "";
        const password = passwordInput ? passwordInput.value : "";
        const confirmPassword = confirmPasswordInput
            ? confirmPasswordInput.value
            : "";

        if (!email || !email.includes("@")) {
            showError(emailError, "Veuillez saisir une adresse email valide.");
            hasError = true;
        }

        if (password.length < 8) {
            showError(
                passwordError,
                "Le mot de passe doit contenir au moins 8 caractères.",
            );
            hasError = true;
        }

        if (password !== confirmPassword) {
            showError(
                confirmPasswordError,
                "Les mots de passe ne correspondent pas.",
            );
            hasError = true;
        }

        if (hasError) {
            event.preventDefault();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = "Création du compte...";
        }
    });
});
