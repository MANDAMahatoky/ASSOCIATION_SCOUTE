<?php
session_start();

// Protection : rediriger vers login si non connecté
if (!isset($_SESSION['utilisateur'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['utilisateur'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil — Association Scoute</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="with-nav">

<!-- NAVBAR -->
<nav>
    <div class="nav-brand">
        <img src="assets/images/logo.png" alt="Logo" height="40"> Association Scoute
    </div>
    <div class="nav-user">
        <span>Bonjour, <strong><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></strong></span>
        <span class="badge-type"><?= htmlspecialchars($user['type_membre']) ?></span>
        <a href="logout.php" class="btn-logout">Déconnexion</a>
    </div>
</nav>

<!-- CONTENU -->
<div class="container">

    <div class="welcome">
        <h2>Tableau de bord</h2>
        <p>Bienvenue dans l'espace de gestion de l'association scoute.</p>
    </div>

    <div class="section-title">Organisation</div>
    <div class="grid">
        <a href="includes/vondrona_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/vondrona.png" alt="Vondrona" height="48"></div>
            <h3>Vondrona</h3>
            <p>Gérer les groupements Tily et Mpanazava</p>
        </a>
        <a href="includes/branche_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/branche.png" alt="Branches" height="48"></div>
            <h3>Branches</h3>
            <p>Gérer les 7 branches de l'association</p>
        </a>
    </div>

    <div class="section-title">Membres</div>
    <div class="grid">
        <a href="includes/membres_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/membres.png" alt="Membres" height="48"></div>
            <h3>Membres</h3>
            <p>Liste et gestion de tous les membres</p>
        </a>
        <a href="includes/parrainage_form.html" class="module-card">
            <div class="module-icon"><img src="assets/images/parrainage.png" alt="Parrainage" height="48"></div>
            <h3>Parrainage</h3>
            <p>Liens mpanohana → beazina</p>
        </a>
    </div>

    <div class="section-title">Activités</div>
    <div class="grid">
        <a href="includes/hetsika_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/hetsika.png" alt="Hetsika" height="48"></div>
            <h3>Hetsika</h3>
            <p>Activités et événements</p>
        </a>
        <a href="includes/presence_form.html" class="module-card">
            <div class="module-icon"><img src="assets/images/presence.png" alt="Présences" height="48"></div>
            <h3>Présences</h3>
            <p>Enregistrer les branches présentes</p>
        </a>
        <a href="includes/recompense_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/recompense.png" alt="Récompenses" height="48"></div>
            <h3>Récompenses</h3>
            <p>Coupes et prix remportés</p>
        </a>
    </div>

    <div class="section-title">Progression</div>
    <div class="grid">
        <a href="includes/ambaratonga_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/grades.png" alt="Grades" height="48"></div>
            <h3>Grades</h3>
            <p>Ambaratonga — niveaux des beazina</p>
        </a>
        <a href="includes/talenta_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/talents.png" alt="Talents" height="48"></div>
            <h3>Talents</h3>
            <p>Talenta — compétences des beazina</p>
        </a>
    </div>

    <div class="section-title">Matériel & Finances</div>
    <div class="grid">
        <a href="includes/materiel_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/materiel.png" alt="Matériel" height="48"></div>
            <h3>Matériel</h3>
            <p>Inventaire et suivi des équipements</p>
        </a>
        <a href="includes/emprunt_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/emprunt.png" alt="Emprunts" height="48"></div>
            <h3>Emprunts</h3>
            <p>Suivi des emprunts de matériel</p>
        </a>
        <a href="includes/cotisation_liste.html" class="module-card">
            <div class="module-icon"><img src="assets/images/cotisation.png" alt="Cotisations" height="48"></div>
            <h3>Cotisations</h3>
            <p>Versements et suivi financier</p>
        </a>
        <a href="includes/dashboard.html" class="module-card">
            <div class="module-icon"><img src="assets/images/stats.png" alt="Statistiques" height="48"></div>
            <h3>Statistiques</h3>
            <p>Vue d'ensemble de l'association</p>
        </a>
    </div>

</div>

<footer>
    Association Scoute &mdash; Fivondronana Antananarivo &mdash; <?= date('Y') ?>
</footer>

</body>
</html>
