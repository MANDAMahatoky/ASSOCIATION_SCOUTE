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
    // Vérifier pas d'emprunt en cours
    $emprunts = $db->query("SELECT COUNT(*) FROM emprunt WHERE id_materiel=$id AND date_retour_effective IS NULL")->fetchColumn();
    if ($emprunts > 0) {
        header('Location: liste.php?erreur=emprunt_en_cours');
        exit;
    }
    $db->prepare("DELETE FROM materiel WHERE id_materiel=?")->execute([$id]);
}

header('Location: liste.php?msg=supprime');
exit;
