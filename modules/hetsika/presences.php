<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user = $_SESSION['utilisateur'];
$db   = getDB();
$id   = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: liste.php'); exit; }

$hetsika = $db->query("SELECT * FROM hetsika WHERE id_hetsika = $id")->fetch();
if (!$hetsika) { header('Location: liste.php'); exit; }

$msg    = '';
$erreur = '';

// Traitement ajout/modification présence
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $id_branche = (int)($_POST['id_branche'] ?? 0);
    $nb         = (int)($_POST['nb_presents'] ?? 0);

    if ($action === 'supprimer' && $id_branche) {
        $db->prepare("DELETE FROM presence WHERE id_hetsika=? AND id_branche=?")->execute([$id, $id_branche]);
        $msg = 'supprime';
    } elseif ($id_branche && $nb >= 0) {
        $db->prepare("
            INSERT INTO presence (id_hetsika, id_branche, nb_presents)
            VALUES (?, ?, ?)
            ON CONFLICT (id_hetsika, id_branche)
            DO UPDATE SET nb_presents = EXCLUDED.nb_presents
        ")->execute([$id, $id_branche, $nb]);
        $msg = 'sauvegarde';
    } else {
        $erreur = 'Veuillez sélectionner une branche et indiquer le nombre de présents.';
    }
}

// Présences existantes
$presences = $db->query("
    SELECT p.id_branche, p.nb_presents, b.nom AS branche
    FROM presence p
    JOIN branche b ON b.id_branche = p.id_branche
    WHERE p.id_hetsika = $id
    ORDER BY b.nom
")->fetchAll();

$branches_presentes = array_column($presences, 'id_branche');

// Branches disponibles (pas encore ajoutées)
$branches_dispo = $db->query("
    SELECT id_branche, nom FROM branche
    WHERE id_branche NOT IN (" . (empty($branches_presentes) ? '0' : implode(',', $branches_presentes)) . ")
    ORDER BY nom
")->fetchAll();

$total = array_sum(array_column($presences, 'nb_presents'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Présences — <?= htmlspecialchars($hetsika['titre']) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="with-nav">

<nav>
    <div class="nav-brand"><span>⚜</span> Association Scoute</div>
    <div class="nav-user">
        <span><strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong></span>
        <a href="../../logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container" style="max-width:700px;">

    <div style="margin-bottom:24px;">
        <a href="detail.php?id=<?= $id ?>" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour à l'activité</a>
        <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">
            Présences — <?= htmlspecialchars($hetsika['titre']) ?>
        </h2>
    </div>

    <?php if ($msg === 'sauvegarde'): ?>
        <div class="alerte alerte-succes">Présence enregistrée.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Présence supprimée.</div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <!-- PRÉSENCES ACTUELLES -->
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden; margin-bottom:24px;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; display:flex; justify-content:space-between;">
            <span>Branches présentes (<?= count($presences) ?>)</span>
            <span><?= $total ?> présents au total</span>
        </div>
        <?php if (empty($presences)): ?>
            <div style="padding:20px; color:var(--gris); font-size:13px; text-align:center;">Aucune présence enregistrée</div>
        <?php else: ?>
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead style="background:#f5f0e8;">
                    <tr>
                        <th style="padding:10px 16px; text-align:left; font-size:11px; color:var(--gris); text-transform:uppercase;">Branche</th>
                        <th style="padding:10px 16px; text-align:center; font-size:11px; color:var(--gris); text-transform:uppercase;">Nb présents</th>
                        <th style="padding:10px 16px; text-align:center; font-size:11px; color:var(--gris); text-transform:uppercase;">Modifier</th>
                        <th style="padding:10px 16px; text-align:center; font-size:11px; color:var(--gris); text-transform:uppercase;">Supprimer</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($presences as $p): ?>
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:10px 16px;">🌿 <strong><?= htmlspecialchars($p['branche']) ?></strong></td>
                    <td style="padding:10px 16px; text-align:center;">
                        <form method="POST" style="display:flex; align-items:center; justify-content:center; gap:8px;">
                            <input type="hidden" name="id_branche" value="<?= $p['id_branche'] ?>">
                            <input type="number" name="nb_presents" value="<?= $p['nb_presents'] ?>"
                                   min="0" style="width:70px; padding:4px 8px; text-align:center; border:1.5px solid #d1d5db; border-radius:3px;">
                            <button type="submit" class="btn btn-primary" style="padding:4px 12px; font-size:11px;">OK</button>
                        </form>
                    </td>
                    <td></td>
                    <td style="padding:10px 16px; text-align:center;">
                        <form method="POST" onsubmit="return confirm('Supprimer cette présence ?')">
                            <input type="hidden" name="id_branche" value="<?= $p['id_branche'] ?>">
                            <input type="hidden" name="action" value="supprimer">
                            <button type="submit" class="btn btn-danger" style="padding:4px 12px; font-size:11px;">Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- AJOUTER UNE PRÉSENCE -->
    <?php if (!empty($branches_dispo)): ?>
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Ajouter une branche
        </div>
        <div style="padding:20px;">
            <form method="POST" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <div class="champ" style="margin:0; flex:1; min-width:180px;">
                    <label>Branche</label>
                    <select name="id_branche" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($branches_dispo as $b): ?>
                        <option value="<?= $b['id_branche'] ?>"><?= htmlspecialchars($b['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ" style="margin:0; width:120px;">
                    <label>Nb présents</label>
                    <input type="number" name="nb_presents" min="0" value="0" required>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-bottom:1px;">Ajouter</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="alerte alerte-succes">Toutes les branches ont été enregistrées.</div>
    <?php endif; ?>

</div>

<footer>Association Scoute &mdash; <?= date('Y') ?></footer>
</body>
</html>
