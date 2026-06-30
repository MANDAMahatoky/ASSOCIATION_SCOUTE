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

$hetsika = $db->query("SELECT * FROM hetsika WHERE id_hetsika = $id")->fetch();
if (!$hetsika) { header('Location: liste.php'); exit; }

$msg    = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';

    if ($action === 'supprimer') {
        $id_rec = (int)($_POST['id_recompense'] ?? 0);
        if ($id_rec) {
            $db->prepare("DELETE FROM recompense WHERE id_recompense=?")->execute([$id_rec]);
            $msg = 'supprime';
        }
    } else {
        $libelle          = trim($_POST['libelle'] ?? '');
        $type             = $_POST['type'] ?? '';
        $date_attribution = $_POST['date_attribution'] ?? date('Y-m-d');
        $id_membre        = $_POST['id_membre'] ? (int)$_POST['id_membre'] : null;
        $id_branche       = $_POST['id_branche'] ? (int)$_POST['id_branche'] : null;

        if ($libelle === '' || $type === '') {
            $erreur = 'Le libellé et le type sont obligatoires.';
        } elseif ($type === 'individuelle' && !$id_membre) {
            $erreur = 'Une récompense individuelle nécessite un membre.';
        } elseif ($type === 'collective' && !$id_branche) {
            $erreur = 'Une récompense collective nécessite une branche.';
        } else {
            $db->prepare("
                INSERT INTO recompense (libelle, type, date_attribution, id_hetsika, id_membre, id_branche)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$libelle, $type, $date_attribution, $id, $id_membre, $id_branche]);
            $msg = 'ajoute';
        }
    }
}

// Récompenses existantes
$recompenses = $db->query("
    SELECT r.id_recompense, r.libelle, r.type, r.date_attribution,
           m.nom || ' ' || m.prenom AS membre_nom,
           b.nom AS branche_nom
    FROM recompense r
    LEFT JOIN mpikambana m ON m.id_membre = r.id_membre
    LEFT JOIN branche b ON b.id_branche = r.id_branche
    WHERE r.id_hetsika = $id
    ORDER BY r.date_attribution DESC
")->fetchAll();

$membres  = $db->query("SELECT id_membre, nom, prenom FROM mpikambana ORDER BY nom")->fetchAll();
$branches = $db->query("SELECT id_branche, nom FROM branche ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Récompenses — <?= htmlspecialchars($hetsika['titre']) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        #champ-membre, #champ-branche { display:none; }
    </style>
</head>
<body class="with-nav">

<nav>
    <div class="nav-brand"><span>⚜</span> Association Scoute</div>
    <div class="nav-user">
        <span><strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong></span>
        <a href="../../logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<div class="container" style="max-width:750px;">

    <div style="margin-bottom:24px;">
        <a href="detail.php?id=<?= $id ?>" style="font-size:13px; color:var(--gris); text-decoration:none;">← Retour à l'activité</a>
        <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">
            Récompenses — <?= htmlspecialchars($hetsika['titre']) ?>
        </h2>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Récompense ajoutée.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Récompense supprimée.</div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <!-- LISTE RÉCOMPENSES -->
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden; margin-bottom:24px;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Récompenses attribuées (<?= count($recompenses) ?>)
        </div>
        <?php if (empty($recompenses)): ?>
            <div style="padding:20px; color:var(--gris); font-size:13px; text-align:center;">Aucune récompense</div>
        <?php else: ?>
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead style="background:#f5f0e8;">
                <tr>
                    <th style="padding:10px 16px; text-align:left; font-size:11px; color:var(--gris); text-transform:uppercase;">Libellé</th>
                    <th style="padding:10px 16px; text-align:left; font-size:11px; color:var(--gris); text-transform:uppercase;">Type</th>
                    <th style="padding:10px 16px; text-align:left; font-size:11px; color:var(--gris); text-transform:uppercase;">Attribué à</th>
                    <th style="padding:10px 16px; text-align:left; font-size:11px; color:var(--gris); text-transform:uppercase;">Date</th>
                    <th style="padding:10px 16px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recompenses as $r): ?>
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:10px 16px;"><strong>🏆 <?= htmlspecialchars($r['libelle']) ?></strong></td>
                <td style="padding:10px 16px;">
                    <span class="badge" style="background:<?= $r['type']==='individuelle' ? '#E1F5EE' : '#FAEEDA' ?>; color:<?= $r['type']==='individuelle' ? '#085041' : '#633806' ?>;">
                        <?= $r['type'] ?>
                    </span>
                </td>
                <td style="padding:10px 16px;"><?= htmlspecialchars($r['membre_nom'] ?? $r['branche_nom'] ?? '—') ?></td>
                <td style="padding:10px 16px; font-size:12px; color:var(--gris);"><?= date('d/m/Y', strtotime($r['date_attribution'])) ?></td>
                <td style="padding:10px 16px;">
                    <form method="POST" onsubmit="return confirm('Supprimer ?')">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="id_recompense" value="<?= $r['id_recompense'] ?>">
                        <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:11px;">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- AJOUTER RÉCOMPENSE -->
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Ajouter une récompense
        </div>
        <div style="padding:24px;">
            <form method="POST">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
                    <div class="champ">
                        <label for="libelle">Libellé *</label>
                        <input type="text" id="libelle" name="libelle"
                               value="<?= htmlspecialchars($_POST['libelle'] ?? '') ?>"
                               placeholder="Ex: Coupe du meilleur camp" required>
                    </div>
                    <div class="champ">
                        <label for="type">Type *</label>
                        <select id="type" name="type" required onchange="toggleType(this.value)">
                            <option value="">-- Choisir --</option>
                            <option value="individuelle" <?= ($_POST['type'] ?? '') === 'individuelle' ? 'selected' : '' ?>>Individuelle</option>
                            <option value="collective"   <?= ($_POST['type'] ?? '') === 'collective'   ? 'selected' : '' ?>>Collective</option>
                        </select>
                    </div>
                </div>

                <div id="champ-membre" class="champ">
                    <label for="id_membre">Membre *</label>
                    <select id="id_membre" name="id_membre">
                        <option value="">-- Choisir un membre --</option>
                        <?php foreach ($membres as $m): ?>
                        <option value="<?= $m['id_membre'] ?>"><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="champ-branche" class="champ">
                    <label for="id_branche">Branche *</label>
                    <select id="id_branche" name="id_branche">
                        <option value="">-- Choisir une branche --</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id_branche'] ?>"><?= htmlspecialchars($b['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="champ">
                    <label for="date_attribution">Date d'attribution</label>
                    <input type="date" id="date_attribution" name="date_attribution"
                           value="<?= htmlspecialchars($_POST['date_attribution'] ?? date('Y-m-d')) ?>">
                </div>

                <button type="submit" class="btn btn-primary">Ajouter</button>
            </form>
        </div>
    </div>

</div>

<script>
function toggleType(val) {
    document.getElementById('champ-membre').style.display  = val === 'individuelle' ? 'block' : 'none';
    document.getElementById('champ-branche').style.display = val === 'collective'   ? 'block' : 'none';
}
toggleType(document.getElementById('type').value);
</script>

<footer>Association Scoute &mdash; <?= date('Y') ?></footer>
</body>
</html>
