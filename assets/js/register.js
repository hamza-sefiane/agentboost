document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registerForm");

    const emailInput = document.getElementById("email");

    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirmPassword");

    const togglePassword = document.getElementById("togglePassword");
    const toggleConfirmPassword = document.getElementById(
        "toggleConfirmPassword",
    );

    const eyeOpen = document.getElementById("eyeOpen");
    const eyeClosed = document.getElementById("eyeClosed");

    const confirmEyeOpen = document.getElementById("confirmEyeOpen");
    const confirmEyeClosed = document.getElementById("confirmEyeClosed");

    const submitButton = document.getElementById("submitButton");

    const emailError = document.getElementById("emailError");
    const passwordError = document.getElementById("passwordError");
    const confirmPasswordError = document.getElementById(
        "confirmPasswordError",
    );

    const showError = (element, message) => {
        element.textContent = message;
        element.style.display = "block";
    };

    const hideError = (element) => {
        element.textContent = "";
        element.style.display = "none";
    };

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener("click", () => {
            const hidden = passwordInput.type === "password";

            passwordInput.type = hidden ? "text" : "password";

            eyeOpen.style.display = hidden ? "none" : "block";
            eyeClosed.style.display = hidden ? "block" : "none";
        });
    }

    if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener("click", () => {
            const hidden = confirmPasswordInput.type === "password";

            confirmPasswordInput.type = hidden ? "text" : "password";

            confirmEyeOpen.style.display = hidden ? "none" : "block";
            confirmEyeClosed.style.display = hidden ? "block" : "none";
        });
    }

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

    if (form) {
        form.addEventListener("submit", (event) => {
            hideError(emailError);
            hideError(passwordError);
            hideError(confirmPasswordError);

            let hasError = false;

            const email = emailInput.value.trim();
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (!email || !email.includes("@")) {
                showError(
                    emailError,
                    "Veuillez saisir une adresse email valide.",
                );
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

            submitButton.disabled = true;
            submitButton.textContent = "Création du compte...";
        });
    }
});
