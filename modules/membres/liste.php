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
$filtre_branche      = (int)($_GET['branche'] ?? 0);
$filtre_type         = $_GET['type'] ?? '';
$filtre_recherche    = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($filtre_branche) {
    $where[]  = "m.id_branche = ?";
    $params[] = $filtre_branche;
}
if ($filtre_type !== '') {
    $where[]  = "m.type_membre = ?";
    $params[] = $filtre_type;
}
if ($filtre_recherche !== '') {
    $where[]  = "(m.nom ILIKE ? OR m.prenom ILIKE ? OR m.code_membre ILIKE ?)";
    $params[] = "%$filtre_recherche%";
    $params[] = "%$filtre_recherche%";
    $params[] = "%$filtre_recherche%";
}

$sql = "
    SELECT m.id_membre, m.nom, m.prenom, m.type_membre, m.code_membre,
           m.telephone, m.email, b.nom AS branche, v.nom AS vondrona
    FROM mpikambana m
    LEFT JOIN branche b ON b.id_branche = m.id_branche
    LEFT JOIN vondrona v ON v.id_vondrona = b.id_vondrona
" . ($where ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY m.type_membre, m.nom, m.prenom
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$membres = $stmt->fetchAll();

// Pour les filtres
$branches = $db->query("SELECT id_branche, nom FROM branche ORDER BY nom")->fetchAll();
$types    = ['beazina', 'mpanabe', 'kp', 'mpanohana', 'ray aman-dreny'];

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membres — Association Scoute</title>
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
        .filtres label { margin-bottom: 4px; }
        .filtres input, .filtres select { padding: 8px 12px; font-size: 13px; }
    </style>
</head>
<body class="with-nav bg-membres">

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
                Membres <span style="font-size:14px; color:var(--gris); font-family:'Lato',sans-serif;">(<?= count($membres) ?>)</span>
            </h2>
        </div>
        <?php if ($user['type_membre'] === 'kp'): ?>
        <a href="ajouter.php" class="btn btn-primary">+ Ajouter</a>
        <?php endif; ?>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Membre ajouté avec succès.</div>
    <?php elseif ($msg === 'modifie'): ?>
        <div class="alerte alerte-succes">Membre modifié avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Membre supprimé avec succès.</div>
    <?php endif; ?>

    <!-- FILTRES -->
    <form method="GET" action="liste.php">
        <div class="filtres">
            <div class="champ">
                <label>Recherche</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filtre_recherche) ?>"
                       placeholder="Nom, prénom, code...">
            </div>
            <div class="champ">
                <label>Type</label>
                <select name="type">
                    <option value="">Tous les types</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= $filtre_type === $t ? 'selected' : '' ?>>
                        <?= ucfirst($t) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ">
                <label>Branche</label>
                <select name="branche">
                    <option value="0">Toutes les branches</option>
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
                    <th>Code</th>
                    <th>Nom & Prénom</th>
                    <th>Type</th>
                    <th>Branche / Vondrona</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($membres)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--gris); padding:32px;">Aucun membre trouvé</td></tr>
                <?php else: ?>
                <?php foreach ($membres as $m): ?>
                <tr>
                    <td style="font-size:11px; color:var(--gris);"><?= htmlspecialchars($m['code_membre'] ?? '—') ?></td>
                    <td>
                        <a href="detail.php?id=<?= $m['id_membre'] ?>"
                           style="font-weight:700; color:var(--vert2); text-decoration:none;">
                            <?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        $badge = match($m['type_membre']) {
                            'beazina'       => 'badge-beazina',
                            'mpanabe'       => 'badge-mpanabe',
                            'kp'            => 'badge-kp',
                            'mpanohana'     => 'badge-mpanohana',
                            'ray aman-dreny'=> 'badge-ray',
                            default         => ''
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= htmlspecialchars($m['type_membre'] ?? '—') ?></span>
                    </td>
                    <td>
                        <?php if ($m['branche']): ?>
                            <strong><?= htmlspecialchars($m['branche']) ?></strong>
                            <span style="color:var(--gris); font-size:12px;"> / <?= htmlspecialchars($m['vondrona'] ?? '') ?></span>
                        <?php else: ?>
                            <span style="color:var(--gris); font-size:12px;">Fivondronana direct</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;">
                        <?= htmlspecialchars($m['telephone'] ?? '—') ?>
                    </td>
                    <td style="display:flex; gap:6px;">
                        <a href="detail.php?id=<?= $m['id_membre'] ?>" class="btn btn-secondary" style="padding:5px 10px; font-size:11px;">Voir</a>
                        <?php if ($user['type_membre'] === 'kp'): ?>
                        <a href="modifier.php?id=<?= $m['id_membre'] ?>" class="btn btn-secondary" style="padding:5px 10px; font-size:11px;">Modifier</a>
                        <a href="supprimer.php?id=<?= $m['id_membre'] ?>"
                           class="btn btn-danger" style="padding:5px 10px; font-size:11px;"
                           onclick="return confirm('Supprimer ce membre définitivement ?')">Supprimer</a>
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
