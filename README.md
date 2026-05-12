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

## Fonctionnement

1. Le cron calcule chaque jour l'âge de **tous les contacts** ayant une date de naissance
2. Les contacts avec `poste = 'Fils'` ou `poste = 'Fille'` et âge < 16 reçoivent automatiquement la case **"Fête des enfants" cochée** (`fete_enfants = 1`)
3. Si un parent a **au moins un enfant** qui qualifie (via le lien `fk_parent`), sa propre case est aussi cochée
4. Le **Tiers** lié reçoit également la case cochée si au moins un de ses contacts qualifie
5. La page principale du module affiche la liste avec filtre et **export CSV**

---

## Champs créés automatiquement à l'activation

| Champ | Table | Type | Description |
|---|---|---|---|
| `age_1jan` | contacts | Int | Âge calculé au 1er janvier |
| `fete_enfants` | contacts | Boolean | ✔ si l'enfant est invité ou si parent d'un invité |
| `fk_parent` | contacts | Int | ID du contact parent (à renseigner manuellement) |
| `fete_enfants` | tiers | Boolean | ✔ si au moins un enfant du tiers est invité |

---

## Structure du module

```
agebf/
├── agebfindex.php                    # Page principale : liste, stats, filtre, export CSV
├── core/
│   └── modules/
│       └── modAgeBF.class.php        # Descripteur v2.0 (cron, extrafields, pas de menu top)
├── class/
│   └── agebf.class.php               # Logique de calcul + propagation fete_enfants
├── admin/
│   ├── setup.php                     # Page de configuration
│   └── about.php                     # À propos
├── sql/
│   ├── dolibarr_allversions.sql      # Script SQL chargé à l'activation
│   ├── insert_data_test.sql          # Données de test (20 contacts, 10 enfants)
│   ├── import_tiers.csv              # CSV import Tiers
│   └── import_contacts.csv          # CSV import contacts
├── langs/
│   └── en_US/
│       └── agebf.lang                # Traductions
└── lib/
    └── agebf.lib.php                 # Fonctions utilitaires
```

---

## Installation

### Prérequis

- Dolibarr 19+ fonctionnel
- PHP 8.0+
- Modules **Tiers** et **Contacts/Adresses** activés

### Étape 1 — Copier le module

```
htdocs/custom/agebf/     ← coller ici le dossier du repo
```

### Étape 2 — Activer le module

1. **Accueil → Configuration → Modules/Applications**
2. Rechercher **AgeBF** → **Activer**
3. Les 4 champs extrafields sont créés automatiquement
4. Le cron apparaît dans **Outils → Travaux planifiés**

> **Mise à jour depuis v1.0 :** désactivez puis réactivez le module pour créer les nouveaux champs `fete_enfants` et `fk_parent`.

### Étape 3 — Charger les données de test

Dans **phpMyAdmin**, onglet **SQL**, exécuter [`sql/insert_data_test.sql`](sql/insert_data_test.sql).

Crée : 1 Tiers (Bruxelles Formation) + 20 contacts (10 collaborateurs + 10 enfants avec liens parent).

### Étape 4 — Lier les enfants aux parents (installation manuelle)

Pour chaque contact **Fils/Fille**, renseigner manuellement le champ **"Parent (contact ID)"** avec le `rowid` du contact parent (affiché dans l'URL de la fiche contact).

> Les données de test configurent ce lien automatiquement via SQL.

---

## Utilisation

### Lancer le calcul

**Outils → Travaux planifiés** → cron **"Calcul âge contacts au 1er janvier"** → **Lancer maintenant**

### Voir les résultats

**Accueil → Configuration → Modules → AgeBF → ⚙ (roue dentée)** ou aller directement à :

```
http://localhost/dolibarr/htdocs/custom/agebf/agebfindex.php
```

### Page principale — fonctionnalités

| Élément | Description |
|---|---|
| Bandeau stats | Total enfants, nombre < 16 ans, cases cochées |
| Filtre | "Invités seulement" / "Tous les enfants" |
| Tableau | Nom, Prénom, Genre, Parent (lien), Tiers, Age, Case fête |
| Export CSV | Télécharge la liste filtrée en `.csv` (UTF-8 + BOM Excel) |
| Bouton Cron | Lien direct vers la page de gestion des tâches planifiées |

### Champ "Fête des enfants" sur les fiches

- **Fiche contact Fils/Fille** : case cochée si âge < 16
- **Fiche contact parent** : cochée si au moins un de ses enfants est invité
- **Fiche Tiers** : cochée si au moins un contact lié est invité

---

## Résultat attendu (données de test, 1er janvier 2026)

| Enfant | Genre | Date naissance | Âge | Invité |
|--------|-------|---------------|-----|--------|
| Lecomte Zoé | Fille | 2016-02-14 | 9 | ✔ |
| Martin Lucas | Fils | 2015-01-08 | 10 | ✔ |
| Lecomte Nathan | Fils | 2013-06-05 | 12 | ✔ |
| Dupont Thomas | Fils | 2012-03-15 | 13 | ✔ |
| Dubois Camille | Fille | 2011-12-15 | 14 | ✔ |
| Bernard Hugo | Fils | 2011-09-20 | 14 | ✔ |
| Dubois Mathis | Fils | 2014-04-17 | 11 | ✔ |
| **Martin Chloé** | Fille | 2010-11-30 | **15** | ✔ ← nouveau v2.0 |
| Dupont Emma | Fille | 2009-12-07 | 16 | ✘ |
| Bernard Léa | Fille | 2008-12-03 | 17 | ✘ |

---

## Automatisation quotidienne

### Windows — Task Scheduler

1. Ouvrir `taskschd.msc`
2. **Créer une tâche de base**
   - Déclencheur : tous les jours à **00h05**
   - Programme : `C:\xampp\php\php.exe`
   - Arguments : `C:\xampp\htdocs\dolibarr\htdocs\cron\run.php`

### Linux / Serveur

```bash
5 0 * * * php /var/www/html/dolibarr/htdocs/cron/run.php
```

---

## Changelog

### v2.0 (2026-05-12)
- ✅ Critère d'invitation : âge **< 16** (les 15 ans sont maintenant inclus)
- ✅ Poste renommé `'Fils'` / `'Fille'` (remplace `'Enfant'`)
- ✅ Calcul de l'âge étendu à **tous les contacts** (pas seulement les enfants)
- ✅ Nouveau champ `fete_enfants` (checkbox) sur les contacts ET les Tiers
- ✅ Propagation automatique : parent et Tiers cochés si au moins un enfant qualifie
- ✅ Nouveau champ `fk_parent` pour lier un enfant à son parent contact
- ✅ Page principale : filtre Invités/Tous, colonne Parent avec lien
- ✅ **Export CSV** de la liste des enfants invités (compatible Excel UTF-8)
- ✅ Suppression du menu de navigation principale (moins encombrant)

### v1.0 (2026-05-01)
- Calcul âge contacts `poste='Enfant'` au 1er janvier
- Affichage liste ✔/✘ dans la page AgeBF
- Cron quotidien automatisé

---

## Licence

- Code source : **GPLv3**
- Documentation : **GFDL**
