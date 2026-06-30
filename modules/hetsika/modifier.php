<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user   = $_SESSION['utilisateur'];
$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);
$erreur = '';

if (!$id) { header('Location: liste.php'); exit; }

$hetsika  = $db->query("SELECT * FROM hetsika WHERE id_hetsika = $id")->fetch();
$branches = $db->query("SELECT id_branche, nom FROM branche ORDER BY nom")->fetchAll();

if (!$hetsika) { header('Location: liste.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $date_debut  = $_POST['date_debut'] ?? '';
    $date_fin    = $_POST['date_fin'] ?: null;
    $lieu        = trim($_POST['lieu'] ?? '') ?: null;
    $id_branche  = $_POST['id_branche'] ? (int)$_POST['id_branche'] : null;

    if ($titre === '' || $date_debut === '') {
        $erreur = 'Le titre et la date de début sont obligatoires.';
    } elseif ($date_fin && $date_fin < $date_debut) {
        $erreur = 'La date de fin ne peut pas être avant la date de début.';
    } else {
        $stmt = $db->prepare("
            UPDATE hetsika SET titre=?, description=?, date_debut=?, date_fin=?, lieu=?, id_branche=?
            WHERE id_hetsika=?
        ");
        $stmt->execute([$titre, $description, $date_debut, $date_fin, $lieu, $id_branche, $id]);
        header('Location: detail.php?id=' . $id . '&msg=modifie');
        exit;
    }
}

$v = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $hetsika;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier Hetsika — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        @media (max-width:600px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="with-nav">

<nav>
    <div class="nav-brand"><span>⚜</span> Association Scoute</div>
    <div class="nav-user">
        <span><strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong></span>
        <a href="../../logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container" style="max-width:650px;">

    <div style="margin-bottom:24px;">
        <a href="detail.php?id=<?= $id ?>" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour au détail</a>
        <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Modifier l'activité</h2>
    </div>

    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding:32px;">
            <form method="POST">

                <div class="champ">
                    <label for="titre">Titre *</label>
                    <input type="text" id="titre" name="titre"
                           value="<?= htmlspecialchars($v['titre'] ?? '') ?>" required>
                </div>

                <div class="champ">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"
                              style="resize:vertical;"><?= htmlspecialchars($v['description'] ?? '') ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="champ">
                        <label for="date_debut">Date de début *</label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="<?= htmlspecialchars($v['date_debut'] ?? '') ?>" required>
                    </div>
                    <div class="champ">
                        <label for="date_fin">Date de fin</label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="<?= htmlspecialchars($v['date_fin'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="champ">
                        <label for="lieu">Lieu</label>
                        <input type="text" id="lieu" name="lieu"
                               value="<?= htmlspecialchars($v['lieu'] ?? '') ?>">
                    </div>
                    <div class="champ">
                        <label for="id_branche">Branche organisatrice</label>
                        <select id="id_branche" name="id_branche">
                            <option value="">-- Toutes les branches --</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id_branche'] ?>"
                                <?= ($v['id_branche'] ?? '') == $b['id_branche'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <a href="detail.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
                </div>

            </form>
        </div>
    </div>

</div>

<footer>Association Scoute &mdash; <?= date('Y') ?></footer>
</body>
</html>
