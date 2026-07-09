<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'wp-config.php';
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "DB_PASSWORD: " . DB_PASSWORD . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
?>
