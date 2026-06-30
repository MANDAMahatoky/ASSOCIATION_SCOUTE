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

// Informations du membre
$membre = $db->prepare("
    SELECT m.*, b.nom AS branche_nom, v.nom AS vondrona_nom, f.nom AS fivondronana_nom
    FROM mpikambana m
    LEFT JOIN branche b ON b.id_branche = m.id_branche
    LEFT JOIN vondrona v ON v.id_vondrona = b.id_vondrona
    LEFT JOIN fivondronana f ON f.id_fivondronana = m.id_fivondronana
    WHERE m.id_membre = ?
");
$membre->execute([$id]);
$m = $membre->fetch();

if (!$m) { header('Location: liste.php'); exit; }

// Grades (ambaratonga)
$grades = $db->prepare("
    SELECT a.libelle, a.date_obtention, a.actif,
           mp.nom || ' ' || mp.prenom AS validateur
    FROM ambaratonga a
    LEFT JOIN mpikambana mp ON mp.id_membre = a.id_validateur
    WHERE a.id_membre = ?
    ORDER BY a.date_obtention DESC
");
$grades->execute([$id]);
$grades = $grades->fetchAll();

// Talents (talenta)
$talents = $db->prepare("
    SELECT t.libelle, t.date_validation,
           mp.nom || ' ' || mp.prenom AS validateur
    FROM talenta t
    LEFT JOIN mpikambana mp ON mp.id_membre = t.id_validateur
    WHERE t.id_membre = ?
    ORDER BY t.date_validation DESC
");
$talents->execute([$id]);
$talents = $talents->fetchAll();

// Cotisations
$cotisations = $db->prepare("
    SELECT montant, date_versement, type, periode
    FROM cotisation
    WHERE id_membre = ?
    ORDER BY date_versement DESC
    LIMIT 10
");
$cotisations->execute([$id]);
$cotisations = $cotisations->fetchAll();

$total_cotisations = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM cotisation WHERE id_membre = ?");
$total_cotisations->execute([$id]);
$total = $total_cotisations->fetchColumn();

// Badge type
$badge = match($m['type_membre']) {
    'beazina'       => 'badge-beazina',
    'mpanabe'       => 'badge-mpanabe',
    'kp'            => 'badge-kp',
    'mpanohana'     => 'badge-mpanohana',
    'ray aman-dreny'=> 'badge-ray',
    default         => ''
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?> — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        @media (max-width: 700px) { .detail-grid { grid-template-columns: 1fr; } }

        .detail-card {
            background: var(--blanc);
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
        }
        .detail-card-header {
            background: var(--vert2);
            color: #fff;
            padding: 10px 16px;
            font-family: 'Cinzel', serif;
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .detail-card-body { padding: 16px; }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .key { color: var(--gris); }
        .info-row .val { font-weight: 600; text-align: right; }

        .profil-header {
            background: var(--blanc);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .profil-avatar {
            width: 64px; height: 64px;
            background: rgba(45,106,79,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        .mini-badge {
            display: inline-block;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            background: #f0f0f0;
            color: var(--gris);
            margin-right: 4px;
        }
        .mini-badge.actif { background: #e6f4ec; color: var(--vert2); }
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

    <!-- NAVIGATION -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <a href="liste.php" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour à la liste</a>
        <?php if ($user['type_membre'] === 'kp'): ?>
        <div style="display:flex; gap:8px;">
            <a href="modifier.php?id=<?= $m['id_membre'] ?>" class="btn btn-secondary">Modifier</a>
            <a href="supprimer.php?id=<?= $m['id_membre'] ?>" class="btn btn-danger"
               onclick="return confirm('Supprimer ce membre définitivement ?')">Supprimer</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- PROFIL -->
    <div class="profil-header">
        <div class="profil-avatar">👤</div>
        <div>
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-bottom:6px;">
                <?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?>
            </h2>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="badge <?= $badge ?>"><?= htmlspecialchars($m['type_membre'] ?? '—') ?></span>
                <?php if ($m['code_membre']): ?>
                <span style="font-size:12px; color:var(--gris);">Code : <strong><?= htmlspecialchars($m['code_membre']) ?></strong></span>
                <?php endif; ?>
                <?php if ($m['branche_nom']): ?>
                <span style="font-size:12px; color:var(--gris);">🌿 <?= htmlspecialchars($m['branche_nom']) ?> / <?= htmlspecialchars($m['vondrona_nom'] ?? '') ?></span>
                <?php else: ?>
                <span style="font-size:12px; color:var(--gris);">🏕️ Fivondronana direct</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="detail-grid">

        <!-- INFORMATIONS PERSONNELLES -->
        <div class="detail-card">
            <div class="detail-card-header">Informations personnelles</div>
            <div class="detail-card-body">
                <div class="info-row">
                    <span class="key">Date de naissance</span>
                    <span class="val"><?= $m['date_naissance'] ? date('d/m/Y', strtotime($m['date_naissance'])) : '—' ?></span>
                </div>
                <div class="info-row">
                    <span class="key">Adresse</span>
                    <span class="val"><?= htmlspecialchars($m['adresse'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="key">Téléphone</span>
                    <span class="val"><?= htmlspecialchars($m['telephone'] ?? '—') ?></span>
                </div>
                <div class="info-row">
                    <span class="key">Email</span>
                    <span class="val"><?= htmlspecialchars($m['email'] ?? '—') ?></span>
                </div>
                <?php if ($m['fiantso']): ?>
                <div class="info-row">
                    <span class="key">Fiantso (surnom)</span>
                    <span class="val"><?= htmlspecialchars($m['fiantso']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- COTISATIONS -->
        <div class="detail-card">
            <div class="detail-card-header">Cotisations — Total : <?= number_format($total, 0, ',', ' ') ?> Ar</div>
            <div class="detail-card-body">
                <?php if (empty($cotisations)): ?>
                    <p style="color:var(--gris); font-size:13px;">Aucune cotisation enregistrée</p>
                <?php else: ?>
                    <?php foreach ($cotisations as $c): ?>
                    <div class="info-row">
                        <span class="key"><?= date('d/m/Y', strtotime($c['date_versement'])) ?>
                            <?php if ($c['periode']): ?>
                                <span style="font-size:10px;">(<?= htmlspecialchars($c['periode']) ?>)</span>
                            <?php endif; ?>
                        </span>
                        <span class="val" style="color:var(--vert2);"><?= number_format($c['montant'], 0, ',', ' ') ?> Ar</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (in_array($m['type_membre'], ['beazina', 'mpanabe'])): ?>

        <!-- GRADES -->
        <div class="detail-card">
            <div class="detail-card-header">Grades — Ambaratonga</div>
            <div class="detail-card-body">
                <?php if (empty($grades)): ?>
                    <p style="color:var(--gris); font-size:13px;">Aucun grade enregistré</p>
                <?php else: ?>
                    <?php foreach ($grades as $g): ?>
                    <div class="info-row">
                        <span class="key">
                            <?= htmlspecialchars($g['libelle']) ?>
                            <?php if ($g['actif']): ?>
                                <span class="mini-badge actif">Actif</span>
                            <?php endif; ?>
                        </span>
                        <span class="val" style="font-size:11px; color:var(--gris);">
                            <?= date('d/m/Y', strtotime($g['date_obtention'])) ?>
                            <br>par <?= htmlspecialchars($g['validateur'] ?? '—') ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TALENTS -->
        <div class="detail-card">
            <div class="detail-card-header">Talents — Talenta</div>
            <div class="detail-card-body">
                <?php if (empty($talents)): ?>
                    <p style="color:var(--gris); font-size:13px;">Aucun talent enregistré</p>
                <?php else: ?>
                    <?php foreach ($talents as $t): ?>
                    <div class="info-row">
                        <span class="key"><?= htmlspecialchars($t['libelle']) ?></span>
                        <span class="val" style="font-size:11px; color:var(--gris);">
                            <?= date('d/m/Y', strtotime($t['date_validation'])) ?>
                            <br>par <?= htmlspecialchars($t['validateur'] ?? '—') ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

    </div>

</div>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
