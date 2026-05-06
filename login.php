<?php
session_start();

// Si déjà connecté, rediriger vers index
if (isset($_SESSION['utilisateur'])) {
    header('Location: index.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/db.php';

    $login = trim($_POST['login'] ?? '');
    $mdp   = trim($_POST['mot_de_passe'] ?? '');

    if ($login === '' || $mdp === '') {
        $erreur = 'Veuillez remplir tous les champs.';
    } else {
        $st = getDB()->prepare("
            SELECT u.*, m.nom, m.prenom, m.type_membre
            FROM UTILISATEUR u
            JOIN MEMBRE m ON m.id_membre = u.id_membre
            WHERE u.login = ? AND u.actif = 1
        ");
        $st->execute([$login]);
        $user = $st->fetch();

        if (!$user || !password_verify($mdp, $user['mot_de_passe'])) {
            $erreur = 'Identifiant ou mot de passe incorrect.';
        } elseif (!in_array($user['type_membre'], ['kp', 'mpanabe'])) {
            $erreur = 'Accès réservé aux membres KP et Mpanabe.';
        } else {
            // Connexion réussie
            $_SESSION['utilisateur'] = [
                'id'          => $user['id_utilisateur'],
                'id_membre'   => $user['id_membre'],
                'login'       => $user['login'],
                'nom'         => $user['nom'],
                'prenom'      => $user['prenom'],
                'type_membre' => $user['type_membre'],
            ];

            // Mettre à jour la date de dernière connexion
            $upd = getDB()->prepare("UPDATE UTILISATEUR SET derniere_connexion = NOW() WHERE id_utilisateur = ?");
            $upd->execute([$user['id_utilisateur']]);

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Association Scoute</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
<div class="card">
    <div class="card-header">
        <img src="assets/images/logo.png" alt="Logo Association Scoute" height="80">
        <h1>Association Scoute</h1>
        <p>Espace de gestion</p>
    </div>
    <div class="card-body">

        <?php if ($erreur): ?>
            <div class="alerte"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <div class="acces-note">
            Accès réservé aux membres <strong>KP</strong> et <strong>Mpanabe</strong>
        </div>

        <form method="POST" action="login.php">
            <div class="champ">
                <label for="login">Identifiant</label>
                <input type="text" id="login" name="login"
                       value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                       placeholder="Votre login" required autofocus>
            </div>
            <div class="champ">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn">Se connecter</button>
        </form>

        <p class="footer-note">Fivondronana Antananarivo &mdash; <?= date('Y') ?></p>
    </div>
</div>
</body>
</html>
