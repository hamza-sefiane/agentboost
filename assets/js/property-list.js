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

function copyLeboncoin(data) {
    const property = normalizePropertyData(data);

    const title = [
        property.type,
        property.surface ? `${property.surface} m²` : null,
        property.city ? property.city.toUpperCase() : null,
    ]
        .filter(Boolean)
        .join(" - ");

    const formatted = [
        title,
        "",
        `Prix : ${formatPrice(property.price)} €`,
        property.address ? `Adresse : ${property.address}` : null,
        property.postalCode || property.city
            ? `Localisation : ${[property.postalCode, property.city].filter(Boolean).join(" ")}`
            : null,
        property.surface ? `Surface : ${property.surface} m²` : null,
        property.rooms && property.type.toLowerCase() !== "parking"
            ? `Pièces : ${property.rooms}`
            : null,
        `Parking : ${property.parking || "Non"}`,
        "",
        "Description",
        property.ad,
        "",
        "Contactez l’agence pour plus d’informations ou pour organiser une visite.",
    ]
        .filter((line) => line !== null && line !== undefined && line !== "")
        .join("\n");

    copyToClipboard(formatted, "Format Leboncoin copié");
}

function shareFacebook(data) {
    const property = normalizePropertyData(data);

    const formatted = [
        `🏡 ${property.type} à ${property.city || "découvrir"}`,
        "",
        property.price ? `💰 ${formatPrice(property.price)} €` : null,
        property.surface ? `📐 ${property.surface} m²` : null,
        property.rooms && property.type.toLowerCase() !== "parking"
            ? `🛋️ ${property.rooms} pièce${Number(property.rooms) > 1 ? "s" : ""}`
            : null,
        property.parking === "Oui" ? "🚗 Parking inclus" : null,
        property.city || property.postalCode
            ? `📍 ${[property.postalCode, property.city].filter(Boolean).join(" ")}`
            : null,
        "",
        property.ad,
        "",
        "📩 Contactez-nous pour plus d’informations ou pour organiser une visite.",
        "",
        buildHashtags(property),
    ]
        .filter((line) => line !== null && line !== undefined && line !== "")
        .join("\n");

    copyToClipboard(formatted, "Post Facebook copié");

    window.open("https://www.facebook.com/", "_blank", "noopener,noreferrer");
}

function normalizePropertyData(data) {
    if (typeof data === "string") {
        return {
            ad: data,
            type: "Bien immobilier",
            price: "",
            surface: "",
            rooms: "",
            parking: "",
            address: "",
            postalCode: "",
            city: "",
        };
    }

    return {
        ad: data.ad || "",
        type: data.type || "Bien immobilier",
        price: data.price || "",
        surface: data.surface || "",
        rooms: data.rooms || "",
        parking: data.parking || "",
        address: data.address || "",
        postalCode: data.postalCode || "",
        city: data.city || "",
    };
}

function formatPrice(value) {
    const number = Number(value);

    if (!Number.isFinite(number) || number <= 0) {
        return "";
    }

    return new Intl.NumberFormat("fr-FR").format(number);
}

function buildHashtags(property) {
    const city = (property.city || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-zA-Z0-9]/g, "");

    return [
        "#Immobilier",
        "#VenteImmobiliere",
        city ? `#${city}` : null,
        property.type ? `#${property.type.replace(/\s+/g, "")}` : null,
    ]
        .filter(Boolean)
        .join(" ");
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
