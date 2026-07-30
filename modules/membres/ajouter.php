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

$branches     = $db->query("SELECT id_branche, nom FROM branche ORDER BY nom")->fetchAll();
$fivondronana = $db->query("SELECT id_fivondronana, nom FROM fivondronana ORDER BY nom")->fetchAll();
$types        = ['beazina', 'mpanabe', 'kp', 'mpanohana', 'ray aman-dreny'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom            = trim($_POST['nom'] ?? '');
    $prenom         = trim($_POST['prenom'] ?? '');
    $type_membre    = $_POST['type_membre'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? null;
    $adresse        = trim($_POST['adresse'] ?? '') ?: null;
    $telephone      = trim($_POST['telephone'] ?? '') ?: null;
    $email          = trim($_POST['email'] ?? '') ?: null;
    $code_membre    = trim($_POST['code_membre'] ?? '') ?: null;
    $fiantso        = trim($_POST['fiantso'] ?? '') ?: null;
    $id_branche     = $_POST['id_branche'] ? (int)$_POST['id_branche'] : null;
    $id_fivondronana = $_POST['id_fivondronana'] ? (int)$_POST['id_fivondronana'] : null;

    if ($nom === '' || $prenom === '' || $type_membre === '') {
        $erreur = 'Nom, prénom et type sont obligatoires.';
    } elseif (in_array($type_membre, ['beazina', 'mpanabe']) && !$id_branche) {
        $erreur = 'Un beazina ou mpanabe doit être rattaché à une branche.';
    } else {
        $stmt = $db->prepare("
            INSERT INTO mpikambana
                (nom, prenom, type_membre, date_naissance, adresse, telephone, email,
                 code_membre, fiantso, id_branche, id_fivondronana)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nom, $prenom, $type_membre,
            $date_naissance ?: null,
            $adresse, $telephone, $email,
            $code_membre, $fiantso,
            $id_branche, $id_fivondronana
        ]);
        header('Location: liste.php?msg=ajoute');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter Membre — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
        #section-branche, #section-fivondronana { display: none; }
    </style>
</head>
<body class="with-nav bg-membres">

<nav>
    <div class="nav-brand"><span>⚜</span> Association Scoute</div>
    <div class="nav-user">
        <span><strong><?= htmlspecialchars($_SESSION['utilisateur']['prenom'] . ' ' . $_SESSION['utilisateur']['nom']) ?></strong></span>
        <a href="../../logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container" style="max-width:700px;">

    <div style="margin-bottom:24px;">
        <a href="liste.php" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour à la liste</a>
        <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Ajouter un Membre</h2>
    </div>

    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="card" style="box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding:32px;">
            <form method="POST">

                <div class="form-grid">
                    <div class="champ">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom"
                               value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                    </div>
                    <div class="champ">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom"
                               value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="champ">
                        <label for="type_membre">Type de membre *</label>
                        <select id="type_membre" name="type_membre" required onchange="toggleRattachement(this.value)">
                            <option value="">-- Choisir --</option>
                            <?php foreach ($types as $t): ?>
                            <option value="<?= $t ?>" <?= ($_POST['type_membre'] ?? '') === $t ? 'selected' : '' ?>>
                                <?= ucfirst($t) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label for="code_membre">Code membre</label>
                        <input type="text" id="code_membre" name="code_membre"
                               value="<?= htmlspecialchars($_POST['code_membre'] ?? '') ?>"
                               placeholder="Ex: BEA001">
                    </div>
                </div>

                <!-- Rattachement branche (beazina / mpanabe) -->
                <div id="section-branche" class="champ">
                    <label for="id_branche">Branche *</label>
                    <select id="id_branche" name="id_branche">
                        <option value="">-- Choisir une branche --</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id_branche'] ?>"
                            <?= ($_POST['id_branche'] ?? '') == $b['id_branche'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Rattachement fivondronana (kp / mpanohana / ray aman-dreny) -->
                <div id="section-fivondronana" class="champ">
                    <label for="id_fivondronana">Fivondronana *</label>
                    <select id="id_fivondronana" name="id_fivondronana">
                        <option value="">-- Choisir --</option>
                        <?php foreach ($fivondronana as $f): ?>
                        <option value="<?= $f['id_fivondronana'] ?>"
                            <?= ($_POST['id_fivondronana'] ?? '') == $f['id_fivondronana'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="champ">
                        <label for="date_naissance">Date de naissance</label>
                        <input type="date" id="date_naissance" name="date_naissance"
                               value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>">
                    </div>
                    <div class="champ">
                        <label for="fiantso">Fiantso (surnom scout)</label>
                        <input type="text" id="fiantso" name="fiantso"
                               value="<?= htmlspecialchars($_POST['fiantso'] ?? '') ?>">
                    </div>
                </div>

                <div class="champ">
                    <label for="adresse">Adresse</label>
                    <input type="text" id="adresse" name="adresse"
                           value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>">
                </div>

                <div class="form-grid">
                    <div class="champ">
                        <label for="telephone">Téléphone</label>
                        <input type="text" id="telephone" name="telephone"
                               value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>"
                               placeholder="034 00 000 00">
                    </div>
                    <div class="champ">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
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

<script>
function toggleRattachement(type) {
    const branche      = document.getElementById('section-branche');
    const fivondronana = document.getElementById('section-fivondronana');
    branche.style.display      = ['beazina','mpanabe'].includes(type) ? 'block' : 'none';
    fivondronana.style.display = ['kp','mpanohana','ray aman-dreny'].includes(type) ? 'block' : 'none';
}
// Init au chargement si valeur déjà sélectionnée (erreur de validation)
toggleRattachement(document.getElementById('type_membre').value);
</script>

<footer>Association Scoute &mdash; <?= date('Y') ?></footer>
</body>
</html>
