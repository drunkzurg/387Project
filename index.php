<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('Group5_functions.php');
require_once('Group5_session.php');
require_once('Group5_database.php');

verify_logout();

new_header("Arcade 101 Management Login", "index.php");

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

        $sql = "SELECT user_id, username, password_hash, role, is_active
                FROM USERS
                WHERE username = :username 
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch();

        if ($user) {

            if ((int)$user["is_active"] !== 1) {
                $_SESSION["message"] = "Account is inactive.";
                redirect("index.php");
            }

            if (password_check($password, $user["password_hash"])) {
                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];

                redirect_by_role();
            } else {
                $_SESSION["message"] = "Invalid username or password.";
                redirect("index.php");
            }

        } else {
            $_SESSION["message"] = "Invalid username or password.";
            redirect("index.php");
        }

    } else {
        $_SESSION["message"] = "Please fill in all information.";
        redirect("index.php");
    }

} else {

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
