// Shows the current weather for a pin using OpenWeatherMap (through weather.php,
// which keeps the API key private on the server).
(function () {
    async function load(lat, lng, element) {
        const requestId = String(Date.now() + Math.random());
        element.dataset.request = requestId;
        element.textContent = 'Checking the weather at this location...';

        try {
            const response = await fetch(
                'weather.php?lat=' + encodeURIComponent(lat) +
                '&lng=' + encodeURIComponent(lng),
                { credentials: 'same-origin' }
            );
            const data = await response.json();

            // Ignore answers for an older pin position.
            if (element.dataset.request !== requestId) {
                return;
            }

            if (!response.ok || data.error) {
                element.textContent = data.error || 'Weather is unavailable right now.';
                return;
            }

            element.textContent =
                'Weather here: ' + data.description +
                ', ' + data.temp + '°C' +
                ', humidity ' + data.humidity + '%' +
                ', wind ' + data.wind_kmh + ' km/h' +
                (data.rain_mm > 0 ? ', rain ' + data.rain_mm + ' mm' : '');
        } catch (error) {
            if (element.dataset.request === requestId) {
                element.textContent = 'Weather is unavailable right now.';
            }
        }
    }

    window.BarangayWeather = { load: load };
})();
