<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);

session_start();
session_destroy();
header('Location: login.php');
exit;
