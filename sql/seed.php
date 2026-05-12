<?php
/**
 * Script de seed — 58 contacts (27 parents + 31 enfants)
 * Exécuter UNE SEULE FOIS via : http://localhost/dolibarr/htdocs/custom/agebf/sql/seed.php
 */

// Bootstrap Dolibarr
$res = 0;
if (!$res && file_exists("../../../../main.inc.php"))  { $res = @include "../../../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))     { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Bootstrap Dolibarr impossible."); }

$db->begin();

// ── Nettoyage ────────────────────────────────────────────────────────────────
$bf_old = $db->getRow("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom='Bruxelles Formation' AND entity=1");
if ($bf_old) {
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."socpeople_extrafields WHERE fk_object IN (SELECT rowid FROM ".MAIN_DB_PREFIX."socpeople WHERE fk_soc=".(int)$bf_old->rowid.")");
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."socpeople WHERE fk_soc=".(int)$bf_old->rowid);
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."societe WHERE rowid=".(int)$bf_old->rowid);
}

// ── Tiers ────────────────────────────────────────────────────────────────────
$db->query("INSERT INTO ".MAIN_DB_PREFIX."societe (nom,entity,datec,fk_user_creat,status,client,address,zip,town,phone,email)
VALUES ('Bruxelles Formation',1,NOW(),1,1,0,'Boulevard Anspach 65','1000','Bruxelles','+32 2 371 74 00','info@bruxellesformation.be')");
$bf = $db->last_insert_id(MAIN_DB_PREFIX."societe");
echo "✔ Tiers créé — ID=$bf<br>";

// ── Données contacts ─────────────────────────────────────────────────────────
// Format : [lastname, firstname, poste, birthday]
$contacts = [
    // Famille Dupont
    ['Dupont',    'Jean',          'Formateur',                          '1978-03-15'],
    ['Dupont',    'Marie',         'Gestionnaire pédagogique',           '1980-06-22'],
    ['Dupont',    'Thomas',        'Fils',                               '2012-03-15'], // 13 ✅
    ['Dupont',    'Emma',          'Fille',                              '2009-12-07'], // 16 ❌
    // Famille Martin
    ['Martin',    'Pierre',        'Conseiller pédagogique',             '1975-09-10'],
    ['Martin',    'Sophie',        'Chargée de communication',           '1977-11-23'],
    ['Martin',    'Lucas',         'Fils',                               '2015-01-08'], // 10 ✅
    ['Martin',    'Chloé',         'Fille',                              '2010-11-30'], // 15 ✅
    // Famille Lecomte
    ['Lecomte',   'David',         'Responsable RH',                     '1982-02-04'],
    ['Lecomte',   'Isabelle',      'Coordinatrice pédagogique',          '1984-07-19'],
    ['Lecomte',   'Nathan',        'Fils',                               '2013-06-05'], // 12 ✅
    ['Lecomte',   'Zoé',           'Fille',                              '2016-02-14'], //  9 ✅
    // Famille Bernard
    ['Bernard',   'Marc',          'Technicien informatique',            '1979-12-01'],
    ['Bernard',   'Claire',        'Gestionnaire administrative',        '1981-03-30'],
    ['Bernard',   'Hugo',          'Fils',                               '2011-09-20'], // 14 ✅
    ['Bernard',   'Léa',           'Fille',                              '2008-12-03'], // 17 ❌
    // Famille Dubois
    ['Dubois',    'Alain',         'Chargé de projet',                   '1976-05-17'],
    ['Dubois',    'Nathalie',      'Conseillère en orientation',         '1978-08-28'],
    ['Dubois',    'Mathis',        'Fils',                               '2014-04-17'], // 11 ✅
    ['Dubois',    'Camille',       'Fille',                              '2011-12-15'], // 14 ✅
    // Famille Laurent
    ['Laurent',   'François',      'Directeur de formation',             '1971-08-12'],
    ['Laurent',   'Anne',          'Secrétaire de direction',            '1973-04-05'],
    ['Laurent',   'Julien',        'Fils',                               '2019-03-22'], //  6 ✅
    ['Laurent',   'Sarah',         'Fille',                              '2009-07-14'], // 16 ❌
    ['Laurent',   'Clara',         'Fille',                              '2014-08-30'], // 11 ✅
    // Famille Simon
    ['Simon',     'Nicolas',       'Formateur',                          '1983-01-29'],
    ['Simon',     'Céline',        'Assistante RH',                      '1985-10-14'],
    ['Simon',     'Théo',          'Fils',                               '2021-05-10'], //  4 ✅
    ['Simon',     'Lola',          'Fille',                              '2013-09-18'], // 12 ✅
    // Famille Rousseau
    ['Rousseau',  'Patrick',       'Chef de projet digital',             '1977-06-30'],
    ['Rousseau',  'Valérie',       'Comptable',                          '1979-02-18'],
    ['Rousseau',  'Antoine',       'Fils',                               '2008-06-25'], // 17 ❌
    ['Rousseau',  'Manon',         'Fille',                              '2018-01-12'], //  7 ✅
    // Famille Moreau
    ['Moreau',    'Bruno',         'Formateur senior',                   '1974-11-07'],
    ['Moreau',    'Christine',     'Documentaliste',                     '1976-09-25'],
    ['Moreau',    'Kévin',         'Fils',                               '2020-11-03'], //  5 ✅
    ['Moreau',    'Julie',         'Fille',                              '2010-08-22'], // 15 ✅
    ['Moreau',    'Alexis',        'Fils',                               '2015-04-07'], // 10 ✅
    // Famille Petit
    ['Petit',     'Stéphane',      'Analyste pédagogique',               '1980-07-03'],
    ['Petit',     'Sandrine',      'Coordinatrice projets',              '1982-12-11'],
    ['Petit',     'Clément',       'Fils',                               '2023-02-14'], //  2 ✅
    ['Petit',     'Inès',          'Fille',                              '2012-07-30'], // 13 ✅
    ['Petit',     'Tom',           'Fils',                               '2010-05-19'], // 15 ✅
    // Famille Gallet
    ['Gallet',    'Jean-Marc',     'Directeur adjoint',                  '1969-05-21'],
    ['Gallet',    'Élise',         'Chargée des relations entreprises',  '1972-09-09'],
    ['Gallet',    'Raphaël',       'Fils',                               '2017-09-05'], //  8 ✅
    ['Gallet',    'Sofia',         'Fille',                              '2014-11-20'], // 11 ✅
    // Famille Durand
    ['Durand',    'Michel',        'Ingénieur pédagogique',              '1981-04-16'],
    ['Durand',    'Isabelle',      'Assistante de direction',            '1983-08-02'],
    ['Durand',    'Noé',           'Fils',                               '2016-06-28'], //  9 ✅
    ['Durand',    'Alice',         'Fille',                              '2022-03-15'], //  3 ✅
    // Parent isolé — Renard
    ['Renard',    'Catherine',     'Formatrice senior',                  '1979-01-30'],
    ['Renard',    'Pierre-Antoine','Fils',                               '2019-07-04'], //  6 ✅
    // Parent isolé — Bonnet (2 enfants)
    ['Bonnet',    'André',         "Chargé d'insertion professionnelle", '1977-03-22'],
    ['Bonnet',    'Léonie',        'Fille',                              '2021-12-01'], //  4 ✅
    ['Bonnet',    'Maxime',        'Fils',                               '2012-08-15'], // 13 ✅
    // Parent isolé — Gros
    ['Gros',      'Marie-France',  'Chargée de communication',           '1983-06-14'],
    ['Gros',      'Anaïs',         'Fille',                              '2018-06-20'], //  7 ✅
];

$nb = 0;
foreach ($contacts as $c) {
    $sql  = "INSERT INTO ".MAIN_DB_PREFIX."socpeople";
    $sql .= " (fk_soc, lastname, firstname, poste, birthday, entity, datec, fk_user_creat, statut)";
    $sql .= " VALUES (";
    $sql .= (int)$bf . ",";
    $sql .= "'" . $db->escape($c[0]) . "',";
    $sql .= "'" . $db->escape($c[1]) . "',";
    $sql .= "'" . $db->escape($c[2]) . "',";
    $sql .= "'" . $db->escape($c[3]) . "',";
    $sql .= "1, NOW(), 1, 1)";
    if ($db->query($sql)) {
        $nb++;
    } else {
        echo "⚠ Erreur contact {$c[1]} {$c[0]} : " . $db->lasterror() . "<br>";
    }
}
echo "✔ <b>$nb contacts insérés</b><br>";

// ── Liens fk_parent ──────────────────────────────────────────────────────────
$sql_children = "SELECT rowid, lastname FROM ".MAIN_DB_PREFIX."socpeople WHERE fk_soc=".(int)$bf." AND poste IN ('Fils','Fille')";
$res_ch = $db->query($sql_children);
$nb_links = 0;
while ($ch = $db->fetch_object($res_ch)) {
    // Trouve le premier parent adulte du même nom
    $sql_par  = "SELECT rowid FROM ".MAIN_DB_PREFIX."socpeople";
    $sql_par .= " WHERE fk_soc=".(int)$bf;
    $sql_par .= " AND lastname='" . $db->escape($ch->lastname) . "'";
    $sql_par .= " AND poste NOT IN ('Fils','Fille')";
    $sql_par .= " ORDER BY rowid LIMIT 1";
    $res_par = $db->query($sql_par);
    $par = $db->fetch_object($res_par);
    if ($par) {
        // Upsert extrafield fk_parent
        $ex = $db->getRow("SELECT rowid FROM ".MAIN_DB_PREFIX."socpeople_extrafields WHERE fk_object=".(int)$ch->rowid);
        if ($ex) {
            $db->query("UPDATE ".MAIN_DB_PREFIX."socpeople_extrafields SET fk_parent=".(int)$par->rowid." WHERE fk_object=".(int)$ch->rowid);
        } else {
            $db->query("INSERT INTO ".MAIN_DB_PREFIX."socpeople_extrafields (fk_object,fk_parent) VALUES (".(int)$ch->rowid.",".(int)$par->rowid.")");
        }
        $nb_links++;
    }
}
echo "✔ <b>$nb_links liens parent-enfant créés</b><br>";

$db->commit();

// ── Résumé ───────────────────────────────────────────────────────────────────
$stats = $db->getRow("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN poste IN ('Fils','Fille') THEN 1 ELSE 0 END) AS enfants,
    SUM(CASE WHEN poste NOT IN ('Fils','Fille') THEN 1 ELSE 0 END) AS parents
FROM ".MAIN_DB_PREFIX."socpeople WHERE fk_soc=".(int)$bf);

echo "<br><b>── Résumé ──</b><br>";
echo "Total : <b>{$stats->total}</b> contacts<br>";
echo "Parents (employés) : <b>{$stats->parents}</b><br>";
echo "Fils / Filles : <b>{$stats->enfants}</b><br>";
echo "<br>✅ <a href='".DOL_URL_ROOT."/custom/agebf/agebfindex.php'>Voir la liste AgeBF</a>";
echo " &nbsp;|&nbsp; ";
echo "<a href='".DOL_URL_ROOT."/cron/list.php'>Lancer le cron</a>";
