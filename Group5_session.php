<?php
session_start();
	
	$now = time();
	if (isset($_SESSION["discard_after"]) && $now > $_SESSION["discard_after"]) {
		// this session has worn out its welcome; kill it and start a brand new one
		session_unset();
		session_destroy();
		session_start();
	}

	// either new or old, it should live at most for another hour
	$_SESSION["discard_after"] = $now + 3600;
	//$_SESSION["discard_after"] = $now + 30;

	function message() {
		if (isset($_SESSION["message"])) {
			
			$output = "<div class='row'>";
			$output .= "<div data-alert class='alert-box info round'>";
			$output .= htmlentities($_SESSION["message"]);
			$output .= "</div>";
			$output .= "</div>";
			
			// clear message after use
			$_SESSION["message"] = null;
			
			return $output;
		}
		else {
			return null;
		}
	}

	function errors() {
		if (isset($_SESSION["errors"])) {
			$errors = $_SESSION["errors"];
			
			// clear message after use
			$_SESSION["errors"] = null;
			
			return $errors;
		}
	}
	function verify_login() {
		if(!isset($_SESSION["user_id"]) && $_SESSION["user_id"] === NULL) {
		$_SESSION["message"] = "You must login in first!";
		header("Location: index.php");
		exit;
	}
}


function verify_logout() {
    if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] !== null) {
        redirect_by_role();
        exit;
    }
}

function verify_role($allowed_roles = []) {
    verify_login();

    if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], $allowed_roles)) {
        $_SESSION["message"] = "Access denied.";
        redirect_by_role();
        exit;
    }
}

function verify_admin() {
    verify_role(["system_admin"]);
}

function verify_owner() {
    verify_role(["owner"]);
}

function verify_manager() {
    verify_role(["manager"]);
}

function verify_employee() {
    verify_role(["employee"]);
}

function verify_hr() {
    verify_role(["hr"]);
}

function redirect_by_role() {
    if (!isset($_SESSION["role"])) {
        header("Location: index.php");
        exit;
    }

    switch ($_SESSION["role"]) {
        case "system_admin":
            header("Location: admin.php");
            exit;
        case "owner":
            header("Location: owner.php");
            exit;
        case "manager":
            header("Location: manager.php");
            exit;
        case "employee":
            header("Location: employee.php");
            exit;
        case "hr":
            header("Location: hr.php");
            exit;
        default:
            header("Location: index.php");
            exit;
    }
}
?>
