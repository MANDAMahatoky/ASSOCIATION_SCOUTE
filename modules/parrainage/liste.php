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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'supprimer') {
        $id = (int)($_POST['id_parrainage'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM parrainage WHERE id_parrainage=?")->execute([$id]);
            $msg = 'supprime';
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id_parrainage'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE parrainage SET actif = NOT actif WHERE id_parrainage=?")->execute([$id]);
            $msg = 'modifie';
        }
    } else {
        $id_mpanohana  = (int)($_POST['id_mpanohana'] ?? 0);
        $id_beazina    = $_POST['id_beazina']  ? (int)$_POST['id_beazina']  : null;
        $id_hetsika    = $_POST['id_hetsika']  ? (int)$_POST['id_hetsika']  : null;
        $type_soutien  = $_POST['type_soutien'] ?? '';
        $description   = trim($_POST['description'] ?? '') ?: null;
        $montant       = $_POST['montant'] ? (float)$_POST['montant'] : null;
        $date_soutien  = $_POST['date_soutien'] ?? date('Y-m-d');

        if (!$id_mpanohana || $type_soutien === '') {
            $erreur = 'Le mpanohana et le type de soutien sont obligatoires.';
        } else {
            $db->prepare("
                INSERT INTO parrainage (id_mpanohana, id_beazina, id_hetsika, type_soutien, description, montant, date_soutien)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$id_mpanohana, $id_beazina, $id_hetsika, $type_soutien, $description, $montant, $date_soutien]);
            $msg = 'ajoute';
        }
    }
}

// Filtres
$filtre_type      = $_GET['type'] ?? '';
$filtre_mpanohana = (int)($_GET['mpanohana'] ?? 0);
$filtre_actif     = $_GET['actif'] ?? '';

$where  = [];
$params = [];

if ($filtre_type !== '') {
    $where[]  = "p.type_soutien = ?";
    $params[] = $filtre_type;
}
if ($filtre_mpanohana) {
    $where[]  = "p.id_mpanohana = ?";
    $params[] = $filtre_mpanohana;
}
if ($filtre_actif === '1') {
    $where[] = "p.actif = TRUE";
} elseif ($filtre_actif === '0') {
    $where[] = "p.actif = FALSE";
}

$sql = "
    SELECT p.*,
           mp.nom || ' ' || mp.prenom AS mpanohana_nom,
           b.nom  || ' ' || b.prenom  AS beazina_nom,
           h.titre AS hetsika_titre
    FROM parrainage p
    JOIN mpikambana mp ON mp.id_membre = p.id_mpanohana
    LEFT JOIN mpikambana b ON b.id_membre = p.id_beazina
    LEFT JOIN hetsika h ON h.id_hetsika = p.id_hetsika
" . ($where ? "WHERE " . implode(" AND ", $where) : "") . "
    ORDER BY p.date_soutien DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$parrainages = $stmt->fetchAll();

// Stats
$stats = $db->query("
    SELECT
        COUNT(*) AS nb_total,
        COUNT(*) FILTER (WHERE actif = TRUE)  AS nb_actifs,
        COUNT(*) FILTER (WHERE type_soutien = 'financier') AS nb_financier,
        COUNT(*) FILTER (WHERE type_soutien = 'matériel')  AS nb_materiel,
        COUNT(*) FILTER (WHERE type_soutien = 'sponsor')   AS nb_sponsor,
        COUNT(*) FILTER (WHERE type_soutien = 'moral')     AS nb_moral,
        COALESCE(SUM(montant) FILTER (WHERE type_soutien = 'financier'), 0) AS total_financier
    FROM parrainage
")->fetch();

// Listes pour formulaire
$mpanohana = $db->query("
    SELECT id_membre, nom, prenom FROM mpikambana
    WHERE type_membre = 'mpanohana'
    ORDER BY nom
")->fetchAll();

$beazina = $db->query("
    SELECT id_membre, nom, prenom FROM mpikambana
    WHERE type_membre IN ('beazina','mpanabe')
    ORDER BY nom
")->fetchAll();

$hetsika = $db->query("
    SELECT id_hetsika, titre, date_debut FROM hetsika
    ORDER BY date_debut DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parrainage FMT2S — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
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

        .badge-financier { background:#e6f4ec; color:#085041; }
        .badge-materiel  { background:#FAEEDA; color:#633806; }
        .badge-sponsor   { background:#EEEDFE; color:#3C3489; }
        .badge-moral     { background:#E6F1FB; color:#0C447C; }

        #champ-montant { display:none; }
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
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Parrainage — FMT2S</h2>
            <p style="font-size:12px; color:var(--gris); margin-top:2px;">Structure de soutien et mobilisation de ressources</p>
        </div>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Soutien enregistré avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Soutien supprimé.</div>
    <?php elseif ($msg === 'modifie'): ?>
        <div class="alerte alerte-succes">Statut mis à jour.</div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="val"><?= $stats['nb_actifs'] ?></div>
            <div class="lbl">Soutiens actifs</div>
        </div>
        <div class="stat-card">
            <div class="val"><?= $stats['nb_financier'] ?></div>
            <div class="lbl">Financier</div>
        </div>
        <div class="stat-card">
            <div class="val"><?= $stats['nb_materiel'] ?></div>
            <div class="lbl">Matériel</div>
        </div>
        <div class="stat-card">
            <div class="val"><?= $stats['nb_sponsor'] ?></div>
            <div class="lbl">Sponsor</div>
        </div>
        <div class="stat-card">
            <div class="val"><?= $stats['nb_moral'] ?></div>
            <div class="lbl">Moral</div>
        </div>
        <div class="stat-card gold">
            <div class="val"><?= number_format($stats['total_financier'], 0, ',', ' ') ?></div>
            <div class="lbl">Total financier (Ar)</div>
        </div>
    </div>

    <!-- FILTRES -->
    <form method="GET">
        <div class="filtres">
            <div class="champ">
                <label>Type de soutien</label>
                <select name="type">
                    <option value="">Tous</option>
                    <option value="financier" <?= $filtre_type === 'financier' ? 'selected' : '' ?>>Financier</option>
                    <option value="matériel"  <?= $filtre_type === 'matériel'  ? 'selected' : '' ?>>Matériel</option>
                    <option value="sponsor"   <?= $filtre_type === 'sponsor'   ? 'selected' : '' ?>>Sponsor</option>
                    <option value="moral"     <?= $filtre_type === 'moral'     ? 'selected' : '' ?>>Moral</option>
                </select>
            </div>
            <div class="champ">
                <label>Mpanohana</label>
                <select name="mpanohana">
                    <option value="0">Tous</option>
                    <?php foreach ($mpanohana as $m): ?>
                    <option value="<?= $m['id_membre'] ?>" <?= $filtre_mpanohana == $m['id_membre'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="champ">
                <label>Statut</label>
                <select name="actif">
                    <option value="">Tous</option>
                    <option value="1" <?= $filtre_actif === '1' ? 'selected' : '' ?>>Actifs</option>
                    <option value="0" <?= $filtre_actif === '0' ? 'selected' : '' ?>>Inactifs</option>
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
                    <th>Mpanohana (FMT2S)</th>
                    <th>Type soutien</th>
                    <th>Bénéficiaire</th>
                    <th>Activité liée</th>
                    <th>Montant</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($parrainages)): ?>
                <tr><td colspan="9" style="text-align:center; color:var(--gris); padding:32px;">Aucun soutien enregistré</td></tr>
                <?php else: ?>
                <?php foreach ($parrainages as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['mpanohana_nom']) ?></strong></td>
                    <td>
                        <span class="badge badge-<?= $p['type_soutien'] === 'matériel' ? 'materiel' : $p['type_soutien'] ?>">
                            <?= ucfirst(htmlspecialchars($p['type_soutien'])) ?>
                        </span>
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars($p['beazina_nom'] ?? '— Général —') ?></td>
                    <td style="font-size:12px; color:var(--gris);"><?= htmlspecialchars($p['hetsika_titre'] ?? '—') ?></td>
                    <td style="font-weight:700; color:var(--vert2);">
                        <?= $p['montant'] ? number_format($p['montant'], 0, ',', ' ') . ' Ar' : '—' ?>
                    </td>
                    <td style="font-size:12px; color:var(--gris);"><?= htmlspecialchars(mb_substr($p['description'] ?? '—', 0, 40)) ?></td>
                    <td style="font-size:12px;"><?= date('d/m/Y', strtotime($p['date_soutien'])) ?></td>
                    <td>
                        <span class="badge" style="background:<?= $p['actif'] ? '#e6f4ec' : '#f1efe8' ?>; color:<?= $p['actif'] ? '#085041' : 'var(--gris)' ?>;">
                            <?= $p['actif'] ? 'Actif' : 'Inactif' ?>
                        </span>
                    </td>
                    <td style="display:flex; gap:6px;">
                        <!-- Toggle actif/inactif -->
                        <form method="POST">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id_parrainage" value="<?= $p['id_parrainage'] ?>">
                            <button type="submit" class="btn btn-secondary" style="padding:4px 10px; font-size:11px;">
                                <?= $p['actif'] ? 'Désactiver' : 'Activer' ?>
                            </button>
                        </form>
                        <?php if ($user['type_membre'] === 'kp'): ?>
                        <form method="POST" onsubmit="return confirm('Supprimer ce soutien ?')">
                            <input type="hidden" name="action" value="supprimer">
                            <input type="hidden" name="id_parrainage" value="<?= $p['id_parrainage'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:11px;">Supprimer</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- AJOUTER SOUTIEN -->
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Enregistrer un soutien FMT2S
        </div>
        <div style="padding:24px;">
            <form method="POST">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
                    <div class="champ">
                        <label for="id_mpanohana">Mpanohana (FMT2S) *</label>
                        <select id="id_mpanohana" name="id_mpanohana" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($mpanohana as $m): ?>
                            <option value="<?= $m['id_membre'] ?>"><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label for="type_soutien">Type de soutien *</label>
                        <select id="type_soutien" name="type_soutien" required onchange="toggleMontant(this.value)">
                            <option value="">-- Choisir --</option>
                            <option value="financier">💰 Financier</option>
                            <option value="matériel">📦 Matériel</option>
                            <option value="sponsor">🏢 Sponsor</option>
                            <option value="moral">🤝 Moral</option>
                        </select>
                    </div>

                    <div class="champ" id="champ-montant">
                        <label for="montant">Montant (Ar)</label>
                        <input type="number" id="montant" name="montant" min="0" step="100" placeholder="Ex: 50000">
                    </div>

                    <div class="champ">
                        <label for="id_beazina">Bénéficiaire <span style="color:var(--gris); font-weight:400;">(optionnel)</span></label>
                        <select id="id_beazina" name="id_beazina">
                            <option value="">-- Soutien général --</option>
                            <?php foreach ($beazina as $b): ?>
                            <option value="<?= $b['id_membre'] ?>"><?= htmlspecialchars($b['nom'] . ' ' . $b['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="champ">
                        <label for="id_hetsika">Activité liée <span style="color:var(--gris); font-weight:400;">(optionnel)</span></label>
                        <select id="id_hetsika" name="id_hetsika">
                            <option value="">-- Soutien général --</option>
                            <?php foreach ($hetsika as $h): ?>
                            <option value="<?= $h['id_hetsika'] ?>">
                                <?= htmlspecialchars($h['titre']) ?>
                                (<?= date('d/m/Y', strtotime($h['date_debut'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="champ">
                        <label for="date_soutien">Date</label>
                        <input type="date" id="date_soutien" name="date_soutien" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="champ">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="2"
                              placeholder="Détails du soutien apporté..." style="resize:vertical;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>

</div>

<script>
function toggleMontant(type) {
    document.getElementById('champ-montant').style.display =
        ['financier','sponsor'].includes(type) ? 'block' : 'none';
}
</script>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
