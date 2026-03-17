<?php
require_once('Group5_functions.php');
require_once('Group5_session.php');
require_once('Group5_database.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$mysqli = Group5_Database::DBConnect();
$mysqli->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

verify_logout();

new_header("Arcade 101 Management Login");

if (($output = message()) !== NULL){ 
echo $output();
}


echo '<div id="main">';
echo '<div style="margin-top: 3em">';
echo "<div class='row'>";
echo "<h3>Welcome to Arcade 101 Management Database!</h3>";

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

        $stmt = $mysqli -> prepare ($query);
        $stmt->execute([username]);

        if ($stmt){
            $row = $stmt -> fetch(PDO::FETCH_ASSOC);
            

        if ($row && password_check($password, $row["password_hash"])) {

                if ((int)$row["is_active"] !== 1) {
                    $_SESSION["message"] = "Account inactive.";
                    redirect("index.php");
                }

                $_SESSION["username"] = $row["username"];
                $_SESSION["user_id"] = $row["user_id"];
                $_SESSION["role"] = $row["role"];

                redirect_by_role();
            }
            else {
                $_SESSION["message"] = "Username/Password not found.";
                redirect("index.php");
            }
        }
        else {
            $_SESSION["message"] = "Login error.";
            redirect("index.php");
        }
    }
    else {
        $_SESSION["message"] = "Fill in all fields.";
        redirect("index.php");
    }
}
else {
    echo '<form action="index.php" method="post">';
    echo '<p>Username: <input type="text" name="username" /></p>';
    echo '<p>Password: <input type="password" name="password" /></p>';
    echo "<input type='submit' name='submit' value='Login' class='button tiny round' />";
    echo '</form>';
}

echo "<br /><br /><a href='create_user.php'>Create Account</a>";

echo '</div></div></div>';

new_footer();

Group5_Database::DBDisconnect();

?>
