import "../styles/admin.css";

document.addEventListener("DOMContentLoaded", () => {
    const statsElement = document.getElementById("admin-stats-data");
    const citiesElement = document.getElementById("admin-cities-data");

    if (!statsElement || !citiesElement || typeof Chart === "undefined") {
        return;
    }

    const stats = JSON.parse(statsElement.textContent);
    const topCities = JSON.parse(citiesElement.textContent);

    new Chart(document.getElementById("usersPieChart"), {
        type: "doughnut",
        data: {
            labels: ["Abonnés actifs", "Utilisateurs gratuits"],
            datasets: [
                {
                    data: [stats.activeSubscriptions, stats.freeUsers],
                    backgroundColor: ["#2563eb", "#f97316"],
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: "bottom" },
            },
        },
    });

    new Chart(document.getElementById("globalBarChart"), {
        type: "bar",
        data: {
            labels: ["Utilisateurs", "Abonnements", "Biens", "Revenu"],
            datasets: [
                {
                    label: "Statistiques",
                    data: [
                        stats.users,
                        stats.activeSubscriptions,
                        stats.properties,
                        stats.estimatedRevenue,
                    ],
                    backgroundColor: "#2563eb",
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true },
            },
        },
    });

    new Chart(document.getElementById("citiesChart"), {
        type: "bar",
        data: {
            labels: topCities.map((item) => item.city),
            datasets: [
                {
                    label: "Biens estimés",
                    data: topCities.map((item) => item.total),
                    backgroundColor: "#16a34a",
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: "y",
            scales: {
                x: { beginAtZero: true },
            },
        },
    });
});
