<?php
require_once __DIR__ . '/config/db.php';

$login = 'mettez_votre_login_ici';
$mdp   = 'mettez_votre_mot_de_passe_ici';

$st = getDB()->prepare("
    SELECT u.*, m.nom, m.prenom, m.type_membre
    FROM UTILISATEUR u
    JOIN MEMBRE m ON m.id_membre = u.id_membre
    WHERE u.login = ?
");
$st->execute([$login]);
$user = $st->fetch();

if (!$user) {
    echo "❌ Utilisateur introuvable en base";
} else {
    echo "✅ Utilisateur trouvé<br>";
    echo "actif : " . $user['actif'] . "<br>";
    echo "type_membre : " . $user['type_membre'] . "<br>";
    echo "password_verify : " . (password_verify($mdp, $user['mot_de_passe']) ? '✅ OK' : '❌ ÉCHEC') . "<br>";
    echo "mot_de_passe en DB commence par : " . substr($user['mot_de_passe'], 0, 7) . "<br>";
}
