<?php
/**
 * using PDO for database connection
 */
$databaseHost = 'localhost';
$databaseName = 'midevsco_price_managment';
$databaseUsername = getenv("user_name");
$databasePassword =  getenv("password");
const PRICE_DATES="price_dates";

$dbh = new PDO('mysql:dbname='.$databaseName.';host='.$databaseHost, $databaseUsername, $databasePassword);
