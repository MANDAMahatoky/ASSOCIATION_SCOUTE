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

$vondrona = $db->query("SELECT id_vondrona, nom FROM vondrona ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nom'] ?? '');
    $code       = trim($_POST['code'] ?? '') ?: null;
    $id_vondrona = (int)($_POST['id_vondrona'] ?? 0);

    if ($nom === '' || $id_vondrona === 0) {
        $erreur = 'Le nom et le vondrona sont obligatoires.';
    } else {
        $stmt = $db->prepare("INSERT INTO branche (nom, code, id_vondrona) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $code, $id_vondrona]);
        header('Location: liste.php?msg=ajoute');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter Branche — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="with-nav">

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
        <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Ajouter une Branche</h2>
    </div>

    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding:32px;">
            <form method="POST">
                <div class="champ">
                    <label for="nom">Nom de la branche</label>
                    <input type="text" id="nom" name="nom"
                           value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                           placeholder="Ex: Lovitao, Tily, Afo..." required>
                </div>
                <div class="champ">
                    <label for="code">Code <span style="color:var(--gris); font-weight:400;">(optionnel)</span></label>
                    <input type="text" id="code" name="code"
                           value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
                           placeholder="Ex: LOV, TIL, AFO" maxlength="20">
                </div>
                <div class="champ">
                    <label for="id_vondrona">Vondrona</label>
                    <select id="id_vondrona" name="id_vondrona" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($vondrona as $v): ?>
                        <option value="<?= $v['id_vondrona'] ?>"
                            <?= ($_POST['id_vondrona'] ?? '') == $v['id_vondrona'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['nom']) ?>
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
