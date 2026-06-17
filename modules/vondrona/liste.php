<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) {
    header('Location: ../../login.php');
    exit;
}

$user = $_SESSION['utilisateur'];
$db   = getDB();

// Récupérer tous les vondrona avec leur fivondronana et nombre de branches
$vondrona = $db->query("
    SELECT v.id_vondrona, v.nom, f.nom AS fivondronana,
           COUNT(b.id_branche) AS nb_branches
    FROM vondrona v
    JOIN fivondronana f ON f.id_fivondronana = v.id_fivondronana
    LEFT JOIN branche b ON b.id_vondrona = v.id_vondrona
    GROUP BY v.id_vondrona, v.nom, f.nom
    ORDER BY v.id_vondrona
")->fetchAll();

// Message de succès/erreur
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vondrona — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
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
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Vondrona</h2>
        </div>
        <?php if ($user['type_membre'] === 'kp'): ?>
        <a href="ajouter.php" class="btn btn-primary">+ Ajouter</a>
        <?php endif; ?>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Vondrona ajouté avec succès.</div>
    <?php elseif ($msg === 'modifie'): ?>
        <div class="alerte alerte-succes">Vondrona modifié avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Vondrona supprimé avec succès.</div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nom du Vondrona</th>
                    <th>Fivondronana</th>
                    <th>Nb Branches</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vondrona)): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--gris);">Aucun vondrona enregistré</td></tr>
                <?php else: ?>
                <?php foreach ($vondrona as $v): ?>
                <tr>
                    <td><?= $v['id_vondrona'] ?></td>
                    <td><strong><?= htmlspecialchars($v['nom']) ?></strong></td>
                    <td><?= htmlspecialchars($v['fivondronana']) ?></td>
                    <td>
                        <a href="../branches/liste.php?vondrona=<?= $v['id_vondrona'] ?>" 
                           style="color:var(--vert); font-weight:700;">
                            <?= $v['nb_branches'] ?> branche(s)
                        </a>
                    </td>
                    <td style="display:flex; gap:8px;">
                        <?php if ($user['type_membre'] === 'kp'): ?>
                        <a href="modifier.php?id=<?= $v['id_vondrona'] ?>" class="btn btn-secondary" style="padding:6px 12px; font-size:11px;">Modifier</a>
                        <a href="supprimer.php?id=<?= $v['id_vondrona'] ?>" class="btn btn-danger" style="padding:6px 12px; font-size:11px;"
                           onclick="return confirm('Supprimer ce vondrona ?')">Supprimer</a>
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
