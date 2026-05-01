<?php

// clears session cookie then sends users back to the public home page
require_once __DIR__ . '/../src/Auth/Auth.php';

Auth::logout();

header('Location: index.php');
exit;
