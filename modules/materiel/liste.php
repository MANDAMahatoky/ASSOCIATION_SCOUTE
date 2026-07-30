<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user = $_SESSION['utilisateur'];
$db   = getDB();

$filtre_etat    = $_GET['etat'] ?? '';
$filtre_branche = (int)($_GET['branche'] ?? 0);

$where  = [];
$params = [];

if ($filtre_etat !== '') {
    $where[]  = "m.etat = ?";
    $params[] = $filtre_etat;
}
if ($filtre_branche === -1) {
    $where[] = "m.id_branche IS NULL";
} elseif ($filtre_branche > 0) {
    $where[]  = "m.id_branche = ?";
    $params[] = $filtre_branche;
}

$sql = "
    SELECT m.id_materiel, m.nom, m.description, m.etat, m.quantite,
           b.nom AS branche,
           COUNT(e.id_emprunt) FILTER (WHERE e.date_retour_effective IS NULL) AS nb_emprunts_en_cours
    FROM materiel m
    LEFT JOIN branche b ON b.id_branche = m.id_branche
    LEFT JOIN emprunt e ON e.id_materiel = m.id_materiel
" . ($where ? "WHERE " . implode(" AND ", $where) : "") . "
    GROUP BY m.id_materiel, m.nom, m.description, m.etat, m.quantite, b.nom
    ORDER BY m.nom
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$materiels = $stmt->fetchAll();

$branches = $db->query("SELECT id_branche, nom FROM branche ORDER BY nom")->fetchAll();
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matériel — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .filtres { background:var(--blanc); border:1px solid var(--border); border-radius:6px; padding:16px 20px; margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
        .filtres .champ { margin:0; flex:1; min-width:150px; }
        .filtres select { padding:8px 12px; font-size:13px; }
    </style>
</head>
<body class="with-nav bg-materiel">

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
                Matériel <span style="font-size:14px; color:var(--gris); font-family:'Lato',sans-serif;">(<?= count($materiels) ?>)</span>
            </h2>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="emprunts.php" class="btn btn-secondary">📦 Emprunts</a>
            <?php if ($user['type_membre'] === 'kp'): ?>
            <a href="ajouter.php" class="btn btn-primary">+ Ajouter</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Matériel ajouté avec succès.</div>
    <?php elseif ($msg === 'modifie'): ?>
        <div class="alerte alerte-succes">Matériel modifié avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Matériel supprimé avec succès.</div>
    <?php endif; ?>

    <!-- FILTRES -->
    <form method="GET">
        <div class="filtres">
            <div class="champ">
                <label>État</label>
                <select name="etat">
                    <option value="">Tous les états</option>
                    <option value="neuf"      <?= $filtre_etat === 'neuf'      ? 'selected' : '' ?>>Neuf</option>
                    <option value="usagé"     <?= $filtre_etat === 'usagé'     ? 'selected' : '' ?>>Usagé</option>
                    <option value="à réparer" <?= $filtre_etat === 'à réparer' ? 'selected' : '' ?>>À réparer</option>
                </select>
            </div>
            <div class="champ">
                <label>Propriétaire</label>
                <select name="branche">
                    <option value="0">Tous</option>
                    <option value="-1" <?= $filtre_branche === -1 ? 'selected' : '' ?>>Fivondronana (commun)</option>
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
                    <th>Nom</th>
                    <th>État</th>
                    <th>Quantité</th>
                    <th>Propriétaire</th>
                    <th>Emprunts en cours</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materiels)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--gris); padding:32px;">Aucun matériel trouvé</td></tr>
                <?php else: ?>
                <?php foreach ($materiels as $m):
                    $badge_etat = match($m['etat']) {
                        'neuf'       => 'badge-neuf',
                        'usagé'      => 'badge-usage',
                        'à réparer'  => 'badge-reparer',
                        default      => ''
                    };
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($m['nom']) ?></strong>
                        <?php if ($m['description']): ?>
                        <div style="font-size:11px; color:var(--gris);"><?= htmlspecialchars(mb_substr($m['description'], 0, 50)) ?>...</div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $badge_etat ?>"><?= htmlspecialchars($m['etat']) ?></span></td>
                    <td style="text-align:center; font-weight:700;"><?= $m['quantite'] ?></td>
                    <td>
                        <?php if ($m['branche']): ?>
                            🌿 <?= htmlspecialchars($m['branche']) ?>
                        <?php else: ?>
                            <span style="color:var(--gris); font-size:12px;">🏕️ Fivondronana</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($m['nb_emprunts_en_cours'] > 0): ?>
                            <span style="color:var(--rouge); font-weight:700;"><?= $m['nb_emprunts_en_cours'] ?> en cours</span>
                        <?php else: ?>
                            <span style="color:var(--gris);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex; gap:6px;">
                        <?php if ($user['type_membre'] === 'kp'): ?>
                        <a href="modifier.php?id=<?= $m['id_materiel'] ?>" class="btn btn-secondary" style="padding:5px 10px; font-size:11px;">Modifier</a>
                        <a href="supprimer.php?id=<?= $m['id_materiel'] ?>"
                           class="btn btn-danger" style="padding:5px 10px; font-size:11px;"
                           onclick="return confirm('Supprimer ce matériel ?')">Supprimer</a>
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
