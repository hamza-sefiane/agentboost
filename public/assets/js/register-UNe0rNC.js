document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registerForm");

    if (!form) {
        return;
    }

    const bindPasswordToggle = (
        buttonId,
        inputId,
        openIconId,
        closedIconId,
    ) => {
        const button = document.getElementById(buttonId);
        const input = document.getElementById(inputId);
        const openIcon = document.getElementById(openIconId);
        const closedIcon = document.getElementById(closedIconId);

        if (!button || !input) {
            return;
        }

        button.addEventListener("click", () => {
            const isHidden = input.type === "password";

            input.type = isHidden ? "text" : "password";

            if (openIcon) {
                openIcon.style.display = isHidden ? "none" : "block";
            }

            if (closedIcon) {
                closedIcon.style.display = isHidden ? "block" : "none";
            }

            button.setAttribute(
                "aria-label",
                isHidden
                    ? "Masquer le mot de passe"
                    : "Afficher le mot de passe",
            );
        });
    };

    bindPasswordToggle("togglePassword", "password", "eyeOpen", "eyeClosed");
    bindPasswordToggle(
        "toggleConfirmPassword",
        "confirmPassword",
        "confirmEyeOpen",
        "confirmEyeClosed",
    );

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
