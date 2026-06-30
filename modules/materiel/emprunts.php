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

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'retour') {
        $id_emprunt = (int)($_POST['id_emprunt'] ?? 0);
        if ($id_emprunt) {
            $db->prepare("
                UPDATE emprunt SET date_retour_effective = CURRENT_DATE WHERE id_emprunt=?
            ")->execute([$id_emprunt]);
            $msg = 'retour';
        }
    } elseif ($action === 'ajouter') {
        $id_materiel   = (int)($_POST['id_materiel'] ?? 0);
        $id_responsable = (int)($_POST['id_responsable'] ?? 0);
        $date_emprunt  = $_POST['date_emprunt'] ?? date('Y-m-d');
        $date_retour   = $_POST['date_retour_prevue'] ?: null;

        if (!$id_materiel || !$id_responsable) {
            $erreur = 'Le matériel et le responsable sont obligatoires.';
        } else {
            $db->prepare("
                INSERT INTO emprunt (date_emprunt, date_retour_prevue, id_materiel, id_responsable)
                VALUES (?, ?, ?, ?)
            ")->execute([$date_emprunt, $date_retour, $id_materiel, $id_responsable]);
            $msg = 'ajoute';
        }
    }
}

// Filtre
$filtre = $_GET['filtre'] ?? 'en_cours';

if ($filtre === 'en_cours') {
    $sql_where = "WHERE e.date_retour_effective IS NULL";
} elseif ($filtre === 'retournes') {
    $sql_where = "WHERE e.date_retour_effective IS NOT NULL";
} else {
    $sql_where = "";
}

$emprunts = $db->query("
    SELECT e.id_emprunt, e.date_emprunt, e.date_retour_prevue, e.date_retour_effective,
           mat.nom AS materiel, mat.id_materiel,
           m.nom || ' ' || m.prenom AS responsable
    FROM emprunt e
    JOIN materiel mat ON mat.id_materiel = e.id_materiel
    JOIN mpikambana m ON m.id_membre = e.id_responsable
    $sql_where
    ORDER BY e.date_emprunt DESC
")->fetchAll();

$materiels = $db->query("SELECT id_materiel, nom FROM materiel ORDER BY nom")->fetchAll();
$membres   = $db->query("SELECT id_membre, nom, prenom FROM mpikambana ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprunts — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .tabs { display:flex; gap:4px; margin-bottom:20px; }
        .tab { padding:8px 18px; border-radius:3px; font-size:13px; text-decoration:none; border:1px solid var(--border); color:var(--gris); }
        .tab.actif { background:var(--vert2); color:#fff; border-color:var(--vert2); }
        .retard { color:var(--rouge); font-weight:700; }
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
            <a href="liste.php" style="font-size:13px; color:var(--gris); text-decoration:none;">← Matériel</a>
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Emprunts</h2>
        </div>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Emprunt enregistré.</div>
    <?php elseif ($msg === 'retour'): ?>
        <div class="alerte alerte-succes">Retour enregistré.</div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
        <a href="?filtre=en_cours"  class="tab <?= $filtre === 'en_cours'  ? 'actif' : '' ?>">En cours</a>
        <a href="?filtre=retournes" class="tab <?= $filtre === 'retournes' ? 'actif' : '' ?>">Retournés</a>
        <a href="?filtre=tous"      class="tab <?= $filtre === 'tous'      ? 'actif' : '' ?>">Tous</a>
    </div>

    <!-- TABLEAU EMPRUNTS -->
    <div class="table-wrap" style="margin-bottom:32px;">
        <table>
            <thead>
                <tr>
                    <th>Matériel</th>
                    <th>Responsable</th>
                    <th>Date emprunt</th>
                    <th>Retour prévu</th>
                    <th>Retour effectif</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($emprunts)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--gris); padding:32px;">Aucun emprunt</td></tr>
                <?php else: ?>
                <?php foreach ($emprunts as $e):
                    $en_retard = !$e['date_retour_effective'] && $e['date_retour_prevue'] && $e['date_retour_prevue'] < date('Y-m-d');
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($e['materiel']) ?></strong></td>
                    <td><?= htmlspecialchars($e['responsable']) ?></td>
                    <td><?= date('d/m/Y', strtotime($e['date_emprunt'])) ?></td>
                    <td class="<?= $en_retard ? 'retard' : '' ?>">
                        <?= $e['date_retour_prevue'] ? date('d/m/Y', strtotime($e['date_retour_prevue'])) : '—' ?>
                        <?php if ($en_retard): ?> ⚠️<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($e['date_retour_effective']): ?>
                            <span style="color:var(--vert2);">✅ <?= date('d/m/Y', strtotime($e['date_retour_effective'])) ?></span>
                        <?php else: ?>
                            <span style="color:var(--gris);">En cours</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$e['date_retour_effective']): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="retour">
                            <input type="hidden" name="id_emprunt" value="<?= $e['id_emprunt'] ?>">
                            <button type="submit" class="btn btn-primary" style="padding:5px 12px; font-size:11px;">
                                Marquer retour
                            </button>
                        </form>
                        <?php else: ?>
                            <span style="color:var(--gris); font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- NOUVEL EMPRUNT -->
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Enregistrer un emprunt
        </div>
        <div style="padding:24px;">
            <form method="POST">
                <input type="hidden" name="action" value="ajouter">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
                    <div class="champ">
                        <label for="id_materiel">Matériel *</label>
                        <select id="id_materiel" name="id_materiel" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($materiels as $m): ?>
                            <option value="<?= $m['id_materiel'] ?>"><?= htmlspecialchars($m['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label for="id_responsable">Responsable *</label>
                        <select id="id_responsable" name="id_responsable" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($membres as $m): ?>
                            <option value="<?= $m['id_membre'] ?>"><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label for="date_emprunt">Date d'emprunt</label>
                        <input type="date" id="date_emprunt" name="date_emprunt" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="champ">
                        <label for="date_retour_prevue">Retour prévu</label>
                        <input type="date" id="date_retour_prevue" name="date_retour_prevue">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>

</div>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
