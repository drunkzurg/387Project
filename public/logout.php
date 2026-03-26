<?php

require_once __DIR__ . '/../src/Auth/Auth.php';

Auth::logout();

header('Location: login.php');
exit;
