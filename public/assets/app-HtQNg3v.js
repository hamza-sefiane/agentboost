import "./bootstrap.js";
import "./styles/app.css";

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".dropdown-toggle").forEach((dropdown) => {
        dropdown.addEventListener("click", (e) => {
            e.stopPropagation();
        });
    });
});
