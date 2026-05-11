# Module AgeBF — Dolibarr ERP CRM

Module custom développé dans le cadre d'un stage chez **Bruxelles Formation**.

**Auteur :** Soufiane Achraa — Stage 2026 — TECHGEST ICCBXL  
**Version :** 1.0  
**Compatibilité :** Dolibarr 19+, PHP 8.0+

---

## Objectif

Calculer automatiquement l'âge des contacts de type **Enfant** au 1er janvier de l'année en cours, et stocker le résultat dans le champ `age_1jan`.

Les enfants ayant moins de 15 ans au 1er janvier sont considérés comme **invités** à la fête des enfants de Bruxelles Formation.

---

## Fonctionnement

1. Le module crée automatiquement un champ `age_1jan` (extrafield) sur les contacts
2. Une tâche planifiée (cron) calcule chaque jour l'âge de tous les contacts avec `poste = 'Enfant'` et une date de naissance renseignée
3. Le résultat est stocké dans `age_1jan` — un enfant avec `age_1jan < 15` est invité

**Règle :** `age_1jan = age au 1er janvier de l'année en cours`

---

## Structure du module

```
agebf/
├── core/modules/modAgeBF.class.php   # Descripteur du module (cron, extrafield, menu)
├── class/agebf.class.php             # Logique de calcul des âges
├── admin/setup.php                   # Page de configuration
├── admin/about.php                   # Page À propos
├── sql/
│   ├── dolibarr_allversions.sql      # Script SQL chargé à l'activation (vide)
│   ├── insert_data_test.sql          # Données de test (exécuter via phpMyAdmin)
│   ├── import_tiers.csv              # CSV import Tiers (Bruxelles Formation)
│   └── import_contacts.csv          # CSV import contacts
├── langs/en_US/agebf.lang            # Traductions
└── data_test.sql                     # Ancien fichier de test (ignoré)
```

---

## Installation

### Prérequis
- Dolibarr 19+ installé et configuré
- PHP 8.0+
- Module **Tiers** et **Contacts** activés dans Dolibarr

### Étapes

1. Copier le dossier `agebf/` dans `htdocs/custom/`
2. Dans Dolibarr : **Accueil → Configuration → Modules**
3. Activer le module **AgeBF**
4. Le champ `age_1jan` est créé automatiquement sur les contacts
5. Le cron apparaît dans **Outils → Travaux planifiés**

---

## Données de test

Le fichier [`sql/insert_data_test.sql`](sql/insert_data_test.sql) insère :
- 1 Tiers : **Bruxelles Formation**
- 20 contacts : 10 collaborateurs (avec postes réels BF) + 10 enfants avec dates de naissance

**Pour charger les données :**
1. Ouvrir **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Sélectionner la base `dolibarr`
3. Onglet **SQL** → coller le contenu du fichier → Exécuter

**Résultat attendu après exécution du cron (référence 1er janvier 2026) :**

| Enfant | Âge | Invité |
|--------|-----|--------|
| Thomas Dupont (2012) | 13 ans | ✅ |
| Lucas Martin (2015) | 10 ans | ✅ |
| Nathan Lecomte (2013) | 12 ans | ✅ |
| Zoé Lecomte (2016) | 9 ans | ✅ |
| Hugo Bernard (2011) | 14 ans | ✅ |
| Mathis Dubois (2014) | 11 ans | ✅ |
| Camille Dubois (2011) | 14 ans | ✅ |
| Emma Dupont (2009) | 16 ans | ❌ |
| Chloé Martin (2010) | 15 ans | ❌ |
| Léa Bernard (2008) | 17 ans | ❌ |

---

## Exécution du cron

### Manuellement (tests)

1. Aller sur `http://localhost/dolibarr/htdocs/cron/list.php`
2. Cliquer sur **Exécuter** à côté du cron *"Calcul âge contacts au 1er janvier"*
3. Vérifier les contacts : le champ `age_1jan` doit être mis à jour

### Automatiquement — Windows Task Scheduler

Pour automatiser l'exécution quotidienne sur Windows :

1. Ouvrir le **Planificateur de tâches** Windows (`taskschd.msc`)
2. Cliquer sur **Créer une tâche de base...**
3. Remplir :
   - **Nom :** AgeBF — Calcul âges Dolibarr
   - **Déclencheur :** Tous les jours à **00h05**
   - **Action :** Démarrer un programme
   - **Programme :** `C:\xampp\php\php.exe`
   - **Arguments :** `C:\xampp\htdocs\dolibarr\htdocs\cron\run.php`
4. Cocher *"Ouvrir la boîte de dialogue Propriétés..."* → onglet **Paramètres** → cocher *"Exécuter la tâche dès que possible si un démarrage planifié est manqué"*
5. Cliquer **Terminer**

> En production sur un serveur Linux, équivalent : `5 0 * * * php /var/www/dolibarr/htdocs/cron/run.php`

---

## Licences

- Code : GPLv3
- Documentation : GFDL
