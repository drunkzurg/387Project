<?php

function redirect($new_location) {
    header("Location: " . $new_location);
    exit;
}

function new_header($name = "Arcade Management", $urlLink = "index.php") {
    echo "<!DOCTYPE html>";
    echo "<html lang='en'>";
    echo "<head>";
    echo "    <meta charset='UTF-8'>";
    echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "    <title>$name</title>";
    echo "    <link rel='stylesheet' href='css/normalize.css'>";
    echo "    <link rel='stylesheet' href='css/foundation.css'>";
    echo "    <script src='js/vendor/modernizr.js'></script>";
    echo "</head>";
    echo "<body>";
    echo "<div class='contain-to-grid sticky'>";
    echo "<nav class='top-bar' data-topbar data-options='sticky_on: large'>";
    echo "<ul class='title-area'>";
    echo "<li class='name'>";
    echo "<h1 align='left'><a href='" . htmlspecialchars($urlLink) . "'>$name</a></h1>";
    echo "</li>";
    echo "</ul>";
    echo "</nav>";
    echo "</div>";
}

function new_footer($name = "Arcade Management - Group 5") {
    date_default_timezone_set("America/Chicago");
    echo "<br /><br /><br />";
    echo "<h4><div class='text-center'><small>Copyright {$name}, " . date("M Y") . "</small></div></h4>";
    echo "</body>";
    echo "</html>";
}

function print_alert($message = "") {
    echo "<br />";
    echo "<div class='row'>";
    echo "<div data-alert class='alert-box info round'>" . htmlspecialchars($message) . "</div>";
    echo "</div>";
}

function password_encrypt($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function password_check($password, $existing_hash) {
    return password_verify($password, $existing_hash);
}

?>