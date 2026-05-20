document.addEventListener("DOMContentLoaded", function () {
    const statsElement = document.getElementById("admin-stats-data");
    const citiesElement = document.getElementById("admin-cities-data");

    if (!statsElement || !citiesElement || typeof Chart === "undefined") {
        return;
    }

    const stats = JSON.parse(statsElement.textContent || "{}");
    const cities = JSON.parse(citiesElement.textContent || "[]");

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
            },
        },
    };

    const barOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                },
            },
        },
        plugins: {
            legend: {
                display: true,
            },
        },
    };

    const usersPieCanvas = document.getElementById("usersPieChart");
    const globalBarCanvas = document.getElementById("globalBarChart");
    const citiesCanvas = document.getElementById("citiesChart");

    if (usersPieCanvas) {
        new Chart(usersPieCanvas, {
            type: "doughnut",
            data: {
                labels: ["Abonnés actifs", "Utilisateurs gratuits"],
                datasets: [
                    {
                        data: [
                            Number(stats.activeSubscriptions) || 0,
                            Number(stats.freeUsers) || 0,
                        ],
                    },
                ],
            },
            options: chartOptions,
        });
    }

    if (globalBarCanvas) {
        new Chart(globalBarCanvas, {
            type: "bar",
            data: {
                labels: [
                    "Utilisateurs",
                    "Abonnements",
                    "Biens",
                    "Revenu estimé",
                ],
                datasets: [
                    {
                        label: "Total",
                        data: [
                            Number(stats.users) || 0,
                            Number(stats.activeSubscriptions) || 0,
                            Number(stats.properties) || 0,
                            Number(stats.estimatedRevenue) || 0,
                        ],
                    },
                ],
            },
            options: barOptions,
        });
    }

    if (citiesCanvas) {
        new Chart(citiesCanvas, {
            type: "bar",
            data: {
                labels:
                    cities.length > 0
                        ? cities.map((city) => city.city || "Non renseigné")
                        : ["Aucune donnée"],
                datasets: [
                    {
                        label: "Biens estimés",
                        data:
                            cities.length > 0
                                ? cities.map((city) => Number(city.total) || 0)
                                : [0],
                    },
                ],
            },
            options: barOptions,
        });
    }
});
