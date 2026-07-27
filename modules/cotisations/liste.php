<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user = $_SESSION['utilisateur'];
$db   = getDB();

$msg    = '';
$erreur = '';

// Ajout cotisation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';

    if ($action === 'supprimer') {
        $id_cot = (int)($_POST['id_cotisation'] ?? 0);
        if ($id_cot) {
            $db->prepare("DELETE FROM cotisation WHERE id_cotisation=?")->execute([$id_cot]);
            $msg = 'supprime';
        }
    } else {
        $id_membre      = (int)($_POST['id_membre'] ?? 0);
        $montant        = (float)($_POST['montant'] ?? 0);
        $type           = $_POST['type'] ?? '';
        $date_versement = $_POST['date_versement'] ?? date('Y-m-d');
        $periode        = trim($_POST['periode'] ?? '') ?: null;

        if (!$id_membre || $montant <= 0 || $type === '') {
            $erreur = 'Membre, montant et type sont obligatoires.';
        } else {
            $db->prepare("
                INSERT INTO cotisation (montant, date_versement, type, periode, id_membre)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$montant, $date_versement, $type, $periode, $id_membre]);
            $msg = 'ajoute';
        }
    }
}

// Filtres
$filtre_membre = (int)($_GET['membre'] ?? 0);
$filtre_type   = $_GET['type'] ?? '';
$filtre_mois   = $_GET['mois'] ?? '';

$where  = [];
$params = [];

if ($filtre_membre) {
    $where[]  = "c.id_membre = ?";
    $params[] = $filtre_membre;
}
if ($filtre_type !== '') {
    $where[]  = "c.type = ?";
    $params[] = $filtre_type;
}
if ($filtre_mois !== '') {
    $where[]  = "c.periode = ?";
    $params[] = $filtre_mois;
}

$sql = "
    SELECT c.id_cotisation, c.montant, c.date_versement, c.type, c.periode,
           m.nom || ' ' || m.prenom AS membre_nom, m.type_membre, m.id_membre
    FROM cotisation c
    JOIN mpikambana m ON m.id_membre = c.id_membre
" . ($where ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY c.date_versement DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$cotisations = $stmt->fetchAll();

$total = array_sum(array_column($cotisations, 'montant'));

// Stats globales
$stats = $db->query("
    SELECT
        COUNT(*) AS nb_versements,
        COALESCE(SUM(montant), 0) AS total,
        COALESCE(SUM(montant) FILTER (WHERE type = 'régulière'), 0) AS total_reguliere,
        COALESCE(SUM(montant) FILTER (WHERE type = 'non régulière'), 0) AS total_non_reguliere
    FROM cotisation
")->fetch();

$membres = $db->query("SELECT id_membre, nom, prenom, type_membre FROM mpikambana ORDER BY nom")->fetchAll();

// Périodes disponibles pour filtre
$periodes = $db->query("SELECT DISTINCT periode FROM cotisation WHERE periode IS NOT NULL ORDER BY periode DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotisations — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--blanc);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 16px;
            text-align: center;
        }
        .stat-card .val { font-family:'Cinzel',serif; font-size:24px; color:var(--vert2); font-weight:600; }
        .stat-card .lbl { font-size:11px; color:var(--gris); text-transform:uppercase; letter-spacing:1px; margin-top:4px; }
        .stat-card.gold .val { color:var(--or); }

        .filtres { background:var(--blanc); border:1px solid var(--border); border-radius:6px; padding:16px 20px; margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
        .filtres .champ { margin:0; flex:1; min-width:150px; }
        .filtres select { padding:8px 12px; font-size:13px; }
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
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Cotisations</h2>
        </div>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Cotisation enregistrée avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Cotisation supprimée.</div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card gold">
            <div class="val"><?= number_format($stats['total'], 0, ',', ' ') ?></div>
            <div class="lbl">Total général (Ar)</div>
        </div>
        <div class="stat-card">
            <div class="val"><?= $stats['nb_versements'] ?></div>
            <div class="lbl">Versements</div>
        </div>
        <div class="stat-card">
            <div class="val"><?= number_format($stats['total_reguliere'], 0, ',', ' ') ?></div>
            <div class="lbl">Régulières (Ar)</div>
        </div>
        <div class="stat-card">
            <div class="val"><?= number_format($stats['total_non_reguliere'], 0, ',', ' ') ?></div>
            <div class="lbl">Non régulières (Ar)</div>
        </div>
    </div>

    <!-- FILTRES -->
    <form method="GET">
        <div class="filtres">
            <div class="champ">
                <label>Membre</label>
                <select name="membre">
                    <option value="0">Tous les membres</option>
                    <?php foreach ($membres as $m): ?>
                    <option value="<?= $m['id_membre'] ?>" <?= $filtre_membre == $m['id_membre'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ">
                <label>Type</label>
                <select name="type">
                    <option value="">Tous</option>
                    <option value="régulière"     <?= $filtre_type === 'régulière'     ? 'selected' : '' ?>>Régulière</option>
                    <option value="non régulière" <?= $filtre_type === 'non régulière' ? 'selected' : '' ?>>Non régulière</option>
                </select>
            </div>
            <div class="champ">
                <label>Période</label>
                <select name="mois">
                    <option value="">Toutes</option>
                    <?php foreach ($periodes as $p): ?>
                    <option value="<?= $p ?>" <?= $filtre_mois === $p ? 'selected' : '' ?>><?= $p ?></option>
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
    <div class="table-wrap" style="margin-bottom:32px;">
        <table>
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Type membre</th>
                    <th>Montant</th>
                    <th>Type cotisation</th>
                    <th>Période</th>
                    <th>Date versement</th>
                    <?php if ($user['type_membre'] === 'kp'): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cotisations)): ?>
                <tr><td colspan="<?= $user['type_membre'] === 'kp' ? 7 : 6 ?>" style="text-align:center; color:var(--gris); padding:32px;">Aucune cotisation trouvée</td></tr>
                <?php else: ?>
                <?php foreach ($cotisations as $c):
                    $badge = match($c['type_membre']) {
                        'beazina'       => 'badge-beazina',
                        'mpanabe'       => 'badge-mpanabe',
                        'kp'            => 'badge-kp',
                        'mpanohana'     => 'badge-mpanohana',
                        'ray aman-dreny'=> 'badge-ray',
                        default         => ''
                    };
                ?>
                <tr>
                    <td>
                        <a href="../membres/detail.php?id=<?= $c['id_membre'] ?>"
                           style="font-weight:700; color:var(--vert2); text-decoration:none;">
                            <?= htmlspecialchars($c['membre_nom']) ?>
                        </a>
                    </td>
                    <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($c['type_membre']) ?></span></td>
                    <td style="font-weight:700; color:var(--vert2);"><?= number_format($c['montant'], 0, ',', ' ') ?> Ar</td>
                    <td>
                        <span class="badge" style="background:<?= $c['type']==='régulière' ? '#e6f4ec' : '#FAEEDA' ?>; color:<?= $c['type']==='régulière' ? '#085041' : '#633806' ?>;">
                            <?= htmlspecialchars($c['type']) ?>
                        </span>
                    </td>
                    <td style="font-size:12px; color:var(--gris);"><?= htmlspecialchars($c['periode'] ?? '—') ?></td>
                    <td style="font-size:12px;"><?= date('d/m/Y', strtotime($c['date_versement'])) ?></td>
                    <?php if ($user['type_membre'] === 'kp'): ?>
                    <td>
                        <form method="POST" onsubmit="return confirm('Supprimer cette cotisation ?')">
                            <input type="hidden" name="action" value="supprimer">
                            <input type="hidden" name="id_cotisation" value="<?= $c['id_cotisation'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:11px;">Supprimer</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($cotisations)): ?>
            <tfoot>
                <tr style="background:#f5f0e8;">
                    <td colspan="2" style="padding:12px 16px; font-weight:700; font-family:'Cinzel',serif; font-size:12px;">TOTAL</td>
                    <td style="padding:12px 16px; font-weight:700; color:var(--vert2);"><?= number_format($total, 0, ',', ' ') ?> Ar</td>
                    <td colspan="<?= $user['type_membre'] === 'kp' ? 4 : 3 ?>"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- AJOUTER COTISATION -->
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Enregistrer un versement
        </div>
        <div style="padding:24px;">
            <form method="POST">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
                    <div class="champ">
                        <label for="id_membre">Membre *</label>
                        <select id="id_membre" name="id_membre" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($membres as $m): ?>
                            <option value="<?= $m['id_membre'] ?>"><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label for="montant">Montant (Ar) *</label>
                        <input type="number" id="montant" name="montant" min="0" step="100" placeholder="Ex: 5000" required>
                    </div>
                    <div class="champ">
                        <label for="type">Type *</label>
                        <select id="type" name="type" required onchange="togglePeriode(this.value)">
                            <option value="">-- Choisir --</option>
                            <option value="régulière">Régulière (mensuelle/trimestrielle)</option>
                            <option value="non régulière">Non régulière (ponctuelle)</option>
                        </select>
                    </div>
                    <div class="champ" id="champ-periode">
                        <label for="periode">Période <span style="color:var(--gris); font-weight:400;">(ex: 2026-07)</span></label>
                        <input type="month" id="periode" name="periode">
                    </div>
                    <div class="champ">
                        <label for="date_versement">Date de versement</label>
                        <input type="date" id="date_versement" name="date_versement" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>

</div>

<script>
function togglePeriode(val) {
    document.getElementById('champ-periode').style.display = val === 'régulière' ? 'block' : 'none';
}
// Init
togglePeriode('');
</script>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
