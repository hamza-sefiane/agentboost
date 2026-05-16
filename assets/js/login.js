document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loginForm");
    const passwordInput = document.getElementById("password");
    const toggleBtn = document.getElementById("togglePassword");
    const eyeOpen = document.getElementById("eyeOpen");
    const eyeClosed = document.getElementById("eyeClosed");
    const submitBtn = document.getElementById("submitBtn");

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener("click", () => {
            const hidden = passwordInput.type === "password";

            passwordInput.type = hidden ? "text" : "password";
            eyeOpen.style.display = hidden ? "none" : "block";
            eyeClosed.style.display = hidden ? "block" : "none";
            toggleBtn.setAttribute(
                "aria-label",
                hidden ? "Masquer le mot de passe" : "Afficher le mot de passe",
            );
        });
    }

    if (form && submitBtn) {
        form.addEventListener("submit", () => {
            submitBtn.disabled = true;
            submitBtn.textContent = "Connexion...";
        });
    }
});
