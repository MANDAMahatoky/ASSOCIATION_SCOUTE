<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

$user = $_SESSION['utilisateur'];
$db   = getDB();

$peut_valider = in_array($user['type_membre'], ['kp', 'mpanabe']);

// Grades par branche
$grades_par_branche = [
    // Tily - Ambaratonga
    'Lovitao'     => ['type' => 'ambaratonga', 'niveau' => 'Mavo','grades' => ['Vakimaso 1', 'Vakimaso 2', 'Mpiremby']],
    'Tily'   => ['type' => 'ambaratonga', 'niveau' => 'Maitso','grades' => ['Zazavao', 'Mpikatroka', 'Menavazana']],
    'Mpiandalana'     => ['type' => 'ambaratonga', 'niveau' => 'Mena','grades' => ['Mpiomana', 'Mpiatrika', 'Mpihary']],
    'Mpitarika' => ['type' => 'ambaratonga', 'niveau' => 'Menafify', 'grades' => ['Mpiketrika mameno', 'Mpialoha làlana', 'Mahatsangy no ary']],
    // Mpanazava - Dingana
    'Voronkely'     => ['type' => 'dingana',     'niveau' => 'Mavo','grades' => ['Ary elatra', 'Kopak\'elatra', 'Mihintsan\'elatra', 'Fanekena voronkely']],
    'Mpanazava'     => ['type' => 'dingana',     'niveau' => 'Maitso','grades' => ['Tsiry', 'Voa', 'Fanja', 'Fanekena mpanazava maitso']],
    'Afo'           => ['type' => 'dingana',     'niveau' => 'Mena',  'grades' => ['Rehitra', 'Donak\'afo', 'Fanekena afo', 'Redareda']],
];

$msg    = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$peut_valider) {
        $erreur = "Seuls les mpanabe et kp peuvent valider un grade.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'supprimer') {
            $id_amb = (int)($_POST['id_ambaratonga'] ?? 0);
            if ($id_amb) {
                $db->prepare("DELETE FROM ambaratonga WHERE id_ambaratonga=?")->execute([$id_amb]);
                $msg = 'supprime';
            }
        } else {
            $id_membre      = (int)($_POST['id_membre'] ?? 0);
            $libelle        = trim($_POST['libelle'] ?? '');
            $type_grade     = $_POST['type_grade'] ?? 'ambaratonga';
            $niveau         = trim($_POST['niveau'] ?? '') ?: null;
            $description    = trim($_POST['description'] ?? '') ?: null;
            $date_obtention = $_POST['date_obtention'] ?? date('Y-m-d');

            if (!$id_membre || $libelle === '') {
                $erreur = 'Le membre et le grade sont obligatoires.';
            } else {
                // Désactiver l'ancien grade actif
                $db->prepare("UPDATE ambaratonga SET actif = FALSE WHERE id_membre = ? AND actif = TRUE")
                   ->execute([$id_membre]);

                $db->prepare("
                    INSERT INTO ambaratonga (libelle, description, date_obtention, actif, id_membre, id_validateur, type_grade, niveau)
                    VALUES (?, ?, ?, TRUE, ?, ?, ?, ?)
                ")->execute([$libelle, $description, $date_obtention, $id_membre, $user['id_membre'], $type_grade, $niveau]);

                $msg = 'ajoute';
            }
        }
    }
}

// Liste des grades
$filtre_type = $_GET['type'] ?? '';
$where  = $filtre_type ? "WHERE a.type_grade = '$filtre_type'" : '';

$grades = $db->query("
    SELECT a.id_ambaratonga, a.libelle, a.date_obtention, a.actif, a.type_grade, a.niveau,
           m.id_membre, m.nom || ' ' || m.prenom AS membre_nom, m.type_membre,
           b.nom AS branche_nom,
           v.nom || ' ' || v.prenom AS validateur_nom
    FROM ambaratonga a
    JOIN mpikambana m ON m.id_membre = a.id_membre
    LEFT JOIN branche b ON b.id_branche = m.id_branche
    LEFT JOIN mpikambana v ON v.id_membre = a.id_validateur
    $where
    ORDER BY a.actif DESC, a.date_obtention DESC
")->fetchAll();

// Membres beazina/mpanabe avec leur branche
$beazina = $db->query("
    SELECT m.id_membre, m.nom, m.prenom, m.type_membre, b.nom AS branche_nom
    FROM mpikambana m
    LEFT JOIN branche b ON b.id_branche = m.id_branche
    WHERE m.type_membre IN ('beazina','mpanabe')
    ORDER BY m.nom
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .mini-badge { display:inline-block; font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px; }
        .mini-badge.actif  { background:#e6f4ec; color:var(--vert2); }
        .mini-badge.ancien { background:#f1efe8; color:var(--gris); }
        .mini-badge.tily   { background:#EEEDFE; color:#3C3489; }
        .mini-badge.mpanazava { background:#FAEEDA; color:#633806; }
        .tabs { display:flex; gap:4px; margin-bottom:20px; }
        .tab  { padding:8px 18px; border-radius:3px; font-size:13px; text-decoration:none; border:1px solid var(--border); color:var(--gris); }
        .tab.actif { background:var(--vert2); color:#fff; border-color:var(--vert2); }
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
            <h2 style="font-family:'Cinzel',serif; color:var(--vert2); margin-top:4px;">Grades — Ambaratonga & Dingana</h2>
        </div>
        <a href="talenta.php" class="btn btn-secondary">🎯 Talents</a>
    </div>

    <?php if ($msg === 'ajoute'): ?>
        <div class="alerte alerte-succes">Grade attribué. L'ancien grade actif a été archivé.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Grade supprimé.</div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if (!$peut_valider): ?>
    <div class="acces-note">Seuls les <strong>mpanabe</strong> et <strong>kp</strong> peuvent attribuer ou supprimer un grade.</div>
    <?php endif; ?>

    <!-- TABS FILTRE -->
    <div class="tabs">
        <a href="ambaratonga.php"               class="tab <?= $filtre_type === ''            ? 'actif' : '' ?>">Tous</a>
        <a href="ambaratonga.php?type=ambaratonga" class="tab <?= $filtre_type === 'ambaratonga' ? 'actif' : '' ?>">⭐ Ambaratonga (Tily)</a>
        <a href="ambaratonga.php?type=dingana"     class="tab <?= $filtre_type === 'dingana'     ? 'actif' : '' ?>">🐦 Dingana (Mpanazava)</a>
    </div>

    <!-- TABLEAU -->
    <div class="table-wrap" style="margin-bottom:32px;">
        <table>
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Branche</th>
                    <th>Type</th>
                    <th>Niveau</th>
                    <th>Grade</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Validé par</th>
                    <?php if ($peut_valider): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grades)): ?>
                <tr><td colspan="<?= $peut_valider ? 9 : 8 ?>" style="text-align:center; color:var(--gris); padding:32px;">Aucun grade enregistré</td></tr>
                <?php else: ?>
                <?php foreach ($grades as $g): ?>
                <tr>
                    <td>
                        <a href="../membres/detail.php?id=<?= $g['id_membre'] ?>"
                           style="color:var(--vert2); font-weight:700; text-decoration:none;">
                            <?= htmlspecialchars($g['membre_nom']) ?>
                        </a>
                    </td>
                    <td style="font-size:12px;"><?= htmlspecialchars($g['branche_nom'] ?? '—') ?></td>
                    <td>
                        <span class="mini-badge <?= $g['type_grade'] === 'ambaratonga' ? 'tily' : 'mpanazava' ?>">
                            <?= $g['type_grade'] === 'ambaratonga' ? 'Ambaratonga' : 'Dingana' ?>
                        </span>
                    </td>
                    <td style="font-size:12px; color:var(--gris);"><?= htmlspecialchars($g['niveau'] ?? '—') ?></td>
                    <td><strong><?= htmlspecialchars($g['libelle']) ?></strong></td>
                    <td><span class="mini-badge <?= $g['actif'] ? 'actif' : 'ancien' ?>"><?= $g['actif'] ? 'Actif' : 'Archivé' ?></span></td>
                    <td style="font-size:12px;"><?= date('d/m/Y', strtotime($g['date_obtention'])) ?></td>
                    <td style="font-size:12px; color:var(--gris);"><?= htmlspecialchars($g['validateur_nom'] ?? '—') ?></td>
                    <?php if ($peut_valider): ?>
                    <td>
                        <form method="POST" onsubmit="return confirm('Supprimer ce grade ?')">
                            <input type="hidden" name="action" value="supprimer">
                            <input type="hidden" name="id_ambaratonga" value="<?= $g['id_ambaratonga'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:11px;">Supprimer</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ATTRIBUER UN GRADE -->
    <?php if ($peut_valider): ?>
    <div style="background:var(--blanc); border:1px solid var(--border); border-radius:6px; overflow:hidden;">
        <div style="background:var(--vert2); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Attribuer un grade
        </div>
        <div style="padding:24px;">
            <p style="font-size:12px; color:var(--gris); margin-bottom:16px;">
                ⚠️ Attribuer un nouveau grade archivera automatiquement le grade actif précédent du membre.
            </p>
            <form method="POST">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
                    <div class="champ">
                        <label for="id_membre">Membre *</label>
                        <select id="id_membre" name="id_membre" required onchange="updateGrades(this)">
                            <option value="">-- Choisir un membre --</option>
                            <?php foreach ($beazina as $b): ?>
                            <option value="<?= $b['id_membre'] ?>"
                                    data-branche="<?= htmlspecialchars($b['branche_nom'] ?? '') ?>">
                                <?= htmlspecialchars($b['nom'] . ' ' . $b['prenom']) ?>
                                <?php if ($b['branche_nom']): ?>(<?= htmlspecialchars($b['branche_nom']) ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label for="libelle">Grade *</label>
                        <select id="libelle" name="libelle" required>
                            <option value="">-- Choisir d'abord un membre --</option>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="type_grade" name="type_grade" value="">
                <input type="hidden" id="niveau" name="niveau" value="">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
                    <div class="champ">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" placeholder="Optionnel">
                    </div>
                    <div class="champ">
                        <label for="date_obtention">Date d'obtention</label>
                        <input type="date" id="date_obtention" name="date_obtention" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- Info branche détectée -->
                <div id="info-branche" style="display:none; margin-bottom:16px; padding:10px 14px; background:rgba(45,106,79,0.06); border-left:3px solid var(--vert); border-radius:3px; font-size:13px; color:var(--vert2);">
                </div>

                <button type="submit" class="btn btn-primary">Attribuer</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Données grades par branche depuis PHP
const gradesBranche = <?= json_encode($grades_par_branche) ?>;

function updateGrades(selectMembre) {
    const option     = selectMembre.options[selectMembre.selectedIndex];
    const branche    = option ? option.getAttribute('data-branche') : '';
    const selectGrade = document.getElementById('libelle');
    const infoDiv    = document.getElementById('info-branche');
    const typeGradeInput = document.getElementById('type_grade');
    const niveauInput    = document.getElementById('niveau');

    // Réinitialiser
    selectGrade.innerHTML = '<option value="">-- Choisir un grade --</option>';
    typeGradeInput.value  = '';
    niveauInput.value     = '';
    infoDiv.style.display = 'none';

    if (!branche || !gradesBranche[branche]) {
        selectGrade.innerHTML = '<option value="">-- Branche non reconnue --</option>';
        return;
    }

    const data = gradesBranche[branche];
    typeGradeInput.value = data.type;
    niveauInput.value    = data.niveau || '';

    // Afficher info
    const typeLabel = data.type === 'ambaratonga' ? 'Ambaratonga (Tily)' : 'Dingana (Mpanazava)';
    const niveauLabel = data.niveau ? ` — Niveau ${data.niveau}` : '';
    infoDiv.textContent  = `Branche : ${branche} → ${typeLabel}${niveauLabel}`;
    infoDiv.style.display = 'block';

    // Remplir les grades
    data.grades.forEach(function(g) {
        const opt = document.createElement('option');
        opt.value       = g;
        opt.textContent = g;
        selectGrade.appendChild(opt);
    });
}
</script>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
