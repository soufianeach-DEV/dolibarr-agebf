# Module AgeBF — Dolibarr ERP CRM

> Module custom développé dans le cadre d'un stage chez **Bruxelles Formation**.

**Auteur :** Soufiane Achraa — Stage 2026 — TECHGEST ICCBXL  
**Version :** 2.0  
**Compatibilité :** Dolibarr 19+, PHP 8.0+

---

## Objectif

Calculer automatiquement l'âge des contacts **Fils / Fille** au 1er janvier de l'année en cours, et déterminer lesquels sont invités à la **fête des enfants** de Bruxelles Formation.

**Règle métier :** un enfant est invité s'il a **strictement moins de 16 ans** au 1er janvier (les 15 ans sont donc inclus).

---

## Modèle de données

Le module repose sur le modèle natif Dolibarr :

- **1 Tiers par employé adulte** (ex. : "Dupont Marie") — contient les champs `fete_enfants` et `nb_enfants_invites`
- **Contacts Fils/Fille** liés au Tiers du parent via le champ standard `fk_soc`

Ce modèle est plus fiable que d'utiliser un champ custom `fk_parent` car il exploite les relations natives de Dolibarr.

---

## Fonctionnement

1. Le cron calcule chaque jour l'âge de **tous les contacts** ayant une date de naissance
2. Les contacts avec `poste = 'Fils'` ou `poste = 'Fille'` et âge < 16 reçoivent la case **"Fête des enfants" cochée** (`fete_enfants = 1`)
3. Le **Tiers** parent reçoit automatiquement :
   - `fete_enfants = 1` si au moins un de ses enfants qualifie
   - `nb_enfants_invites = N` avec le nombre d'enfants qualifiés
4. La page principale du module affiche la liste avec filtre et **export CSV**

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
├── agebfindex.php                    # Page principale : liste, stats, filtre, export CSV
├── core/
│   └── modules/
│       └── modAgeBF.class.php        # Descripteur v2.0 (cron, extrafields)
├── class/
│   └── agebf.class.php               # Logique de calcul + propagation vers Tiers
├── admin/
│   ├── setup.php                     # Page de configuration
│   └── about.php                     # À propos
├── sql/
│   └── dolibarr_allversions.sql      # Script SQL chargé à l'activation
├── langs/
│   └── en_US/
│       └── agebf.lang                # Traductions
└── lib/
    └── agebf.lib.php                 # Fonctions utilitaires
```

---

## Installation

### Méthode 1 — ZIP via l'interface Dolibarr (recommandée)

1. Télécharger **`agebf-v2.0.zip`** depuis la [page Releases](https://github.com/soufianeach-DEV/dolibarr-agebf/releases)
2. Dans Dolibarr : **Configuration → Modules/Applications**
3. Cliquer sur l'onglet **"Déployer/Installer un module externe"**
4. Choisir le fichier ZIP → **Envoyer le fichier**
5. Le module apparaît dans la liste → **Activer**

> Le ZIP doit contenir un dossier `agebf/` à sa racine pour que Dolibarr le reconnaisse.

### Méthode 2 — Installation manuelle

```
htdocs/custom/agebf/     ← coller ici le dossier du repo
```

Puis activer via **Configuration → Modules/Applications → Rechercher "AgeBF" → Activer**.

### À l'activation

Les 5 champs extrafields sont créés automatiquement, ainsi que le cron dans **Outils → Travaux planifiés**.

> **Mise à jour depuis v1.0 :** désactivez puis réactivez le module pour créer les nouveaux champs.

---

## Utilisation

### Lancer le calcul manuellement

**Outils → Travaux planifiés** → **"Calcul âge contacts au 1er janvier"** → **Lancer maintenant**

### Voir les résultats

Aller à **AgeBF** dans le menu ou directement :

```
http://localhost/dolibarr/htdocs/custom/agebf/agebfindex.php
```

### Page principale — fonctionnalités

| Élément | Description |
|---|---|
| Bandeau stats | Total enfants, nombre < 16 ans, cases cochées |
| Filtre | "Invités seulement" / "Tous les enfants" |
| Tableau | Nom, Prénom, Genre, Parent (Tiers lié), Fonction, Âge, Case fête |
| Export CSV | Bouton en haut → télécharge la liste en `.csv` (UTF-8 + BOM Excel) |
| Bouton Cron | Lien direct vers la page de gestion des tâches planifiées |

### Champ "Fête des enfants" sur les fiches Tiers

Dans la liste des Tiers ou sur une fiche Tiers, les colonnes `fete_enfants` et `nb_enfants_invites` sont visibles dans les extrafields.

---

## Automatisation quotidienne

### Windows — Task Scheduler

```bat
schtasks /Create /TN "DolibarrAgeBF" /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\dolibarr\htdocs\cron\run.php\"" /SC DAILY /ST 00:00
```

Ou via l'interface `taskschd.msc` :
- Déclencheur : **tous les jours à 00:00**
- Programme : `C:\xampp\php\php.exe`
- Arguments : `C:\xampp\htdocs\dolibarr\htdocs\cron\run.php`

### Linux / Serveur

```bash
0 0 * * * php /var/www/html/dolibarr/htdocs/cron/run.php >> /var/log/dolibarr_cron.log 2>&1
```

---

## Résultat attendu (données de test, 1er janvier 2026)

| Enfant | Genre | Date naissance | Âge | Invité |
|--------|-------|---------------|-----|--------|
| Enfant A | Fille | 2011-03-15 | 14 | ✔ |
| Enfant B | Fils | 2012-07-20 | 13 | ✔ |
| Enfant C | Fille | 2010-11-30 | **15** | ✔ ← inclus v2.0 |
| Enfant D | Fils | 2009-12-07 | 16 | ✘ |

Les Tiers parents obtiennent automatiquement `fete_enfants = 1` et `nb_enfants_invites = N`.

---

## Licence

- Code source : **GPLv3**
- Documentation : **GFDL**
