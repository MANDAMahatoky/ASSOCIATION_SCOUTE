<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();
/*var_dump($_SESSION['utilisateur']);
die();*/
if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user = $_SESSION['utilisateur'];
$db   = getDB();

$peut_valider = in_array($user['type_membre'], ['kp', 'mpanabe']);

$msg    = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$peut_valider) {
        $erreur = "Seuls les mpanabe et kp peuvent valider un talent.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'supprimer') {
            $id_tal = (int)($_POST['id_talenta'] ?? 0);
            if ($id_tal) {
                $db->prepare("DELETE FROM talenta WHERE id_talenta=?")->execute([$id_tal]);
                $msg = 'supprime';
            }
        } else {
            $id_membre       = (int)($_POST['id_membre'] ?? 0);
            $libelle         = trim($_POST['libelle'] ?? '');
            $description     = trim($_POST['description'] ?? '') ?: null;
            $date_validation = $_POST['date_validation'] ?? date('Y-m-d');

            if (!$id_membre || $libelle === '') {
                $erreur = 'Le membre et le libellé sont obligatoires.';
            } else {
                $db->prepare("
                    INSERT INTO talenta (libelle, description, date_validation, id_membre, id_validateur)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$libelle, $description, $date_validation, $id_membre, $user['id_membre']]);
                $msg = 'ajoute';
            }
        }
    }
}

// Liste de tous les talents groupés par membre
$talents = $db->query("
    SELECT t.id_talenta, t.libelle, t.date_validation,
           m.id_membre, m.nom || ' ' || m.prenom AS membre_nom,
           v.nom || ' ' || v.prenom AS validateur_nom
    FROM talenta t
    JOIN mpikambana m ON m.id_membre = t.id_membre
    LEFT JOIN mpikambana v ON v.id_membre = t.id_validateur
    ORDER BY m.nom, t.date_validation DESC
")->fetchAll();

// Regrouper par membre pour affichage
$par_membre = [];
foreach ($talents as $t) {
    $par_membre[$t['id_membre']]['nom'] = $t['membre_nom'];
    $par_membre[$t['id_membre']]['talents'][] = $t;
}

$beazina = $db->query("
    SELECT id_membre, nom, prenom FROM mpikambana
    WHERE type_membre IN ('beazina','mpanabe')
    ORDER BY nom
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talents — Talenta — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .membre-bloc {
            background: var(--blanc);
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .membre-bloc-header {
            background: #f5f0e8;
            padding: 10px 16px;
            font-weight: 700;
            color: var(--vert2);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
        }
        .talent-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .talent-row:last-child { border-bottom: none; }
    </style>
</head>
<body class="with-nav">

<nav>
    <div class="nav-brand"><span>⚜</span> Association Scoute</div>
    <div class="nav-user">
        <span>Bonjour, <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong></span>
        <span class="badge-type"><?= htmlspecialchars($user['type_membre']) ?></span>
        <a href="../../logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
        <div>
            <a href="../../index.php" style="font-size:13px; color:var(--gris); text-decoration:none;">← Tableau de bord</a>
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Talents — Talenta</h2>
        </div>
        <a href="ambaratonga.php" class="btn btn-secondary">⭐ Voir les grades</a>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Talent attribué avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Talent supprimé.</div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if (!$peut_valider): ?>
    <div class="acces-note">Seuls les <strong>mpanabe</strong> et <strong>kp</strong> peuvent attribuer ou supprimer un talent. Vous êtes en lecture seule.</div>
    <?php endif; ?>

    <!-- LISTE PAR MEMBRE -->
    <?php if (empty($par_membre)): ?>
        <div style="text-align:center; color:var(--gris); padding:32px; background:var(--blanc); border:1px solid var(--border); border-radius:6px; margin-bottom:32px;">
            Aucun talent enregistré
        </div>
    <?php else: ?>
        <?php foreach ($par_membre as $id_m => $data): ?>
        <div class="membre-bloc">
            <div class="membre-bloc-header">
                <a href="../membres/detail.php?id=<?= $id_m ?>" style="color:var(--vert2); text-decoration:none;">
                    <?= htmlspecialchars($data['nom']) ?>
                </a>
                <span style="font-weight:400; color:var(--gris); font-size:12px;">— <?= count($data['talents']) ?> talent(s)</span>
            </div>
            <?php foreach ($data['talents'] as $t): ?>
            <div class="talent-row">
                <div>
                    🎯 <strong><?= htmlspecialchars($t['libelle']) ?></strong>
                    <span style="color:var(--gris); font-size:11px; margin-left:8px;">
                        validé le <?= date('d/m/Y', strtotime($t['date_validation'])) ?>
                        <?php if ($t['validateur_nom']): ?> par <?= htmlspecialchars($t['validateur_nom']) ?><?php endif; ?>
                    </span>
                </div>
                <?php if ($peut_valider): ?>
                <form method="POST" onsubmit="return confirm('Supprimer ce talent ?')">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id_talenta" value="<?= $t['id_talenta'] ?>">
                    <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:11px;">Supprimer</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ATTRIBUER UN TALENT -->
    <?php if ($peut_valider): ?>
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden; margin-top:16px;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Attribuer un nouveau talent
        </div>
        <div style="padding:24px;">
            <form method="POST">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
                    <div class="champ">
                        <label for="id_membre">Membre (beazina/mpanabe) *</label>
                        <select id="id_membre" name="id_membre" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($beazina as $b): ?>
                            <option value="<?= $b['id_membre'] ?>"><?= htmlspecialchars($b['nom'] . ' ' . $b['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label for="libelle">Talent *</label>
                        <input type="text" id="libelle" name="libelle" placeholder="Ex: Cuisine, Navigation..." required>
                    </div>
                </div>
                <div class="champ">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" placeholder="Optionnel">
                </div>
                <div class="champ" style="max-width:200px;">
                    <label for="date_validation">Date de validation</label>
                    <input type="date" id="date_validation" name="date_validation" value="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Attribuer</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
