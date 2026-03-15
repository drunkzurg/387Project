<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('Group5_functions.php');
require_once('Group5_session.php');

verify_logout();

new_header("Arcade 1001 Management Login", "index.php");

echo message();

echo '<div id="main">';
echo '<div style="margin-top: 3em">';
echo "<div class='row'>";
echo "<h3>Welcome to Arcade Management!</h3>";

if (isset($_POST["submit"])) {

    if (
        isset($_POST["username"]) && $_POST["username"] !== "" &&
        isset($_POST["password"]) && $_POST["password"] !== ""
    ) {

        $username = trim($_POST["username"]);
        $password = $_POST["password"];

        // Temporary hardcoded login until database is working
        if ($username === "admin" && $password === "password") {
            $_SESSION["user_id"] = 1;
            $_SESSION["username"] = "admin";
            $_SESSION["role"] = "system_admin";
            redirect_by_role();
        } 
        elseif ($username === "owner" && $password === "password") {
            $_SESSION["user_id"] = 2;
            $_SESSION["username"] = "owner";
            $_SESSION["role"] = "owner";
            redirect_by_role();
        } 
        elseif ($username === "manager" && $password === "password") {
            $_SESSION["user_id"] = 3;
            $_SESSION["username"] = "manager";
            $_SESSION["role"] = "manager";
            redirect_by_role();
        } 
        elseif ($username === "employee" && $password === "password") {
            $_SESSION["user_id"] = 4;
            $_SESSION["username"] = "employee";
            $_SESSION["role"] = "employee";
            redirect_by_role();
        } 
        elseif ($username === "hr" && $password === "password") {
            $_SESSION["user_id"] = 5;
            $_SESSION["username"] = "hr";
            $_SESSION["role"] = "hr";
            redirect_by_role();
        } 
        else {
            $_SESSION["message"] = "Username/password not found.";
            redirect("index.php");
        }

    } else {
        $_SESSION["message"] = "Please fill in all information.";
        redirect("index.php");
    }
}
else {

    echo '
    <form method="post" action="index.php">
        <label for="username">Username</label>
        <input type="text" name="username" id="username">

        <label for="password">Password</label>
        <input type="password" name="password" id="password">

        <input type="submit" name="submit" value="Login" class="button">
    </form>';
}

echo '</div></div></div>';

new_footer();

?>