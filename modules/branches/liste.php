<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user = $_SESSION['utilisateur'];
$db   = getDB();

// Filtre par vondrona si passé en GET
$filtre_vondrona = (int)($_GET['vondrona'] ?? 0);

$sql = "
    SELECT b.id_branche, b.nom, b.code,
           v.nom AS vondrona,
           COUNT(m.id_membre) AS nb_membres
    FROM branche b
    JOIN vondrona v ON v.id_vondrona = b.id_vondrona
    LEFT JOIN mpikambana m ON m.id_branche = b.id_branche
";
if ($filtre_vondrona) {
    $sql .= " WHERE b.id_vondrona = $filtre_vondrona";
}
$sql .= " GROUP BY b.id_branche, b.nom, b.code, v.nom ORDER BY v.nom, b.nom";

$branches = $db->query($sql)->fetchAll();

// Pour le filtre select
$vondrona_list = $db->query("SELECT id_vondrona, nom FROM vondrona ORDER BY nom")->fetchAll();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="with-nav bg-vondrona">

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
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Branches</h2>
        </div>
        <?php if ($user['type_membre'] === 'kp'): ?>
        <a href="ajouter.php" class="btn btn-primary">+ Ajouter</a>
        <?php endif; ?>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Branche ajoutée avec succès.</div>
    <?php elseif ($msg === 'modifie'): ?>
        <div class="alerte alerte-succes">Branche modifiée avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Branche supprimée avec succès.</div>
    <?php endif; ?>

    <!-- Filtre par vondrona -->
    <div style="margin-bottom:20px; display:flex; align-items:center; gap:12px;">
        <label style="font-size:12px; color:var(--gris); text-transform:uppercase; letter-spacing:1px;">Filtrer par Vondrona :</label>
        <select onchange="location.href='liste.php?vondrona='+this.value"
                style="padding:8px 12px; border:1.5px solid #d1d5db; border-radius:3px; font-size:14px; width:auto;">
            <option value="0">Tous</option>
            <?php foreach ($vondrona_list as $v): ?>
            <option value="<?= $v['id_vondrona'] ?>" <?= $filtre_vondrona == $v['id_vondrona'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($v['nom']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nom de la Branche</th>
                    <th>Code</th>
                    <th>Vondrona</th>
                    <th>Membres</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($branches)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--gris);">Aucune branche enregistrée</td></tr>
                <?php else: ?>
                <?php foreach ($branches as $b): ?>
                <tr>
                    <td><?= $b['id_branche'] ?></td>
                    <td><strong><?= htmlspecialchars($b['nom']) ?></strong></td>
                    <td>
                        <?php if ($b['code']): ?>
                        <span class="badge" style="background:#f0f0f0; color:#333;"><?= htmlspecialchars($b['code']) ?></span>
                        <?php else: ?>
                        <span style="color:var(--gris);">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($b['vondrona']) ?></td>
                    <td>
                        <a href="../membres/liste.php?branche=<?= $b['id_branche'] ?>"
                           style="color:var(--vert); font-weight:700;">
                            <?= $b['nb_membres'] ?> membre(s)
                        </a>
                    </td>
                    <td style="display:flex; gap:8px;">
                        <?php if ($user['type_membre'] === 'kp'): ?>
                        <a href="modifier.php?id=<?= $b['id_branche'] ?>" class="btn btn-secondary" style="padding:6px 12px; font-size:11px;">Modifier</a>
                        <a href="supprimer.php?id=<?= $b['id_branche'] ?>" class="btn btn-danger" style="padding:6px 12px; font-size:11px;"
                           onclick="return confirm('Supprimer cette branche ? Les membres rattachés seront détachés.')">Supprimer</a>
                        <?php else: ?>
                        <span style="color:var(--gris); font-size:12px;">Lecture seule</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
