<?php
/*
Plugin Name: IVAO Pilot Tracker
Description: Displays pilot departures and arrivals for selected airports with estimated ETD, EET, and ETA. Includes backend management for adding, editing, and removing airports.
Version: 1.21
Author: Eyad Nimri
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Function to create a custom database table for storing airports
function ivao_pilot_tracker_create_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ivao_airports';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        icao_code varchar(4) NOT NULL,
        airport_name varchar(100) NOT NULL,
        latitude float(10,6) NOT NULL,
        longitude float(10,6) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'ivao_pilot_tracker_create_db');

// Function to calculate ETA and EET based on arrival distance, ground speed, departure time, and last track timestamp
function calculate_eta($arrivalDistance, $groundSpeed, $lastTrackTimestamp) {
    if ($groundSpeed > 0 && $arrivalDistance > 0 && $lastTrackTimestamp) {
        $arrivalDistanceInKm = $arrivalDistance * 1.852;
        $currentTime = time();
        $lastTrackTime = strtotime($lastTrackTimestamp);
        $etaSeconds = ($arrivalDistanceInKm / ($groundSpeed * 1.852)) * 3600;
        $etaTimestamp = $currentTime + $etaSeconds;
        $eta = gmdate('H:i', $etaTimestamp) . ' UTC';
    } else {
        $eta = 'N/A';
    }
    return $eta;
}

function calculate_etd($departureTime) {
    if ($departureTime) {
        return gmdate('H:i', $departureTime) . ' UTC';
    }
    return 'N/A';
}

function calculate_eet($departureTime, $arrivalTime) {
    if ($departureTime && $arrivalTime) {
        $eetSeconds = $arrivalTime - $departureTime;
        $hours = floor($eetSeconds / 3600);
        $minutes = floor(($eetSeconds % 3600) / 60);
        return sprintf('%02d:%02d', $hours, $minutes) . ' UTC';
    }
    return 'N/A';
}

// Function to fetch IVAO data
function fetch_ivao_data() {
    $response = wp_remote_get('https://api.ivao.aero/v2/tracker/whazzup');
    if (is_wp_error($response)) {
        return ['departures' => [], 'arrivals' => []];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $result = ['departures' => [], 'arrivals' => []];

    global $wpdb;
    $table_name = $wpdb->prefix . 'ivao_airports';
    $icao_codes = $wpdb->get_col("SELECT icao_code FROM $table_name");

    foreach ($data['clients']['pilots'] as $pilot) {
        $departureId = $pilot['flightPlan']['departureId'] ?? '';
        $arrivalId = $pilot['flightPlan']['arrivalId'] ?? '';
        $departureTime = $pilot['flightPlan']['departureTime'] ?? null;
        $arrivalTime = $pilot['flightPlan']['arrivalTime'] ?? null;
        $arrivalDistance = $pilot['lastTrack']['arrivalDistance'] ?? 0;
        $groundSpeed = $pilot['lastTrack']['groundSpeed'] ?? 0;
        $lastTrackTimestamp = $pilot['lastTrack']['timestamp'] ?? null;

        // Fetch EET directly from API response if available
        $eet = isset($pilot['flightPlan']['eet']) ? gmdate('H:i', $pilot['flightPlan']['eet']) . ' UTC' : 'N/A';

        $etd = calculate_etd($departureTime);
        $eta = calculate_eta($arrivalDistance, $groundSpeed, $lastTrackTimestamp);

        if (in_array($departureId, $icao_codes)) {
            $result['departures'][] = [
                'callsign' => $pilot['callsign'],
                'from' => $departureId,
                'to' => $arrivalId,
                'etd' => $etd,
                'eet' => $eet,
                'eta' => $eta,
                'last_track' => $pilot['lastTrack']['state'] ?? 'Unknown'
            ];
        }

        if (in_array($arrivalId, $icao_codes)) {
            $result['arrivals'][] = [
                'callsign' => $pilot['callsign'],
                'to' => $arrivalId,
                'from' => $departureId,
                'etd' => $etd,
                'eet' => $eet,
                'eta' => $eta,
                'last_track' => $pilot['lastTrack']['state'] ?? 'Unknown'
            ];
        }
    }

    return $result;
}

// Shortcode function to render the plugin output
function render_ivao_pilot_tracker() {
    $data = fetch_ivao_data();

    ob_start(); // Start output buffering

    echo '<style>
        /* Base styles for the plugin */
        .ivao-pilot-tracker {
            font-family: Arial, sans-serif;
            margin: 20px auto; /* Center the div horizontally */
            max-width: 1000px; /* Restrict width for better alignment */
            padding: 0 15px; /* Add some padding */
            display: flex; /* Flexbox for centering content */
            flex-direction: column;
            align-items: center;
            text-align: center; /* Center text and inline elements */
        }

        .ivao-pilot-tracker h2 {
            color: #333;
            margin: 20px 0; /* Adjust margin as needed */
            font-size: 2em;
        }

        .ivao-pilot-tracker h3 {
            color: #444;
            margin-top: 30px;
            margin-bottom: 10px;
            font-size: 1.8em;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }

        .ivao-pilot-tracker h4 {
            color: #555;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 1.5em;
        }

        .ivao-pilot-tracker .table-responsive {
            overflow-x: auto; /* Allow horizontal scrolling */
            width: 100%;
        }

        .ivao-pilot-tracker table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0; /* Adjust margin as needed */
        }

        .ivao-pilot-tracker th, .ivao-pilot-tracker td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 0.9em;
        }

        .ivao-pilot-tracker th {
            background-color: #f4f4f4;
            font-weight: bold;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .ivao-pilot-tracker h3, .ivao-pilot-tracker h4 {
                font-size: 1.4em;
            }

            .ivao-pilot-tracker table {
                display: block;
                width: 100%;
                overflow-x: auto;
            }

            .ivao-pilot-tracker th, .ivao-pilot-tracker td {
                white-space: nowrap;
            }

            .ivao-pilot-tracker th, .ivao-pilot-tracker td {
                padding: 10px;
            }

            /* Make buttons and form inputs full-width */
            .ivao-pilot-tracker form .regular-text,
            .ivao-pilot-tracker form input[type="submit"] {
                width: 100%;
                box-sizing: border-box;
            }
        }

        /* Extra small screens (phones) */
        @media (max-width: 480px) {
            .ivao-pilot-tracker h3, .ivao-pilot-tracker h4 {
                font-size: 1.2em;
            }

            .ivao-pilot-tracker th, .ivao-pilot-tracker td {
                font-size: 0.8em;
                padding: 6px;
            }

            .ivao-pilot-tracker table {
                margin-bottom: 15px;
            }
        }
    </style>';

    echo '<div class="ivao-pilot-tracker">';
    echo '<h2>Airports</h2>';

    global $wpdb;
    $table_name = $wpdb->prefix . 'ivao_airports';
    $airports = $wpdb->get_results("SELECT * FROM $table_name");

    foreach ($airports as $airport) {
        $airportName = esc_html($airport->airport_name);
        $icaoCode = esc_html($airport->icao_code);

        // Departures section for each airport
        echo '<h3>' . $airportName . ' (' . $icaoCode . ')</h3>';
        echo '<h4>Departures</h4>';
        echo '<div class="table-responsive"><table>';
        echo '<tr><th>CALLSIGN</th><th>FROM</th><th>TO</th><th>ETD</th><th>EET</th><th>ETA</th><th>LAST TRACK</th></tr>';

        $hasDepartures = false;
        foreach ($data['departures'] as $departure) {
            if ($departure['from'] === $icaoCode) {
                $hasDepartures = true;
                echo '<tr>';
                echo '<td>' . esc_html($departure['callsign']) . '</td>';
                echo '<td>' . esc_html($departure['from']) . '</td>';
                echo '<td>' . esc_html($departure['to']) . '</td>';
                echo '<td>' . esc_html($departure['etd']) . '</td>';
                echo '<td>' . esc_html($departure['eet']) . '</td>';
                echo '<td>' . esc_html($departure['eta']) . '</td>';
                echo '<td>' . esc_html($departure['last_track']) . '</td>';
                echo '</tr>';
            }
        }
        if (!$hasDepartures) {
            echo '<tr><td colspan="7">No departures</td></tr>';
        }
        echo '</table></div>';

        // Arrivals section for each airport
        echo '<h4>Arrivals</h4>';
        echo '<div class="table-responsive"><table>';
        echo '<tr><th>CALLSIGN</th><th>TO</th><th>FROM</th><th>ETD</th><th>EET</th><th>ETA</th><th>LAST TRACK</th></tr>';

        $hasArrivals = false;
        foreach ($data['arrivals'] as $arrival) {
            if ($arrival['to'] === $icaoCode) {
                $hasArrivals = true;
                echo '<tr>';
                echo '<td>' . esc_html($arrival['callsign']) . '</td>';
                echo '<td>' . esc_html($arrival['to']) . '</td>';
                echo '<td>' . esc_html($arrival['from']) . '</td>';
                echo '<td>' . esc_html($arrival['etd']) . '</td>';
                echo '<td>' . esc_html($arrival['eet']) . '</td>';
                echo '<td>' . esc_html($arrival['eta']) . '</td>';
                echo '<td>' . esc_html($arrival['last_track']) . '</td>';
                echo '</tr>';
            }
        }
        if (!$hasArrivals) {
            echo '<tr><td colspan="7">No arrivals</td></tr>';
        }
        echo '</table></div>';
    }

    echo '</div>';

    return ob_get_clean(); // Return the buffered content
}

// Register the shortcode
function ivao_pilot_tracker_shortcode() {
    return render_ivao_pilot_tracker();
}
add_shortcode('ivao_pilot_tracker', 'ivao_pilot_tracker_shortcode');

// Function to add a menu item for the plugin settings
function ivao_pilot_tracker_menu() {
    add_menu_page(
        'IVAO Pilot Tracker Settings',
        'IVAO Pilot Tracker',
        'manage_options',
        'ivao-pilot-tracker',
        'ivao_pilot_tracker_settings_page',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'ivao_pilot_tracker_menu');

// Function to render the plugin settings page
function ivao_pilot_tracker_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ivao_airports';

    if (isset($_POST['add_airport'])) {
        $icao_code = sanitize_text_field($_POST['icao_code']);
        $airport_name = sanitize_text_field($_POST['airport_name']);
        $latitude = sanitize_text_field($_POST['latitude']);
        $longitude = sanitize_text_field($_POST['longitude']);

        $wpdb->insert(
            $table_name,
            [
                'icao_code' => $icao_code,
                'airport_name' => $airport_name,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]
        );
    }

    if (isset($_POST['delete_airport'])) {
        $id = sanitize_text_field($_POST['id']);
        $wpdb->delete($table_name, ['id' => $id]);
    }

    $airports = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<style>
        .wrap {
            font-family: Arial, sans-serif;
        }

        .form-table th {
            width: 150px;
        }

        .form-table td input {
            width: 100%;
        }

        .button-primary {
            background: #0073aa;
            border-color: #006799;
        }

        .button-secondary {
            background: #f7f7f7;
            border-color: #ccc;
        }
    </style>';

    echo '<div class="wrap">';
    echo '<h1>IVAO Pilot Tracker Settings</h1>';
    echo '<form method="post">';
    echo '<table class="form-table">';
    echo '<tr valign="top"><th scope="row">ICAO Code</th><td><input type="text" name="icao_code" class="regular-text" required /></td></tr>';
    echo '<tr valign="top"><th scope="row">Airport Name</th><td><input type="text" name="airport_name" class="regular-text" required /></td></tr>';
    echo '<tr valign="top"><th scope="row">Latitude</th><td><input type="text" name="latitude" class="regular-text" required /></td></tr>';
    echo '<tr valign="top"><th scope="row">Longitude</th><td><input type="text" name="longitude" class="regular-text" required /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="add_airport" value="Add Airport" class="button-primary" />';
    echo '</form>';
    echo '</div>';

    echo '<div class="wrap">';
    echo '<h2>Existing Airports</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>ICAO Code</th><th>Airport Name</th><th>Latitude</th><th>Longitude</th><th>Action</th></tr></thead>';
    echo '<tbody>';
    foreach ($airports as $airport) {
        echo '<tr>';
        echo '<td>' . esc_html($airport->id) . '</td>';
        echo '<td>' . esc_html($airport->icao_code) . '</td>';
        echo '<td>' . esc_html($airport->airport_name) . '</td>';
        echo '<td>' . esc_html($airport->latitude) . '</td>';
        echo '<td>' . esc_html($airport->longitude) . '</td>';
        echo '<td><form method="post" style="display:inline-block;"><input type="hidden" name="id" value="' . esc_attr($airport->id) . '"><input type="submit" name="delete_airport" value="Delete" class="button-secondary" /></form></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// Enqueue the plugin's styles
function ivao_pilot_tracker_enqueue_styles() {
    wp_enqueue_style('ivao-pilot-tracker', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('wp_enqueue_scripts', 'ivao_pilot_tracker_enqueue_styles');

// Enqueue the plugin's admin scripts and styles
function ivao_pilot_tracker_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_ivao-pilot-tracker') {
        return;
    }

    wp_enqueue_style(
        'ivao-pilot-tracker-admin',
        plugin_dir_url(__FILE__) . 'admin.css'
    );

    wp_enqueue_script(
        'ivao-pilot-tracker-admin',
        plugin_dir_url(__FILE__) . 'admin.js',
        array('jquery'),
        '1.0',
        true
    );
}
add_action('admin_enqueue_scripts', 'ivao_pilot_tracker_enqueue_admin_scripts');
