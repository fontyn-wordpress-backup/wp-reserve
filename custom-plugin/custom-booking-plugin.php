<?php
/*
Plugin Name: Custom Booking Plugin for Fonteyn Holiday Park
Description: A booking form that saves data to an external database.
Version: 1.3
Author: Stef van Herk
*/

// Enable PHP error reporting (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session early
function cbp_start_session() {
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'cbp_start_session', 1);

// Encryption helpers
function cbp_encrypt($data) {
    $key = defined('CBP_ENCRYPTION_KEY') ? CBP_ENCRYPTION_KEY : false;
    $iv = defined('CBP_ENCRYPTION_IV') ? CBP_ENCRYPTION_IV : false;

    if (!$key || strlen($key) !== 32) {
        wp_die('Encryption key is missing or not 32 bytes long.');
    }
    if (!$iv || strlen($iv) !== 16) {
        wp_die('Encryption IV is missing or not 16 bytes long.');
    }

    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    if ($encrypted === false) {
        wp_die('Encryption failed.');
    }

    return $encrypted;
}

function cbp_decrypt($data) {
    $key = defined('CBP_ENCRYPTION_KEY') ? CBP_ENCRYPTION_KEY : false;
    $iv = defined('CBP_ENCRYPTION_IV') ? CBP_ENCRYPTION_IV : false;

    if (!$key || strlen($key) !== 32) {
        wp_die('Decryption key is missing or not 32 bytes long.');
    }
    if (!$iv || strlen($iv) !== 16) {
        wp_die('Decryption IV is missing or not 16 bytes long.');
    }

    $decrypted = openssl_decrypt($data, 'AES-256-CBC', $key, 0, $iv);
    if ($decrypted === false) {
        wp_die('Decryption failed.');
    }

    return $decrypted;
}

// Query helper
function cbp_query_or_die($conn, $sql, $context = '') {
    $res = $conn->query($sql);
    if (!$res) {
        wp_die("Database error during $context:<br><pre>SQL: " . esc_html($sql) . "<br>Error: (" . $conn->errno . ") " . esc_html($conn->error) . "</pre>");
    }
    return $res;
}

// External DB connection
function cbp_get_external_db_connection() {
    if (!defined('EXT_DB_HOST') || !defined('EXT_DB_USER') || !defined('EXT_DB_PASSWORD') || !defined('EXT_DB_NAME')) {
        wp_die('External DB credentials are not defined.');
    }

    $conn = new mysqli(EXT_DB_HOST, EXT_DB_USER, EXT_DB_PASSWORD, EXT_DB_NAME);
    if ($conn->connect_error) {
        error_log("External DB connection failed: " . $conn->connect_error);
        wp_die("External DB connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Availability check
function cbp_is_accommodation_available($conn, $accommodation_id, $start_date, $end_date) {
    $sql = "SELECT COUNT(*) as cnt FROM Bookings
            WHERE Accommodations = $accommodation_id
            AND NOT (EndDateTime <= '$start_date' OR StartDateTime >= '$end_date')";
    $res = $conn->query($sql);
    if (!$res) {
        error_log("Availability check failed: " . $conn->error);
        return false;
    }
    $row = $res->fetch_assoc();
    return ($row['cnt'] == 0);
}

// Booking form shortcode
function cbp_render_booking_form() {
    $errors = $_SESSION['cbp_errors'] ?? [];
    $success = $_SESSION['cbp_success'] ?? [];

    unset($_SESSION['cbp_errors'], $_SESSION['cbp_success']);

    $fields = [
        'first_name' => '',
        'last_name' => '',
        'birthdate' => '',
        'phone' => '',
        'email' => '',
        'start_date' => '',
        'end_date' => '',
        'accommodation_type' => '',
        'iban' => '',
    ];
    foreach ($fields as $key => &$value) {
        $value = isset($_POST[$key]) ? esc_attr($_POST[$key]) : '';
    }

    ob_start();
    if ($errors) {
        echo '<div style="color:red;"><ul>';
        foreach ($errors as $error) {
            echo "<li>" . esc_html($error) . "</li>";
        }
        echo '</ul></div>';
    }
    if ($success) {
        echo '<div style="color:green;">' . esc_html($success) . '</div>';
    }
    ?>
    <form method="post" action="">
        <label>First Name: <input type="text" name="first_name" value="<?php echo $fields['first_name']; ?>" required></label><br>
        <label>Last Name: <input type="text" name="last_name" value="<?php echo $fields['last_name']; ?>" required></label><br>
        <label>Birthdate: <input type="date" name="birthdate" value="<?php echo $fields['birthdate']; ?>" required></label><br>
        <label>Phone Number: <input type="text" name="phone" value="<?php echo $fields['phone']; ?>" required></label><br>
        <label>Email Address: <input type="email" name="email" value="<?php echo $fields['email']; ?>" required></label><br>
        <label>Start Date: <input type="datetime-local" name="start_date" value="<?php echo $fields['start_date']; ?>" required></label><br>
        <label>End Date: <input type="datetime-local" name="end_date" value="<?php echo $fields['end_date']; ?>" required></label><br>
        <label>Accommodation Type:
            <select name="accommodation_type" required>
                <option value="Tent" <?php selected($fields['accommodation_type'], 'Tent'); ?>>Tent</option>
                <option value="Cabin" <?php selected($fields['accommodation_type'], 'Cabin'); ?>>Cabin</option>
            </select>
        </label><br>
        <label>IBAN (optional): <input type="text" name="iban" value="<?php echo $fields['iban']; ?>"></label><br>
        <input type="submit" name="cbp_submit" value="Book Now">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('cbp_booking_form', 'cbp_render_booking_form');

// Form submission handler
function cbp_handle_booking_form() {
    if (!isset($_POST['cbp_submit'])) return;

    $conn = cbp_get_external_db_connection();
    $errors = [];

    // Sanitize and escape
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $birthdate = $conn->real_escape_string(trim($_POST['birthdate']));
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $start_input = trim($_POST['start_date']);
    $end_input = trim($_POST['end_date']);
    $accommodation_type = $conn->real_escape_string(trim($_POST['accommodation_type']));
    $iban_raw = trim($_POST['iban'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }

    if (!$start_input || !$end_input || strtotime($start_input) === false || strtotime($end_input) === false) {
        $errors[] = "Start and end dates must be valid datetimes.";
    } else {
        $start_date = date('Y-m-d H:i:s', strtotime($start_input));
        $end_date = date('Y-m-d H:i:s', strtotime($end_input));
        if ($start_date >= $end_date) {
            $errors[] = "Start date must be before end date.";
        }
    }

    $payment_status = $iban_raw ? 1 : 0;
    if ($payment_status && strlen($iban_raw) < 15) {
        $errors[] = "IBAN seems too short.";
    }

    if ($errors) {
        $_SESSION['cbp_errors'] = $errors;
        wp_safe_redirect(wp_get_referer() ?: home_url('/booking'));
        exit;
    }

    $acc_query = "SELECT ID FROM Accommodations WHERE Type = '$accommodation_type'";
    $result = $conn->query($acc_query);
    if (!$result || $result->num_rows === 0) {
        $_SESSION['cbp_errors'] = ["No accommodations of type $accommodation_type found."];
        wp_safe_redirect(wp_get_referer() ?: home_url('/booking'));
        exit;
    }

    $available_accommodation_id = null;
    while ($row = $result->fetch_assoc()) {
        if (cbp_is_accommodation_available($conn, $row['ID'], $start_date, $end_date)) {
            $available_accommodation_id = $row['ID'];
            break;
        }
    }

    if (!$available_accommodation_id) {
        $_SESSION['cbp_errors'] = ["No available $accommodation_type accommodations for selected dates."];
        wp_safe_redirect(wp_get_referer() ?: home_url('/booking'));
        exit;
    }

    $price_query = "SELECT DailyPrice FROM Accommodations WHERE ID = $available_accommodation_id";
    $price_res = $conn->query($price_query);
    $daily_price = 0;
    if ($price_res && ($row = $price_res->fetch_assoc())) {
        $daily_price = floatval($row['DailyPrice']);
    }

    $encrypted_email = $conn->real_escape_string(cbp_encrypt($email));
    $encrypted_phone = $conn->real_escape_string(cbp_encrypt($phone));
    $encrypted_iban = $iban_raw ? $conn->real_escape_string(cbp_encrypt($iban_raw)) : '';

    $guest_sql = "INSERT INTO Guests (FirstName, LastName, Birthdate, PhoneNumber, EmailAddress)
                  VALUES ('$first_name', '$last_name', '$birthdate', '$encrypted_phone', '$encrypted_email')";
    cbp_query_or_die($conn, $guest_sql, 'Guest insert');
    $guest_id = $conn->insert_id;

    $booking_sql = "INSERT INTO Bookings (Guest, Accommodations, StartDateTime, EndDateTime)
                    VALUES ($guest_id, $available_accommodation_id, '$start_date', '$end_date')";
    cbp_query_or_die($conn, $booking_sql, 'Booking insert');
    $booking_id = $conn->insert_id;

    $days = ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24));
    $amount = $daily_price * $days;

    $payment_sql = "INSERT INTO Payments (Bookings, Amount, Status, IBAN)
                    VALUES ($booking_id, $amount, $payment_status, " . ($encrypted_iban ? "'$encrypted_iban'" : "NULL") . ")";
    cbp_query_or_die($conn, $payment_sql, 'Payment insert');

    $_SESSION['cbp_success'] = "Booking successful!";
    $conn->close();
    wp_safe_redirect(wp_get_referer() ?: home_url('/booking'));
    exit;
}
add_action('init', 'cbp_handle_booking_form');

// Optional test insert
function cbp_test_insert() {
    if (isset($_GET['test_booking'])) {
        $conn = cbp_get_external_db_connection();
        $sql = "INSERT INTO Guests (FirstName, LastName, Birthdate, PhoneNumber, EmailAddress)
                VALUES ('Test', 'User', '2000-01-01', '12345', 'test@example.com')";
        if ($conn->query($sql)) {
            echo "Test insert succeeded.";
        } else {
            echo "Test insert failed: " . $conn->error;
        }
        $conn->close();
        exit;
    }
}
add_action('init', 'cbp_test_insert');

// Shortcode to show private IP
function show_private_ip() {
    $ip = $_SERVER['SERVER_ADDR'] ?? 'Unavailable';
    return 'Private IP: ' . $ip;
}
add_shortcode('private_ip', 'show_private_ip');
