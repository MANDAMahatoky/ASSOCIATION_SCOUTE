<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user   = $_SESSION['utilisateur'];
$db     = getDB();
$erreur = '';

$branches = $db->query("SELECT id_branche, nom FROM branche ORDER BY nom")->fetchAll();

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
            INSERT INTO hetsika (titre, description, date_debut, date_fin, lieu, id_branche)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$titre, $description, $date_debut, $date_fin, $lieu, $id_branche]);
        header('Location: liste.php?msg=ajoute');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter Hetsika — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        @media (max-width:600px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="with-nav bg-hetsika">

<nav>
    <div class="nav-brand"><span>⚜</span> Association Scoute</div>
    <div class="nav-user">
        <span><strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong></span>
        <a href="../../logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container" style="max-width:650px;">

    <div style="margin-bottom:24px;">
        <a href="liste.php" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour à la liste</a>
        <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Ajouter une Activité</h2>
    </div>

    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding:32px;">
            <form method="POST">

                <div class="champ">
                    <label for="titre">Titre de l'activité *</label>
                    <input type="text" id="titre" name="titre"
                           value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>"
                           placeholder="Ex: Camp d'été 2026" required>
                </div>

                <div class="champ">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Détails de l'activité..."
                              style="resize:vertical;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="champ">
                        <label for="date_debut">Date de début *</label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="<?= htmlspecialchars($_POST['date_debut'] ?? '') ?>" required>
                    </div>
                    <div class="champ">
                        <label for="date_fin">Date de fin <span style="color:var(--gris); font-weight:400;">(optionnel)</span></label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="<?= htmlspecialchars($_POST['date_fin'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="champ">
                        <label for="lieu">Lieu</label>
                        <input type="text" id="lieu" name="lieu"
                               value="<?= htmlspecialchars($_POST['lieu'] ?? '') ?>"
                               placeholder="Ex: Antsirabe, Ambohimanga...">
                    </div>
                    <div class="champ">
                        <label for="id_branche">Branche organisatrice</label>
                        <select id="id_branche" name="id_branche">
                            <option value="">-- Toutes les branches --</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id_branche'] ?>"
                                <?= ($_POST['id_branche'] ?? '') == $b['id_branche'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
