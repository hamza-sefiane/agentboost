let currentAd = "";

function openAdModal(text) {
    currentAd = text || "";

    const adContent = document.getElementById("adContent");

    if (adContent) {
        adContent.innerText = currentAd;
    }

    const modalElement = document.getElementById("adModal");

    if (modalElement && window.bootstrap) {
        const modal = new window.bootstrap.Modal(modalElement);
        modal.show();
    }
}

function copyAd(text) {
    copyToClipboard(text || "", "Annonce copiée");
}

function copyFromModal() {
    copyToClipboard(currentAd || "", "Annonce copiée");
}

function copyLeboncoin(text) {
    const formatted = (text || "").replace(/\n/g, "\n\n").toUpperCase();

    copyToClipboard(formatted, "Format Leboncoin copié");
}

function shareFacebook(text) {
    copyToClipboard(text || "", "Annonce copiée pour Facebook");

    window.open("https://www.facebook.com/", "_blank", "noopener,noreferrer");
}

function copyToClipboard(text, successMessage) {
    if (!navigator.clipboard) {
        showToast("Copie impossible sur ce navigateur", true);
        return;
    }

    navigator.clipboard
        .writeText(text)
        .then(() => showToast(successMessage))
        .catch(() => showToast("Erreur lors de la copie", true));
}

function showToast(message, isError = false) {
    const toast = document.createElement("div");

    toast.innerText = message;
    toast.className = isError ? "property-toast is-error" : "property-toast";

    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 2000);
}

function updateBulkDeleteState() {
    const checkedCount = document.querySelectorAll(
        ".property-checkbox:checked",
    ).length;
    const bulkDeleteBtn = document.getElementById("bulk-delete-btn");
    const bulkMobileBar = document.getElementById("bulk-mobile-bar");
    const bulkMobileCount = document.getElementById("bulk-mobile-count");

    if (bulkDeleteBtn) {
        bulkDeleteBtn.classList.toggle("d-none", checkedCount === 0);
    }

    if (bulkMobileBar) {
        bulkMobileBar.classList.toggle("is-visible", checkedCount > 0);
    }

    if (bulkMobileCount) {
        bulkMobileCount.innerText =
            checkedCount + " sélection" + (checkedCount > 1 ? "s" : "");
    }
}

function confirmBulkDelete() {
    const checkedCount = document.querySelectorAll(
        ".property-checkbox:checked",
    ).length;

    if (checkedCount === 0) {
        return false;
    }

    return confirm("Supprimer " + checkedCount + " estimation(s) ?");
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".property-checkbox").forEach((checkbox) => {
        checkbox.addEventListener("change", updateBulkDeleteState);
    });

    updateBulkDeleteState();
});

window.openAdModal = openAdModal;
window.copyAd = copyAd;
window.copyFromModal = copyFromModal;
window.copyLeboncoin = copyLeboncoin;
window.shareFacebook = shareFacebook;
window.confirmBulkDelete = confirmBulkDelete;
