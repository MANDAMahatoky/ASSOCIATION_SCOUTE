<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user = $_SESSION['utilisateur'];
$db   = getDB();

// Filtres
$filtre_branche = (int)($_GET['branche'] ?? 0);
$filtre_statut  = $_GET['statut'] ?? ''; // passee, en_cours, a_venir

$where  = [];
$params = [];

if ($filtre_branche) {
    $where[]  = "h.id_branche = ?";
    $params[] = $filtre_branche;
}
if ($filtre_statut === 'passee') {
    $where[] = "h.date_fin < CURRENT_DATE";
} elseif ($filtre_statut === 'en_cours') {
    $where[] = "h.date_debut <= CURRENT_DATE AND (h.date_fin IS NULL OR h.date_fin >= CURRENT_DATE)";
} elseif ($filtre_statut === 'a_venir') {
    $where[] = "h.date_debut > CURRENT_DATE";
}

$sql = "
    SELECT h.id_hetsika, h.titre, h.date_debut, h.date_fin, h.lieu, h.description,
           b.nom AS branche,
           COUNT(DISTINCT p.id_branche) AS nb_branches_presentes,
           COUNT(DISTINCT r.id_recompense) AS nb_recompenses
    FROM hetsika h
    LEFT JOIN branche b ON b.id_branche = h.id_branche
    LEFT JOIN presence p ON p.id_hetsika = h.id_hetsika
    LEFT JOIN recompense r ON r.id_hetsika = h.id_hetsika
" . ($where ? "WHERE " . implode(" AND ", $where) : "") . "
    GROUP BY h.id_hetsika, h.titre, h.date_debut, h.date_fin, h.lieu, h.description, b.nom
    ORDER BY h.date_debut DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$hetsika = $stmt->fetchAll();

$branches = $db->query("SELECT id_branche, nom FROM branche ORDER BY nom")->fetchAll();
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hetsika — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .filtres {
            background: var(--blanc);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filtres .champ { margin: 0; flex: 1; min-width: 160px; }
        .filtres select, .filtres input { padding: 8px 12px; font-size: 13px; }

        .statut-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .statut-passee   { background: #f1efе8; color: var(--gris); }
        .statut-en_cours { background: #e6f4ec; color: var(--vert2); }
        .statut-a_venir  { background: #EEEDFE; color: #3C3489; }
    </style>
</head>
<body class="with-nav bg-recompense">

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
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">
                Hetsika <span style="font-size:14px; color:var(--gris); font-family:'Lato',sans-serif;">(<?= count($hetsika) ?>)</span>
            </h2>
        </div>
        <a href="ajouter.php" class="btn btn-primary">+ Ajouter</a>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Activité ajoutée avec succès.</div>
    <?php elseif ($msg === 'modifie'): ?>
        <div class="alerte alerte-succes">Activité modifiée avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Activité supprimée avec succès.</div>
    <?php endif; ?>

    <!-- FILTRES -->
    <form method="GET">
        <div class="filtres">
            <div class="champ">
                <label>Statut</label>
                <select name="statut">
                    <option value="">Tous</option>
                    <option value="a_venir"  <?= $filtre_statut === 'a_venir'  ? 'selected' : '' ?>>À venir</option>
                    <option value="en_cours" <?= $filtre_statut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="passee"   <?= $filtre_statut === 'passee'   ? 'selected' : '' ?>>Passée</option>
                </select>
            </div>
            <div class="champ">
                <label>Branche organisatrice</label>
                <select name="branche">
                    <option value="0">Toutes</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id_branche'] ?>" <?= $filtre_branche == $b['id_branche'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['nom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="padding:8px 20px;">Filtrer</button>
                <a href="liste.php" class="btn btn-secondary" style="padding:8px 16px; margin-left:8px;">Réinitialiser</a>
            </div>
        </div>
    </form>

    <!-- TABLEAU -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Lieu</th>
                    <th>Branche</th>
                    <th>Présences</th>
                    <th>Récompenses</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($hetsika)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--gris); padding:32px;">Aucune activité trouvée</td></tr>
                <?php else: ?>
                <?php foreach ($hetsika as $h):
                    // Calculer le statut
                    $today = date('Y-m-d');
                    if ($h['date_fin'] && $h['date_fin'] < $today) {
                        $statut = 'passee';
                        $statut_label = 'Passée';
                    } elseif ($h['date_debut'] > $today) {
                        $statut = 'a_venir';
                        $statut_label = 'À venir';
                    } else {
                        $statut = 'en_cours';
                        $statut_label = 'En cours';
                    }
                ?>
                <tr>
                    <td>
                        <a href="detail.php?id=<?= $h['id_hetsika'] ?>"
                           style="font-weight:700; color:var(--vert2); text-decoration:none;">
                            <?= htmlspecialchars($h['titre']) ?>
                        </a>
                    </td>
                    <td><span class="statut-badge statut-<?= $statut ?>"><?= $statut_label ?></span></td>
                    <td style="font-size:12px;">
                        <?= date('d/m/Y', strtotime($h['date_debut'])) ?>
                        <?php if ($h['date_fin'] && $h['date_fin'] !== $h['date_debut']): ?>
                            <br><span style="color:var(--gris);">→ <?= date('d/m/Y', strtotime($h['date_fin'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($h['lieu'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($h['branche'] ?? '—') ?></td>
                    <td style="text-align:center;">
                        <a href="presences.php?id=<?= $h['id_hetsika'] ?>"
                           style="color:var(--vert); font-weight:700;">
                            <?= $h['nb_branches_presentes'] ?> branche(s)
                        </a>
                    </td>
                    <td style="text-align:center;">
                        <a href="recompenses.php?id=<?= $h['id_hetsika'] ?>"
                           style="color:var(--or); font-weight:700;">
                            <?= $h['nb_recompenses'] ?> 🏆
                        </a>
                    </td>
                    <td style="display:flex; gap:6px;">
                        <a href="detail.php?id=<?= $h['id_hetsika'] ?>" class="btn btn-secondary" style="padding:5px 10px; font-size:11px;">Voir</a>
                        <a href="modifier.php?id=<?= $h['id_hetsika'] ?>" class="btn btn-secondary" style="padding:5px 10px; font-size:11px;">Modifier</a>
                        <a href="supprimer.php?id=<?= $h['id_hetsika'] ?>"
                           class="btn btn-danger" style="padding:5px 10px; font-size:11px;"
                           onclick="return confirm('Supprimer cette activité ?')">Supprimer</a>
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
