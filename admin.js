document.addEventListener('DOMContentLoaded', function () {
    // Confirm before deleting an airport
    const deleteButtons = document.querySelectorAll('input[name="delete_airport"]');
    deleteButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            const confirmed = confirm('Are you sure you want to delete this airport?');
            if (!confirmed) {
                event.preventDefault();
            }
        });
    });

    // Form validation before adding a new airport
    const addAirportForm = document.querySelector('form');
    if (addAirportForm) {
        addAirportForm.addEventListener('submit', function (event) {
            const icaoInput = addAirportForm.querySelector('input[name="icao_code"]');
            const latitudeInput = addAirportForm.querySelector('input[name="latitude"]');
            const longitudeInput = addAirportForm.querySelector('input[name="longitude"]');

            const icaoCode = icaoInput.value.trim().toUpperCase();
            const latitude = parseFloat(latitudeInput.value.trim());
            const longitude = parseFloat(longitudeInput.value.trim());

            let valid = true;

            // Validate ICAO code (should be exactly 4 letters)
            if (!/^[A-Z]{4}$/.test(icaoCode)) {
                alert('Please enter a valid ICAO code (4 letters).');
                valid = false;
            }

            // Validate latitude (-90 to 90)
            if (isNaN(latitude) || latitude < -90 || latitude > 90) {
                alert('Please enter a valid latitude (-90 to 90).');
                valid = false;
            }

            // Validate longitude (-180 to 180)
            if (isNaN(longitude) || longitude < -180 || longitude > 180) {
                alert('Please enter a valid longitude (-180 to 180).');
                valid = false;
            }

            // If any validation fails, prevent the form from being submitted
            if (!valid) {
                event.preventDefault();
            } else {
                // Update the ICAO code field to uppercase before submitting
                icaoInput.value = icaoCode;
            }
        });
    }
});
