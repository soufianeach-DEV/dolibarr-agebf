# Module AgeBF — Dolibarr ERP CRM

> Module custom développé dans le cadre d'un stage chez **Bruxelles Formation**.

**Auteur :** Soufiane Achraa — Stage 2026 — TECHGEST ICCBXL  
**Version :** 3.2  
**Compatibilité :** Dolibarr 19+, PHP 8.0+

---

## Objectif

Calculer automatiquement l'âge des contacts **Fils / Fille** au 1er janvier de l'année en cours, déterminer lesquels sont invités à la **fête des enfants** de Bruxelles Formation, et suivre les **documents** (composition de ménage) fournis par chaque Tiers.

**Règle métier :** un enfant est invité s'il a **strictement moins de 16 ans** au 1er janvier (les 15 ans sont donc inclus).

---

## Modèle de données

Le module repose sur le modèle natif Dolibarr :

- **1 Tiers par employé adulte** (ex. : "Dupont Marie") — contient les champs `fete_enfants` et `nb_enfants_invites`
- **Contacts Fils/Fille** liés au Tiers du parent via le champ standard `fk_soc`

---

## Fonctionnement

1. Le cron calcule chaque jour l'âge de **tous les contacts** ayant une date de naissance
2. Les contacts avec `poste = 'Fils'` ou `poste = 'Fille'` et âge < 16 reçoivent la case **"Fête des enfants" cochée**
3. Le **Tiers** parent reçoit automatiquement :
   - `fete_enfants = 1` si au moins un de ses enfants qualifie
   - `nb_enfants_invites = N` avec le nombre d'enfants qualifiés
4. La page **Documents** liste les Tiers et détecte la présence d'une composition de ménage

---

## Champs créés automatiquement à l'activation

| Champ | Table | Type | Description |
|---|---|---|---|
| `age_1jan` | contacts | Int | Âge calculé au 1er janvier |
| `fete_enfants` | contacts | Boolean | ✔ si l'enfant (Fils/Fille) a < 16 ans |
| `fk_parent` | contacts | Int | ID contact parent (champ informatif) |
| `fete_enfants` | tiers | Boolean | ✔ si au moins un enfant du Tiers est invité |
| `nb_enfants_invites` | tiers | Int | Nombre d'enfants < 16 ans liés au Tiers |

---

## Structure du module

```
agebf/
├── agebfindex.php                    # Fete des enfants : liste, stats, filtre, export CSV
├── agebf_documents.php               # Documents : composition de menage par Tiers
├── core/
│   └── modules/
│       └── modAgeBF.class.php        # Descripteur v3.2 (cron, extrafields, menus)
├── class/
│   └── agebf.class.php               # Logique de calcul + propagation vers Tiers
├── admin/
│   ├── setup.php                     # Page de configuration
│   └── about.php                     # A propos
├── sql/
│   └── dolibarr_allversions.sql      # Script SQL charge a l'activation
├── langs/
│   └── en_US/
│       └── agebf.lang                # Traductions
└── lib/
    └── agebf.lib.php                 # Fonctions utilitaires
```

---

## Installation

### Méthode 1 — ZIP via l'interface Dolibarr (recommandée)

1. Télécharger **`module_agebf-3.2.zip`** depuis la [page Releases](https://github.com/soufianeach-DEV/dolibarr-agebf/releases)
2. Dans Dolibarr : **Configuration → Modules/Applications**
3. Cliquer sur l'onglet **"Déployer/Installer un module externe"**
4. Choisir le fichier ZIP → **Envoyer le fichier**
5. Le module apparaît dans la liste → **Activer**

> Le fichier ZIP respecte la convention Dolibarr : `module_*-x.y*.zip`

### Méthode 2 — Installation manuelle

```
htdocs/custom/agebf/     ← coller ici le dossier du repo
```

Puis activer via **Configuration → Modules/Applications → Rechercher "AgeBF" → Activer**.

### À l'activation

Les 5 champs extrafields sont créés automatiquement, ainsi que le cron dans **Outils → Travaux planifiés** et les menus **Helpy**.

> **Mise à jour :** désactivez puis réactivez le module pour appliquer la nouvelle version (menus, champs).

---

## Utilisation

### Menu Helpy

Après activation, le menu **Helpy** apparaît dans la barre du haut avec deux sous-pages :

| Page | Accès | Description |
|---|---|---|
| Fête des enfants | Helpy → Fête des enfants | Liste des enfants invités, stats, export CSV |
| Documents | Helpy → Documents Tiers | Suivi des compositions de ménage par Tiers |

### Page Fête des enfants

| Élément | Description |
|---|---|
| Bandeau stats | Total enfants, nombre < 16 ans, cases cochées |
| Filtre | "Invités seulement" / "Tous les enfants" |
| Tableau | Tiers (parent), Nom, Prénom, Genre, Âge, Case fête — **tri cliquable** |
| Export CSV | Télécharge la liste en `.csv` (UTF-8 + BOM Excel, nom dynamique par année) |

### Page Documents Tiers

| Élément | Description |
|---|---|
| Bandeau stats | Total Tiers / Composition fournie / Composition manquante / Aucun document |
| Filtre | 3 boutons : **Tous** / **Avec composition** / **Sans composition** |
| Tableau | Tiers, nb documents, statut composition, lien fiche |
| Indicateurs | **Fournie** (vert) / **Manquante** (orange) / **Aucun document** (rouge) |
| Bouton Voir | Ouvre le fichier dans un popup modal (80 % de l'écran) |
| Renommage admin | Sur les lignes "Manquante", un admin peut renommer le fichier inline |
| Ajout document | Sur les Tiers sans aucun fichier : formulaire d'upload intégré dans le tableau |
| Détection | Fichier contenant "composition", "compostion" ou "ménage" dans son nom |
| Sync Dolibarr | Tout ajout / renommage met à jour `llx_ecm_files` (onglet Documents natif) |

### Automatisation quotidienne

**Windows — Task Scheduler**

```bat
schtasks /Create /TN "DolibarrAgeBF" /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\dolibarr\htdocs\cron\run.php\"" /SC DAILY /ST 00:00
```

**Linux / Serveur**

```bash
0 0 * * * php /var/www/html/dolibarr/htdocs/cron/run.php >> /var/log/dolibarr_cron.log 2>&1
```

---

## Licence

- Code source : **GPLv3**
- Documentation : **GFDL**
