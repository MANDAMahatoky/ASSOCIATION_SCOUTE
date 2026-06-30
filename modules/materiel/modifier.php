<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }
if ($_SESSION['utilisateur']['type_membre'] !== 'kp') { header('Location: liste.php'); exit; }

$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);
$erreur = '';

if (!$id) { header('Location: liste.php'); exit; }

$materiel = $db->query("SELECT * FROM materiel WHERE id_materiel = $id")->fetch();
$branches = $db->query("SELECT id_branche, nom FROM branche ORDER BY nom")->fetchAll();

if (!$materiel) { header('Location: liste.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom         = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $etat        = $_POST['etat'] ?? '';
    $quantite    = (int)($_POST['quantite'] ?? 1);
    $id_branche  = $_POST['id_branche'] ? (int)$_POST['id_branche'] : null;

    if ($nom === '' || $etat === '') {
        $erreur = 'Le nom et l\'état sont obligatoires.';
    } elseif ($quantite < 1) {
        $erreur = 'La quantité doit être au moins 1.';
    } else {
        $db->prepare("
            UPDATE materiel SET nom=?, description=?, etat=?, quantite=?, id_branche=?
            WHERE id_materiel=?
        ")->execute([$nom, $description, $etat, $quantite, $id_branche, $id]);
        header('Location: liste.php?msg=modifie');
        exit;
    }
}

$v = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $materiel;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier Matériel — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; } @media(max-width:600px){.form-grid{grid-template-columns:1fr;}}</style>
</head>
<body class="with-nav">

<nav>
    <div class="nav-brand"><span>⚜</span> Association Scoute</div>
    <div class="nav-user">
        <span><strong><?= htmlspecialchars($_SESSION['utilisateur']['prenom'] . ' ' . $_SESSION['utilisateur']['nom']) ?></strong></span>
        <a href="../../logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container" style="max-width:600px;">

    <div style="margin-bottom:24px;">
        <a href="liste.php" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour à la liste</a>
        <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Modifier — <?= htmlspecialchars($materiel['nom']) ?></h2>
    </div>

    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding:32px;">
            <form method="POST">

                <div class="champ">
                    <label for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($v['nom'] ?? '') ?>" required>
                </div>

                <div class="champ">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="2"
                              style="resize:vertical;"><?= htmlspecialchars($v['description'] ?? '') ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="champ">
                        <label for="etat">État *</label>
                        <select id="etat" name="etat" required>
                            <option value="neuf"      <?= ($v['etat'] ?? '') === 'neuf'      ? 'selected' : '' ?>>Neuf</option>
                            <option value="usagé"     <?= ($v['etat'] ?? '') === 'usagé'     ? 'selected' : '' ?>>Usagé</option>
                            <option value="à réparer" <?= ($v['etat'] ?? '') === 'à réparer' ? 'selected' : '' ?>>À réparer</option>
                        </select>
                    </div>
                    <div class="champ">
                        <label for="quantite">Quantité *</label>
                        <input type="number" id="quantite" name="quantite"
                               value="<?= htmlspecialchars($v['quantite'] ?? '1') ?>" min="1" required>
                    </div>
                </div>

                <div class="champ">
                    <label for="id_branche">Propriétaire</label>
                    <select id="id_branche" name="id_branche">
                        <option value="">🏕️ Fivondronana (matériel commun)</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id_branche'] ?>"
                            <?= ($v['id_branche'] ?? '') == $b['id_branche'] ? 'selected' : '' ?>>
                            🌿 <?= htmlspecialchars($b['nom']) ?>
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
