<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();
/*echo "<pre>";
var_dump($_SESSION);
echo "</pre>";
die();*/
// Protection : rediriger vers login si non connecté
if (!isset($_SESSION['utilisateur'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['utilisateur'];
$db   = getDB();

// ── STATISTIQUES EN TEMPS RÉEL ───────────────────────────────
$stats = [];

// Nombre total de membres
$stats['membres'] = $db->query("SELECT COUNT(*) FROM mpikambana")->fetchColumn();

// Nombre de beazina
$stats['beazina'] = $db->query("SELECT COUNT(*) FROM mpikambana WHERE type_membre = 'beazina'")->fetchColumn();

// Nombre de branches
$stats['branches'] = $db->query("SELECT COUNT(*) FROM branche")->fetchColumn();

// Nombre d'activités ce mois-ci
$stats['hetsika'] = $db->query("
    SELECT COUNT(*) FROM hetsika
    WHERE DATE_TRUNC('month', date_debut) = DATE_TRUNC('month', CURRENT_DATE)
")->fetchColumn();

// Emprunts en cours (non retournés)
$stats['emprunts'] = $db->query("
    SELECT COUNT(*) FROM emprunt
    WHERE date_retour_effective IS NULL
")->fetchColumn();

// Total cotisations ce mois-ci
$stats['cotisations'] = $db->query("
    SELECT COALESCE(SUM(montant), 0) FROM cotisation
    WHERE DATE_TRUNC('month', date_versement) = DATE_TRUNC('month', CURRENT_DATE)
")->fetchColumn();

// Matériel à réparer
$stats['a_reparer'] = $db->query("
    SELECT COUNT(*) FROM materiel WHERE etat = 'à réparer'
")->fetchColumn();

// Dernières activités (3 prochaines)
$prochaines = $db->query("
    SELECT h.titre, h.date_debut, h.lieu, b.nom AS branche
    FROM hetsika h
    LEFT JOIN branche b ON b.id_branche = h.id_branche
    WHERE h.date_debut >= CURRENT_DATE
    ORDER BY h.date_debut ASC
    LIMIT 3
")->fetchAll();

// Membres récemment ajoutés (3 derniers)
$nouveaux = $db->query("
    SELECT nom, prenom, type_membre, branche
    FROM mpikambana
    ORDER BY id_membre DESC
    LIMIT 3
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — Association Scoute</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── STATISTIQUES ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 8px;
        }

        .stat-card {
            background: var(--blanc);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 20px 16px;
            text-align: center;
        }

        .stat-card .stat-value {
            font-family: 'Cinzel', serif;
            font-size: 32px;
            font-weight: 600;
            color: var(--vert2);
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: 11px;
            color: var(--gris);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 6px;
        }

        .stat-card.warning .stat-value { color: var(--rouge); }
        .stat-card.gold    .stat-value { color: var(--or); }

        /* ── PANNEAUX RAPIDES ── */
        .panels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 8px;
        }

        @media (max-width: 700px) { .panels { grid-template-columns: 1fr; } }

        .panel {
            background: var(--blanc);
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
        }

        .panel-header {
            background: var(--vert2);
            color: #fff;
            padding: 10px 16px;
            font-family: 'Cinzel', serif;
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .panel-body { padding: 0; }

        .panel-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }

        .panel-row:last-child { border-bottom: none; }

        .panel-row .label { color: var(--texte); font-weight: 600; }
        .panel-row .meta  { color: var(--gris); font-size: 11px; }
        .panel-empty {
            padding: 20px 16px;
            color: var(--gris);
            font-size: 13px;
            text-align: center;
        }
    </style>
</head>
<body class="with-nav">

<!-- NAVBAR -->
<nav>
    <div class="nav-brand">
        <span>⚜</span> Association Scoute
    </div>
    <div class="nav-user">
        <span>Bonjour, <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong></span>
        <span class="badge-type"><?= htmlspecialchars($user['type_membre'] ?? 'membre') ?></span>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<!-- CONTENU -->
<div class="container">

    <div class="welcome">
        <h2>Tableau de bord</h2>
        <p>Bienvenue dans l'espace de gestion du Fivondronana — <?= date('d/m/Y') ?></p>
    </div>

    <!-- STATISTIQUES -->
    <div class="section-title">Chiffres clés</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['membres'] ?></div>
            <div class="stat-label">Membres</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['beazina'] ?></div>
            <div class="stat-label">Beazina</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['branches'] ?></div>
            <div class="stat-label">Branches</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['hetsika'] ?></div>
            <div class="stat-label">Activités ce mois</div>
        </div>
        <div class="stat-card <?= $stats['emprunts'] > 0 ? 'warning' : '' ?>">
            <div class="stat-value"><?= $stats['emprunts'] ?></div>
            <div class="stat-label">Emprunts en cours</div>
        </div>
        <div class="stat-card gold">
            <div class="stat-value"><?= number_format($stats['cotisations'], 0, ',', ' ') ?></div>
            <div class="stat-label">Ar cotisations (mois)</div>
        </div>
        <?php if ($stats['a_reparer'] > 0): ?>
        <div class="stat-card warning">
            <div class="stat-value"><?= $stats['a_reparer'] ?></div>
            <div class="stat-label">Matériel à réparer</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- PANNEAUX RAPIDES -->
    <div class="section-title">Aperçu rapide</div>
    <div class="panels">

        <!-- Prochaines activités -->
        <div class="panel">
            <div class="panel-header">Prochaines activités</div>
            <div class="panel-body">
                <?php if (empty($prochaines)): ?>
                    <div class="panel-empty">Aucune activité à venir</div>
                <?php else: ?>
                    <?php foreach ($prochaines as $h): ?>
                    <div class="panel-row">
                        <div>
                            <div class="label"><?= htmlspecialchars($h['titre']) ?></div>
                            <div class="meta"><?= htmlspecialchars($h['lieu'] ?? '—') ?> · <?= htmlspecialchars($h['branche'] ?? '—') ?></div>
                        </div>
                        <div class="meta"><?= date('d/m', strtotime($h['date_debut'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nouveaux membres -->
        <div class="panel">
            <div class="panel-header">Derniers membres ajoutés</div>
            <div class="panel-body">
                <?php if (empty($nouveaux)): ?>
                    <div class="panel-empty">Aucun membre enregistré</div>
                <?php else: ?>
                    <?php foreach ($nouveaux as $m): ?>
                    <div class="panel-row">
                        <div>
                            <div class="label"><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></div>
                            <div class="meta"><?= htmlspecialchars($m['branche'] ?? 'Fivondronana') ?></div>
                        </div>
                        <span class="badge badge-<?= htmlspecialchars($m['type_membre'] === 'ray aman-dreny' ? 'ray' : $m['type_membre']) ?>">
                            <?= htmlspecialchars($m['type_membre']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- MODULES -->
    <div class="section-title">Organisation</div>
    <div class="grid">
        <a href="modules/vondrona/liste.php" class="module-card">
            <div class="module-icon">🏕️</div>
            <h3>Vondrona</h3>
            <p>Gérer les groupements Tily et Mpanazava</p>
        </a>
        <a href="modules/branches/liste.php" class="module-card">
            <div class="module-icon">🌿</div>
            <h3>Branches</h3>
            <p>Gérer les 7 branches de l'association</p>
        </a>
    </div>

    <div class="section-title">Membres</div>
    <div class="grid">
        <a href="modules/membres/liste.php" class="module-card">
            <div class="module-icon">👥</div>
            <h3>Membres</h3>
            <p>Liste et gestion de tous les membres</p>
        </a>
        <a href="modules/membres/parrainage.php" class="module-card">
            <div class="module-icon">🤝</div>
            <h3>Parrainage</h3>
            <p>Liens mpanohana → beazina</p>
        </a>
    </div>

    <div class="section-title">Activités</div>
    <div class="grid">
        <a href="modules/hetsika/liste.php" class="module-card">
            <div class="module-icon">📅</div>
            <h3>Hetsika</h3>
            <p>Activités et événements</p>
        </a>
        <a href="modules/hetsika/presences.php" class="module-card">
            <div class="module-icon">✅</div>
            <h3>Présences</h3>
            <p>Enregistrer les branches présentes</p>
        </a>
        <a href="modules/hetsika/recompenses.php" class="module-card">
            <div class="module-icon">🏆</div>
            <h3>Récompenses</h3>
            <p>Coupes et prix remportés</p>
        </a>
    </div>

    <div class="section-title">Progression</div>
    <div class="grid">
        <a href="modules/progression/ambaratonga.php" class="module-card">
            <div class="module-icon">⭐</div>
            <h3>Grades</h3>
            <p>Ambaratonga — niveaux des beazina</p>
        </a>
        <a href="modules/progression/talenta.php" class="module-card">
            <div class="module-icon">🎯</div>
            <h3>Talents</h3>
            <p>Talenta — compétences des beazina</p>
        </a>
    </div>

    <div class="section-title">Matériel & Finances</div>
    <div class="grid">
        <a href="modules/materiel/liste.php" class="module-card">
            <div class="module-icon">🎒</div>
            <h3>Matériel</h3>
            <p>Inventaire et suivi des équipements</p>
        </a>
        <a href="modules/materiel/emprunts.php" class="module-card">
            <div class="module-icon">📦</div>
            <h3>Emprunts</h3>
            <p>Suivi des emprunts de matériel</p>
        </a>
        <a href="modules/cotisations/liste.php" class="module-card">
            <div class="module-icon">💰</div>
            <h3>Cotisations</h3>
            <p>Versements et suivi financier</p>
        </a>
    </div>

</div>

<footer>
    Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?>
</footer>

</body>
</html>
