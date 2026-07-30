<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }
if ($_SESSION['utilisateur']['type_membre'] !== 'kp') { header('Location: liste.php'); exit; }

$db     = getDB();
$erreur = '';

// Récupérer les fivondronana pour le select
$fivondronana = $db->query("SELECT id_fivondronana, nom FROM fivondronana ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom   = trim($_POST['nom'] ?? '');
    $id_fi = (int)($_POST['id_fivondronana'] ?? 0);

    if ($nom === '' || $id_fi === 0) {
        $erreur = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $db->prepare("INSERT INTO vondrona (nom, id_fivondronana) VALUES (?, ?)");
        $stmt->execute([$nom, $id_fi]);
        header('Location: liste.php?msg=ajoute');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Vondrona — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="with-nav bg-vondrona">

<nav>
    <div class="nav-brand"><span>⚜</span> Association Scoute</div>
    <div class="nav-user">
        <span><strong><?= htmlspecialchars($_SESSION['utilisateur']['prenom'] . ' ' . $_SESSION['utilisateur']['nom']) ?></strong></span>
        <a href="../../logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container" style="max-width:500px;">

    <div style="margin-bottom:24px;">
        <a href="liste.php" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour à la liste</a>
        <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Ajouter un Vondrona</h2>
    </div>

    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding:32px;">
            <form method="POST">
                <div class="champ">
                    <label for="nom">Nom du Vondrona</label>
                    <input type="text" id="nom" name="nom"
                           value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                           placeholder="Ex: Tily, Mpanazava" required>
                </div>
                <div class="champ">
                    <label for="id_fivondronana">Fivondronana</label>
                    <select id="id_fivondronana" name="id_fivondronana" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($fivondronana as $f): ?>
                        <option value="<?= $f['id_fivondronana'] ?>"
                            <?= ($_POST['id_fivondronana'] ?? '') == $f['id_fivondronana'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <a href="liste.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>

</div>

<footer>Association Scoute &mdash; <?= date('Y') ?></footer>
</body>
</html>
