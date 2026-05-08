# ASSOCIATION_SCOUTE
Gestion d'association scoute locale 
#  Gestion Association Scoute Locale

> Application web de gestion d'une association scoute (Fivondronana), développée en **PHP**, **HTML** et **CSS**.

---

##  Description

Ce projet est une application de gestion complète pour une association scoute locale (Fivondronana).  
Elle permet de gérer les membres, les branches, les activités (Hetsika), les récompenses, le matériel et les cotisations selon la hiérarchie organisationnelle définie.

---

## Structure de l'Organisation

```
Fivondronana
├── Vondrona : Tily
│   ├── Branche : Lovitao
│   ├── Branche : Tily
│   ├── Branche : Mpiandalana
│   └── Branche : Mpitarika
└── Vondrona : Mpanazava
    ├── Branche : Voronkely
    ├── Branche : Mpanazava
    └── Branche : Afo
```

---

## 👥 Types de Membres

| Type         | Description                                      | Rattachement        |
|--------------|--------------------------------------------------|---------------------|
| `beazina`    | Membre scout actif (jeune)                       | Branche             |
| `mpanabe`    | Responsable / animateur                          | Branche             |
| `kp`         | Chef de projet / responsable senior              | Fivondronana direct |
| `mpanohana`  | Soutien / parrain                                | Fivondronana direct |
| `ray aman-dreny` | Parents / tuteurs                           | Fivondronana direct |

---

## ✨ Fonctionnalités

### Gestion des Membres
- Inscription et fiche de chaque membre (nom, prénom, adresse, type)
- Rattachement à une branche (pour `beazina` et `mpanabe`)
- Système de parrainage : un `mpanohana` peut parrainer un ou plusieurs `beazina`

### Progression des Beazina
- Suivi des **Ambaratonga** (grades/niveaux) avec historique daté
- Gestion des **Talenta** (talents/compétences multiples)
- Validation par un `mpanabe` ou `kp` uniquement

### Activités — Hetsika
- Création et gestion des activités par branche
- Enregistrement des présences (branches participantes)
- Attribution de **Récompenses** (coupes, prix) — individuelles ou collectives

### Matériel
- Inventaire du matériel par branche ou commun au Fivondronana
- Suivi de l'état : `neuf`, `usagé`, `à réparer`
- Gestion des emprunts avec désignation d'un membre responsable

### Cotisations
- Saisie des versements par membre
- Types : `régulière` (périodique) ou `non régulière` (ponctuelle)
- Montants différenciés selon le type de membre

---

## 🗂️ Structure du Projet

```
gestion-association-scoute/
│
├── index.php                   # Page d'accueil / tableau de bord
│
├── config/
│   └── db.php                  # Connexion à la base de données
│
├── includes/
│   ├── header.php              # En-tête commune
│   ├── footer.php              # Pied de page commun
│   └── navbar.php              # Barre de navigation
│
├── modules/
│   ├── membres/
│   │   ├── liste.php           # Liste des membres
│   │   ├── ajouter.php         # Formulaire d'ajout
│   │   ├── modifier.php        # Formulaire de modification
│   │   └── supprimer.php       # Suppression
│   │
│   ├── branches/
│   │   ├── liste.php
│   │   ├── ajouter.php
│   │   └── modifier.php
│   │
│   ├── hetsika/
│   │   ├── liste.php           # Liste des activités
│   │   ├── ajouter.php
│   │   ├── presences.php       # Gestion des présences
│   │   └── recompenses.php     # Gestion des récompenses
│   │
│   ├── progression/
│   │   ├── ambaratonga.php     # Grades des beazina
│   │   └── talenta.php         # Talents des beazina
│   │
│   ├── materiel/
│   │   ├── liste.php
│   │   ├── ajouter.php
│   │   └── emprunts.php        # Suivi des emprunts
│   │
│   └── cotisations/
│       ├── liste.php
│       └── ajouter.php
│
├── assets/
│   ├── css/
│   │   ├── style.css           # Styles globaux
│   │   ├── navbar.css          # Styles navigation
│   │   └── forms.css           # Styles formulaires
│   │
│   ├── js/
│   │   └── main.js             # Scripts JavaScript
│   │
│   └── images/
│       └── logo.png            # Logo de l'association
│
└── database/
    └── association_scoute.sql  # Script de création de la base de données
```

---

## 🗄️ Base de Données

Le modèle conceptuel (MCD) comprend les entités suivantes :

| Table           | Description                                      |
|-----------------|--------------------------------------------------|
| `fivondronana`  | Organisation principale                          |
| `vondrona`      | Groupes (Tily / Mpanazava)                       |
| `branche`       | Branches rattachées à un Vondrona                |
| `membre`        | Tous les membres (tous types)                    |
| `hetsika`       | Activités organisées                             |
| `presence`      | Présence des branches aux activités              |
| `recompense`    | Récompenses remportées                           |
| `ambaratonga`   | Grades/niveaux (pour beazina)                    |
| `talenta`       | Talents/compétences                              |
| `materiel`      | Inventaire du matériel                           |
| `cotisation`    | Versements des membres                           |

---

## ⚙️ Installation

### Prérequis

- **PHP** >= 7.4
- **MySQL** >= 5.7 (ou MariaDB)
- **Serveur web** : Apache (XAMPP / WAMP / Laragon) ou Nginx

### Étapes

**1. Cloner ou télécharger le projet**
```bash
git clone https://github.com/votre-utilisateur/gestion-association-scoute.git
```

**2. Placer dans le répertoire web**
```bash
# XAMPP
cp -r gestion-association-scoute/ C:/xampp/htdocs/

# WAMP
cp -r gestion-association-scoute/ C:/wamp64/www/
```

**3. Créer la base de données**
```sql
CREATE DATABASE association_scoute CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**4. Importer le script SQL**
```bash
mysql -u root -p association_scoute < database/association_scoute.sql
```

**5. Configurer la connexion**

Modifier le fichier `config/db.php` :
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // votre utilisateur MySQL
define('DB_PASS', '');            // votre mot de passe
define('DB_NAME', 'association_scoute');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>
```

**6. Accéder à l'application**
```
http://localhost/gestion-association-scoute/
```

---

##  Règles Métier Importantes

- Seul un membre de type `mpanabe` ou `kp` peut **valider** un Ambaratonga ou un Talenta.
- Un `beazina` ne peut avoir qu'**un seul Ambaratonga actif** à la fois (avec historique daté).
- Lors de l'emprunt de matériel commun, un **membre responsable** doit être désigné.
- Les membres `kp`, `mpanohana` et `ray aman-dreny` sont rattachés **directement au Fivondronana** (pas à une branche).

---

## Auteur

**MANDA Mahatoky**  
Étudiant L3 — MISA/SI  

---

##  Licence

Projet académique — usage éducatif uniquement.
