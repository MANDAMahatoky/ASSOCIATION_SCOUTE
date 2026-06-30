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

$hetsika = $db->prepare("
    SELECT h.*, b.nom AS branche_nom
    FROM hetsika h
    LEFT JOIN branche b ON b.id_branche = h.id_branche
    WHERE h.id_hetsika = ?
");
$hetsika->execute([$id]);
$h = $hetsika->fetch();

if (!$h) { header('Location: liste.php'); exit; }

// Présences
$presences = $db->prepare("
    SELECT b.nom AS branche, p.nb_presents
    FROM presence p
    JOIN branche b ON b.id_branche = p.id_branche
    WHERE p.id_hetsika = ?
    ORDER BY b.nom
");
$presences->execute([$id]);
$presences = $presences->fetchAll();

$total_presents = array_sum(array_column($presences, 'nb_presents'));

// Récompenses
$recompenses = $db->prepare("
    SELECT r.libelle, r.type, r.date_attribution,
           m.nom || ' ' || m.prenom AS membre_nom,
           b.nom AS branche_nom
    FROM recompense r
    LEFT JOIN mpikambana m ON m.id_membre = r.id_membre
    LEFT JOIN branche b ON b.id_branche = r.id_branche
    WHERE r.id_hetsika = ?
    ORDER BY r.date_attribution
");
$recompenses->execute([$id]);
$recompenses = $recompenses->fetchAll();

// Statut
$today = date('Y-m-d');
if ($h['date_fin'] && $h['date_fin'] < $today) {
    $statut = 'Passée'; $statut_color = 'var(--gris)';
} elseif ($h['date_debut'] > $today) {
    $statut = 'À venir'; $statut_color = '#3C3489';
} else {
    $statut = 'En cours'; $statut_color = 'var(--vert2)';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($h['titre']) ?> — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        @media (max-width:700px) { .detail-grid { grid-template-columns: 1fr; } }
        .detail-card { background: var(--blanc); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
        .detail-card-header { background: var(--vert2); color: #fff; padding: 10px 16px; font-family: 'Cinzel', serif; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
        .detail-card-body { padding: 16px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
        .info-row:last-child { border-bottom: none; }
        .info-row .key { color: var(--gris); }
        .info-row .val { font-weight: 600; }
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

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <a href="liste.php" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour à la liste</a>
        <div style="display:flex; gap:8px;">
            <a href="presences.php?id=<?= $id ?>" class="btn btn-secondary">Gérer présences</a>
            <a href="recompenses.php?id=<?= $id ?>" class="btn btn-secondary">Gérer récompenses</a>
            <a href="modifier.php?id=<?= $id ?>" class="btn btn-secondary">Modifier</a>
            <a href="supprimer.php?id=<?= $id ?>" class="btn btn-danger"
               onclick="return confirm('Supprimer cette activité ?')">Supprimer</a>
        </div>
    </div>

    <!-- EN-TÊTE -->
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; padding:24px; margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-bottom:8px;"><?= htmlspecialchars($h['titre']) ?></h2>
                <div style="display:flex; gap:16px; flex-wrap:wrap; font-size:13px; color:var(--gris);">
                    <span>📅 <?= date('d/m/Y', strtotime($h['date_debut'])) ?>
                        <?php if ($h['date_fin'] && $h['date_fin'] !== $h['date_debut']): ?>
                            → <?= date('d/m/Y', strtotime($h['date_fin'])) ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($h['lieu']): ?>
                    <span>📍 <?= htmlspecialchars($h['lieu']) ?></span>
                    <?php endif; ?>
                    <?php if ($h['branche_nom']): ?>
                    <span>🌿 <?= htmlspecialchars($h['branche_nom']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($h['description']): ?>
                <p style="margin-top:12px; font-size:14px; color:var(--texte); line-height:1.6;">
                    <?= nl2br(htmlspecialchars($h['description'])) ?>
                </p>
                <?php endif; ?>
            </div>
            <span style="font-size:12px; font-weight:700; color:<?= $statut_color ?>; background:rgba(0,0,0,0.04); padding:4px 12px; border-radius:20px;">
                <?= $statut ?>
            </span>
        </div>
    </div>

    <div class="detail-grid">

        <!-- PRÉSENCES -->
        <div class="detail-card">
            <div class="detail-card-header">
                <span>Présences — <?= count($presences) ?> branche(s)</span>
                <span style="font-size:12px; font-weight:400;"><?= $total_presents ?> présents au total</span>
            </div>
            <div class="detail-card-body">
                <?php if (empty($presences)): ?>
                    <p style="color:var(--gris); font-size:13px;">Aucune présence enregistrée</p>
                <?php else: ?>
                    <?php foreach ($presences as $p): ?>
                    <div class="info-row">
                        <span class="key">🌿 <?= htmlspecialchars($p['branche']) ?></span>
                        <span class="val"><?= $p['nb_presents'] ?> présent(s)</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="margin-top:12px;">
                    <a href="presences.php?id=<?= $id ?>" class="btn btn-secondary" style="font-size:12px; padding:6px 14px;">
                        + Gérer les présences
                    </a>
                </div>
            </div>
        </div>

        <!-- RÉCOMPENSES -->
        <div class="detail-card">
            <div class="detail-card-header">
                <span>Récompenses — <?= count($recompenses) ?></span>
            </div>
            <div class="detail-card-body">
                <?php if (empty($recompenses)): ?>
                    <p style="color:var(--gris); font-size:13px;">Aucune récompense attribuée</p>
                <?php else: ?>
                    <?php foreach ($recompenses as $r): ?>
                    <div class="info-row">
                        <span class="key">
                            🏆 <?= htmlspecialchars($r['libelle']) ?>
                            <span style="font-size:10px; color:var(--gris);">(<?= $r['type'] ?>)</span>
                        </span>
                        <span class="val" style="font-size:12px; color:var(--gris);">
                            <?= htmlspecialchars($r['membre_nom'] ?? $r['branche_nom'] ?? '—') ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="margin-top:12px;">
                    <a href="recompenses.php?id=<?= $id ?>" class="btn btn-secondary" style="font-size:12px; padding:6px 14px;">
                        + Gérer les récompenses
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
