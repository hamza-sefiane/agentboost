document.addEventListener("DOMContentLoaded", () => {
    const addressInput = document.getElementById("address-input");
    const postalCodeInput = document.getElementById("postal-code-input");
    const cityInput = document.getElementById("city-input");
    const addressResults = document.getElementById("address-results");

    if (!addressInput || !postalCodeInput || !cityInput || !addressResults) {
        return;
    }

    let addressTimeout = null;
    let addressAbortController = null;

    function hideAddressResults() {
        addressResults.innerHTML = "";
        addressResults.style.display = "none";
    }

    function renderAddressResults(features) {
        addressResults.innerHTML = "";

        if (!Array.isArray(features) || features.length === 0) {
            hideAddressResults();
            return;
        }

        features.slice(0, 5).forEach((feature) => {
            const properties = feature.properties || {};
            const item = document.createElement("div");

            item.className = "address-result-item";
            item.textContent = properties.label || "";

            item.addEventListener("click", () => {
                addressInput.value = properties.name || properties.label || "";
                postalCodeInput.value = properties.postcode || "";
                cityInput.value =
                    properties.city || properties.municipality || "";

                hideAddressResults();
            });

            addressResults.appendChild(item);
        });

        addressResults.style.display = "block";
    }

    function searchAddress(query) {
        if (addressAbortController) {
            addressAbortController.abort();
        }

        addressAbortController = new AbortController();

        const postalCode = postalCodeInput.value.trim();
        const city = cityInput.value.trim();

        let fullQuery = query;

        if (city.length >= 2) {
            fullQuery += " " + city;
        }

        const params = new URLSearchParams({
            q: fullQuery,
            limit: "5",
        });

        if (/^[0-9]{5}$/.test(postalCode)) {
            params.set("postcode", postalCode);
        }

        fetch(`https://api-adresse.data.gouv.fr/search/?${params.toString()}`, {
            signal: addressAbortController.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data || !Array.isArray(data.features)) {
                    hideAddressResults();
                    return;
                }

                renderAddressResults(data.features);
            })
            .catch((error) => {
                if (error.name !== "AbortError") {
                    hideAddressResults();
                }
            });
    }

    addressInput.addEventListener("input", () => {
        const query = addressInput.value.trim();

        clearTimeout(addressTimeout);

        if (query.length < 3) {
            hideAddressResults();
            return;
        }

        addressTimeout = setTimeout(() => {
            searchAddress(query);
        }, 300);
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".address-autocomplete-wrapper")) {
            hideAddressResults();
        }
    });
});
