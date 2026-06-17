<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }
if ($_SESSION['utilisateur']['type_membre'] !== 'kp') { header('Location: liste.php'); exit; }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $db->prepare("DELETE FROM branche WHERE id_branche = ?")->execute([$id]);
}

header('Location: liste.php?msg=supprime');
exit;
