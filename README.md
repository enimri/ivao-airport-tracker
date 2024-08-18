# IVAO Pilot Tracker

## Description

This plugin displays pilot departures and arrivals for selected airports with estimated ETD (Estimated Time of Departure) and EET (Estimated Elapsed Time). It also includes a backend management interface for adding, editing, and removing airports.

## Installation

1. Download the plugin and unzip it.
2. Upload the `ivao-pilot-tracker` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Use the shortcode `[ivao_airport_tracker]` to display the pilot tracker on any page or post.

## Admin Interface

You can manage airports via the IVAO Pilot Tracker admin page under the WordPress Dashboard.

### Adding an Airport

1. Go to the IVAO Pilot Tracker admin page.
2. Enter the ICAO code, latitude, and longitude for the airport.
3. Click "Add Airport" to save it to the database.

### Deleting an Airport

1. Go to the IVAO Pilot Tracker admin page.
2. Click the "Delete" button next to the airport you want to remove.

## Shortcode

Use the shortcode `[ivao_airport_tracker]` to display the pilot departures and arrivals on any post or page.

## Changelog

### Version 1.5
- Initial release with selected airport filtering and estimated ETD/EET display.
