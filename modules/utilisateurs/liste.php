<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../SessionManager.php';

$handler = new SessionManager(getDB());
session_set_save_handler($handler, true);
session_start();

if (!isset($_SESSION['utilisateur'])) { header('Location: ../../login.php'); exit; }

// Seul le KP/admin peut accéder
if ($_SESSION['utilisateur']['type_membre'] !== 'kp') {
    header('Location: ../../index.php');
    exit;
}

$user = $_SESSION['utilisateur'];
$db   = getDB();

$msg    = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CRÉER UN COMPTE ──────────────────────────────────────
    if ($action === 'creer') {
        $id_membre = (int)($_POST['id_membre'] ?? 0);
        $login     = trim($_POST['login'] ?? '');
        $mdp       = $_POST['mot_de_passe'] ?? '';
        $mdp2      = $_POST['mot_de_passe2'] ?? '';

        if (!$id_membre || $login === '' || $mdp === '') {
            $erreur = 'Tous les champs sont obligatoires.';
        } elseif (strlen($mdp) < 6) {
            $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
        } elseif ($mdp !== $mdp2) {
            $erreur = 'Les mots de passe ne correspondent pas.';
        } else {
            // Vérifier si le membre a déjà un compte
            $existe = $db->prepare("SELECT COUNT(*) FROM utilisateur WHERE id_membre = ?");
            $existe->execute([$id_membre]);
            if ($existe->fetchColumn() > 0) {
                $erreur = 'Ce membre a déjà un compte utilisateur.';
            } else {
                // Vérifier si le login est disponible
                $login_existe = $db->prepare("SELECT COUNT(*) FROM utilisateur WHERE login = ?");
                $login_existe->execute([$login]);
                if ($login_existe->fetchColumn() > 0) {
                    $erreur = 'Ce login est déjà utilisé.';
                } else {
                    $hash = password_hash($mdp, PASSWORD_BCRYPT);
                    $db->prepare("
                        INSERT INTO utilisateur (id_membre, login, mot_de_passe, actif)
                        VALUES (?, ?, ?, TRUE)
                    ")->execute([$id_membre, $login, $hash]);
                    $msg = 'cree';
                }
            }
        }
    }

    // ── ACTIVER / DÉSACTIVER ─────────────────────────────────
    elseif ($action === 'toggle') {
        $id = (int)($_POST['id_utilisateur'] ?? 0);
        if ($id && $id !== $user['id']) {
            $db->prepare("UPDATE utilisateur SET actif = NOT actif WHERE id_utilisateur = ?")
               ->execute([$id]);
            $msg = 'toggle';
        } else {
            $erreur = 'Vous ne pouvez pas désactiver votre propre compte.';
        }
    }

    // ── RÉINITIALISER MOT DE PASSE ───────────────────────────
    elseif ($action === 'reset_mdp') {
        $id  = (int)($_POST['id_utilisateur'] ?? 0);
        $mdp = $_POST['nouveau_mdp'] ?? '';
        $mdp2 = $_POST['nouveau_mdp2'] ?? '';

        if (!$id || $mdp === '') {
            $erreur = 'Mot de passe obligatoire.';
        } elseif (strlen($mdp) < 6) {
            $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
        } elseif ($mdp !== $mdp2) {
            $erreur = 'Les mots de passe ne correspondent pas.';
        } else {
            $hash = password_hash($mdp, PASSWORD_BCRYPT);
            $db->prepare("UPDATE utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?")
               ->execute([$hash, $id]);
            $msg = 'reset';
        }
    }

    // ── MODIFIER LOGIN ───────────────────────────────────────
    elseif ($action === 'modifier_login') {
        $id        = (int)($_POST['id_utilisateur'] ?? 0);
        $new_login = trim($_POST['nouveau_login'] ?? '');

        if (!$id || $new_login === '') {
            $erreur = 'Login obligatoire.';
        } else {
            $login_existe = $db->prepare("SELECT COUNT(*) FROM utilisateur WHERE login = ? AND id_utilisateur != ?");
            $login_existe->execute([$new_login, $id]);
            if ($login_existe->fetchColumn() > 0) {
                $erreur = 'Ce login est déjà utilisé.';
            } else {
                $db->prepare("UPDATE utilisateur SET login = ? WHERE id_utilisateur = ?")
                   ->execute([$new_login, $id]);
                $msg = 'login_modifie';
            }
        }
    }

    // ── SUPPRIMER ────────────────────────────────────────────
    elseif ($action === 'supprimer') {
        $id = (int)($_POST['id_utilisateur'] ?? 0);
        if ($id && $id !== $user['id']) {
            $db->prepare("DELETE FROM utilisateur WHERE id_utilisateur = ?")->execute([$id]);
            $msg = 'supprime';
        } else {
            $erreur = 'Vous ne pouvez pas supprimer votre propre compte.';
        }
    }
}

// Liste des utilisateurs
$utilisateurs = $db->query("
    SELECT u.id_utilisateur, u.login, u.actif, u.derniere_connexion,
           m.nom, m.prenom, m.type_membre, m.code_membre
    FROM utilisateur u
    JOIN mpikambana m ON m.id_membre = u.id_membre
    ORDER BY u.actif DESC, m.nom, m.prenom
")->fetchAll();

// Membres sans compte
$sans_compte = $db->query("
    SELECT m.id_membre, m.nom, m.prenom, m.type_membre, m.code_membre
    FROM mpikambana m
    LEFT JOIN utilisateur u ON u.id_membre = m.id_membre
    WHERE u.id_utilisateur IS NULL
    ORDER BY m.nom
")->fetchAll();

// Modal ouvert
$modal_reset = (int)($_GET['reset'] ?? 0);
$modal_login = (int)($_GET['login'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs — Association Scoute</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #fff;
            border-radius: 8px;
            padding: 28px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal h3 { font-family:'Cinzel',serif; color:var(--bleu); margin-bottom:16px; font-size:16px; }
        .modal .btn { margin-top:8px; }

        .status-dot {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-dot.actif   { background: #22c55e; }
        .status-dot.inactif { background: #ef4444; }

        .actions-cell { display:flex; gap:4px; flex-wrap:wrap; }
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
            <h2 style="font-family:'Cinzel',serif; color:var(--bleu); margin-top:4px;">Gestion des Utilisateurs</h2>
            <p style="color:var(--gris); font-size:13px;">Réservé aux administrateurs KP</p>
        </div>
    </div>

    <?php if ($msg === 'cree'): ?>
        <div class="alerte alerte-succes">Compte créé avec succès.</div>
    <?php elseif ($msg === 'toggle'): ?>
        <div class="alerte alerte-succes">Statut du compte mis à jour.</div>
    <?php elseif ($msg === 'reset'): ?>
        <div class="alerte alerte-succes">Mot de passe réinitialisé.</div>
    <?php elseif ($msg === 'login_modifie'): ?>
        <div class="alerte alerte-succes">Login modifié avec succès.</div>
    <?php elseif ($msg === 'supprime'): ?>
        <div class="alerte alerte-succes">Compte supprimé.</div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte alerte-erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <!-- LISTE DES COMPTES -->
    <div class="section-title">Comptes existants (<?= count($utilisateurs) ?>)</div>
    <div class="table-wrap" style="margin-bottom:32px;">
        <table>
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Type</th>
                    <th>Login</th>
                    <th>Statut</th>
                    <th>Dernière connexion</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u):
                    $badge = match($u['type_membre']) {
                        'beazina'        => 'badge-beazina',
                        'mpanabe'        => 'badge-mpanabe',
                        'kp'             => 'badge-kp',
                        'mpanohana'      => 'badge-mpanohana',
                        'ray aman-dreny' => 'badge-ray',
                        default          => ''
                    };
                    $est_moi = $u['id_utilisateur'] == $user['id'];
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong>
                        <?php if ($u['code_membre']): ?>
                        <span style="font-size:11px; color:var(--gris);"> — <?= htmlspecialchars($u['code_membre']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($u['type_membre']) ?></span></td>
                    <td><code style="font-size:13px;"><?= htmlspecialchars($u['login']) ?></code></td>
                    <td>
                        <span class="status-dot <?= $u['actif'] ? 'actif' : 'inactif' ?>"></span>
                        <?= $u['actif'] ? 'Actif' : 'Inactif' ?>
                        <?php if ($est_moi): ?>
                        <span style="font-size:10px; color:var(--gris);"> (vous)</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px; color:var(--gris);">
                        <?= $u['derniere_connexion'] ? date('d/m/Y H:i', strtotime($u['derniere_connexion'])) : 'Jamais' ?>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <!-- Modifier login -->
                            <a href="?login=<?= $u['id_utilisateur'] ?>" class="btn btn-secondary" style="padding:4px 10px; font-size:11px;">Login</a>

                            <!-- Reset MDP -->
                            <a href="?reset=<?= $u['id_utilisateur'] ?>" class="btn btn-secondary" style="padding:4px 10px; font-size:11px;">MDP</a>

                            <?php if (!$est_moi): ?>
                            <!-- Activer/Désactiver -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id_utilisateur" value="<?= $u['id_utilisateur'] ?>">
                                <button type="submit" class="btn <?= $u['actif'] ? 'btn-secondary' : 'btn-primary' ?>" style="padding:4px 10px; font-size:11px;">
                                    <?= $u['actif'] ? 'Désactiver' : 'Activer' ?>
                                </button>
                            </form>

                            <!-- Supprimer -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce compte ?')">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id_utilisateur" value="<?= $u['id_utilisateur'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding:4px 10px; font-size:11px;">Supprimer</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- CRÉER UN COMPTE -->
    <?php if (!empty($sans_compte)): ?>
    <div class="section-title">Créer un compte</div>
    <div style="background:#fff; border:1px solid var(--border); border-radius:8px; overflow:hidden; max-width:600px;">
        <div style="background:var(--bleu); color:#fff; padding:10px 16px; font-family:'Cinzel',serif; font-size:11px; letter-spacing:1.5px; text-transform:uppercase;">
            Nouveau compte utilisateur
        </div>
        <div style="padding:24px;">
            <form method="POST">
                <input type="hidden" name="action" value="creer">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 20px;">
                    <div class="champ">
                        <label>Membre *</label>
                        <select name="id_membre" required>
                            <option value="">-- Choisir un membre --</option>
                            <?php foreach ($sans_compte as $m): ?>
                            <option value="<?= $m['id_membre'] ?>">
                                <?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?>
                                (<?= htmlspecialchars($m['type_membre']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="champ">
                        <label>Login *</label>
                        <input type="text" name="login" placeholder="Ex: jean.rakoto" required>
                    </div>
                    <div class="champ">
                        <label>Mot de passe * <span style="color:var(--gris); font-weight:400;">(min. 6 caractères)</span></label>
                        <input type="password" name="mot_de_passe" required>
                    </div>
                    <div class="champ">
                        <label>Confirmer mot de passe *</label>
                        <input type="password" name="mot_de_passe2" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Créer le compte</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="acces-note">Tous les membres ont déjà un compte utilisateur.</div>
    <?php endif; ?>

</div>

<!-- MODAL RESET MDP -->
<?php if ($modal_reset): ?>
<?php $u_reset = $db->query("SELECT u.id_utilisateur, u.login, m.nom, m.prenom FROM utilisateur u JOIN mpikambana m ON m.id_membre = u.id_membre WHERE u.id_utilisateur = $modal_reset")->fetch(); ?>
<?php if ($u_reset): ?>
<div class="modal-overlay open">
    <div class="modal">
        <h3>Réinitialiser le mot de passe</h3>
        <p style="font-size:13px; color:var(--gris); margin-bottom:16px;">
            Compte : <strong><?= htmlspecialchars($u_reset['nom'] . ' ' . $u_reset['prenom']) ?></strong>
            (<?= htmlspecialchars($u_reset['login']) ?>)
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_mdp">
            <input type="hidden" name="id_utilisateur" value="<?= $modal_reset ?>">
            <div class="champ">
                <label>Nouveau mot de passe *</label>
                <input type="password" name="nouveau_mdp" required>
            </div>
            <div class="champ">
                <label>Confirmer *</label>
                <input type="password" name="nouveau_mdp2" required>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="liste.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- MODAL MODIFIER LOGIN -->
<?php if ($modal_login): ?>
<?php $u_login = $db->query("SELECT u.id_utilisateur, u.login, m.nom, m.prenom FROM utilisateur u JOIN mpikambana m ON m.id_membre = u.id_membre WHERE u.id_utilisateur = $modal_login")->fetch(); ?>
<?php if ($u_login): ?>
<div class="modal-overlay open">
    <div class="modal">
        <h3>Modifier le login</h3>
        <p style="font-size:13px; color:var(--gris); margin-bottom:16px;">
            Membre : <strong><?= htmlspecialchars($u_login['nom'] . ' ' . $u_login['prenom']) ?></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="modifier_login">
            <input type="hidden" name="id_utilisateur" value="<?= $modal_login ?>">
            <div class="champ">
                <label>Login actuel</label>
                <input type="text" value="<?= htmlspecialchars($u_login['login']) ?>" disabled style="background:#f5f5f5;">
            </div>
            <div class="champ">
                <label>Nouveau login *</label>
                <input type="text" name="nouveau_login" value="<?= htmlspecialchars($u_login['login']) ?>" required>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="liste.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<footer>Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?></footer>
</body>
</html>
