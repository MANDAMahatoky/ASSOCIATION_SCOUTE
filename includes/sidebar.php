<?php
// includes/sidebar.php
// Détermine la page active pour appliquer la classe "active" au bon lien.
$page_actuelle = basename($_SERVER['PHP_SELF']);
function nav_active($fichiers, $page_actuelle) {
    return in_array($page_actuelle, (array) $fichiers) ? 'active' : '';
}
?>
<div class="sidebar" id="sidebar">
    <nav class="sidebar-nav">

        <div class="sidebar-section">Général</div>
        <a href="index.php" class="sidebar-link <?= nav_active('index.php', $page_actuelle) ?>">
            <span class="sidebar-link-main"><span class="sidebar-icon">🏠</span>Tableau de bord</span>
            <span class="chevron">›</span>
        </a>

        <div class="sidebar-section">Organisation</div>
        <a href="modules/vondrona/liste.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">🏕️</span>Vondrona</span>
            <span class="chevron">›</span>
        </a>
        <a href="modules/branches/liste.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">🌿</span>Branches</span>
            <span class="chevron">›</span>
        </a>

        <div class="sidebar-section">Membres</div>
        <a href="modules/membres/liste.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">👥</span>Membres</span>
            <span class="chevron">›</span>
        </a>
        <a href="modules/parrainage/liste.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">🤝</span>Parrainage</span>
            <span class="chevron">›</span>
        </a>

        <div class="sidebar-section">Activités</div>
        <a href="modules/hetsika/liste.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">📅</span>Hetsika</span>
            <span class="chevron">›</span>
        </a>
        <a href="modules/hetsika/presences.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">✅</span>Présences</span>
            <span class="chevron">›</span>
        </a>
        <a href="modules/hetsika/recompenses.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">🏆</span>Récompenses</span>
            <span class="chevron">›</span>
        </a>

        <div class="sidebar-section">Progression</div>
        <a href="modules/progression/ambaratonga.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">⭐</span>Grades</span>
            <span class="chevron">›</span>
        </a>
        <a href="modules/progression/talenta.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">🎯</span>Talents</span>
            <span class="chevron">›</span>
        </a>

        <div class="sidebar-section">Matériel &amp; Finances</div>
        <a href="modules/materiel/liste.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">🎒</span>Matériel</span>
            <span class="chevron">›</span>
        </a>
        <a href="modules/materiel/emprunts.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">📦</span>Emprunts</span>
            <span class="chevron">›</span>
        </a>
        <a href="modules/cotisations/liste.php" class="sidebar-link">
            <span class="sidebar-link-main"><span class="sidebar-icon">💰</span>Cotisations</span>
            <span class="chevron">›</span>
        </a>

    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-logout">↩ Déconnexion</a>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
