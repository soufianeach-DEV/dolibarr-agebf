<?php
/* Copyright (C) 2026 Bruxelles Formation — Module AgeBF v2.0 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) { $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php"; }
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

$annee_ref = (int) date('Y');
$filtre    = GETPOST('filtre', 'aZ09');
if (!in_array($filtre, ['tous', 'invites'])) $filtre = 'invites';

// Tri cliquable
$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = GETPOST('sortorder', 'aZ09');
$allowed   = ['soc.nom', 'sp.lastname', 'sp.firstname', 'sp.poste', 'sp.birthday', 'ex.age_1jan', 'ex.fete_enfants'];
if (!in_array($sortfield, $allowed)) $sortfield = 'soc.nom';
if (!in_array(strtoupper($sortorder), ['ASC', 'DESC'])) $sortorder = 'ASC';

// Helper : génère le lien de tri pour un en-tête
function sort_link($label, $field, $sortfield, $sortorder, $url_base, $filtre) {
	$new_order = ($sortfield === $field && $sortorder === 'ASC') ? 'DESC' : 'ASC';
	$arrow = '';
	if ($sortfield === $field) $arrow = ($sortorder === 'ASC') ? ' ▲' : ' ▼';
	$url = $url_base . '?filtre=' . $filtre . '&sortfield=' . urlencode($field) . '&sortorder=' . $new_order;
	return '<a href="' . $url . '" style="color:inherit;text-decoration:none">' . $label . $arrow . '</a>';
}

// ─────────────────────────────────────────────────────────────────────────────
// EXPORT CSV — doit être exécuté AVANT tout output HTML
// ─────────────────────────────────────────────────────────────────────────────
if (GETPOST('export', 'int') == 1) {
	$sql_e  = "SELECT sp.lastname, sp.firstname, sp.poste, sp.birthday,";
	$sql_e .= " ex.age_1jan, ex.fete_enfants,";
	$sql_e .= " par.lastname as parent_nom, par.firstname as parent_prenom,";
	$sql_e .= " soc.nom as tiers";
	$sql_e .= " FROM " . MAIN_DB_PREFIX . "socpeople AS sp";
	$sql_e .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople_extrafields AS ex ON ex.fk_object = sp.rowid";
	$sql_e .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople_extrafields AS ex_p ON ex_p.fk_object = sp.rowid";
	$sql_e .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople AS par ON par.rowid = ex.fk_parent";
	$sql_e .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe AS soc ON soc.rowid = sp.fk_soc";
	$sql_e .= " WHERE sp.poste IN ('Fils', 'Fille')";
	$sql_e .= " AND (ex.fete_enfants = 1 OR ex.fete_enfants IS NULL)";
	$sql_e .= " ORDER BY soc.nom ASC, sp.lastname ASC";

	$res_e = $db->query($sql_e);

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="fete_enfants_' . $annee_ref . '.csv"');
	echo "\xEF\xBB\xBF";
	echo "Tiers;Nom;Prenom;Genre;Date de naissance;Age au 1er jan " . $annee_ref . ";Invite(e)\n";

	if ($res_e) {
		while ($row = $db->fetch_object($res_e)) {
			$birthday = !empty($row->birthday) ? dol_print_date($db->jdate($row->birthday), 'day') : '';
			$invite   = (isset($row->fete_enfants) && $row->fete_enfants == 1) ? 'Oui' : 'Non';
			echo implode(';', [
				dol_escape_htmltag($row->tiers),
				dol_escape_htmltag($row->lastname),
				dol_escape_htmltag($row->firstname),
				dol_escape_htmltag($row->poste),
				$birthday,
				(string)(int)$row->age_1jan,
				$invite,
			]) . "\n";
		}
		$db->free($res_e);
	}
	$db->close();
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PAGE NORMALE
// ─────────────────────────────────────────────────────────────────────────────
llxHeader("", "AgeBF — Fete des enfants " . $annee_ref, '', '', 0, 0, '', '', '', 'mod-agebf page-index');

$url_base = DOL_URL_ROOT . '/custom/agebf/agebfindex.php';

print load_fiche_titre("Fete des enfants " . $annee_ref, '', 'fa-child');

// Boutons action
print '<div class="tabsAction">';
print '<a class="butAction" href="' . $url_base . '?export=1&filtre=' . $filtre . '">&darr; Exporter en CSV</a>';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/cron/list.php">&#9881; Gerer le cron</a>';
print '</div>';

// Requete principale
$sql  = "SELECT sp.rowid, sp.lastname, sp.firstname, sp.poste, sp.birthday, sp.fk_soc,";
$sql .= " ex.age_1jan, ex.fete_enfants,";
$sql .= " soc.rowid AS tiers_id, soc.nom AS tiers_nom, soc.note_public AS tiers_fonction";
$sql .= " FROM " . MAIN_DB_PREFIX . "socpeople AS sp";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople_extrafields AS ex ON ex.fk_object = sp.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe AS soc ON soc.rowid = sp.fk_soc";
$sql .= " WHERE sp.poste IN ('Fils', 'Fille')";
if ($filtre === 'invites') {
	$sql .= " AND ex.fete_enfants = 1";
}
$sql .= " ORDER BY " . $sortfield . " " . $sortorder;

$resql = $db->query($sql);

// Stats
$sql_stats  = "SELECT COUNT(*) AS total,";
$sql_stats .= " SUM(CASE WHEN ex.fete_enfants = 1 THEN 1 ELSE 0 END) AS invites,";
$sql_stats .= " SUM(CASE WHEN (ex.age_1jan IS NOT NULL AND ex.age_1jan < 16) THEN 1 ELSE 0 END) AS moins16";
$sql_stats .= " FROM " . MAIN_DB_PREFIX . "socpeople AS sp";
$sql_stats .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople_extrafields AS ex ON ex.fk_object = sp.rowid";
$sql_stats .= " WHERE sp.poste IN ('Fils', 'Fille')";
$res_stats  = $db->query($sql_stats);
$stats      = $res_stats ? $db->fetch_object($res_stats) : null;

$total   = $stats ? (int)$stats->total   : 0;
$invites = $stats ? (int)$stats->invites : 0;
$moins16 = $stats ? (int)$stats->moins16 : 0;

// Bandeau stats
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield" style="margin-bottom:12px">';
print '<tr>';
print '  <td class="titlefield center" style="width:20%">Reference de calcul</td>';
print '  <td class="center" style="font-weight:bold">1er janvier ' . $annee_ref . '</td>';
print '  <td class="titlefield center" style="width:20%">Total fils / filles</td>';
print '  <td class="center" style="font-weight:bold">' . $total . '</td>';
print '  <td class="titlefield center" style="width:20%;color:#28a745">Enfants &lt; 16 ans</td>';
print '  <td class="center" style="font-weight:bold;color:#28a745">' . $moins16 . '</td>';
print '  <td class="titlefield center" style="width:20%;color:#007bff">Case cochee</td>';
print '  <td class="center" style="font-weight:bold;color:#007bff">' . $invites . '</td>';
print '</tr>';
print '</table>';

// Filtres
print '<div style="margin-bottom:10px">';
if ($filtre === 'invites') {
	print '<span class="butActionSelected">&#10004; Invites seulement</span> ';
	print '<a class="butAction" href="' . $url_base . '?filtre=tous&sortfield=' . urlencode($sortfield) . '&sortorder=' . $sortorder . '">Voir tous les enfants</a>';
} else {
	print '<a class="butAction" href="' . $url_base . '?filtre=invites&sortfield=' . urlencode($sortfield) . '&sortorder=' . $sortorder . '">&#10004; Invites seulement</a> ';
	print '<span class="butActionSelected">Tous les enfants</span>';
}
print '</div>';

// Tableau
if ($resql) {
	$rows = array();
	while ($obj = $db->fetch_object($resql)) { $rows[] = $obj; }
	$db->free($resql);

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'  . sort_link('Parent (Tiers)',              'soc.nom',          $sortfield, $sortorder, $url_base, $filtre) . '</th>';
	print '<th>'  . sort_link('Nom',                         'sp.lastname',       $sortfield, $sortorder, $url_base, $filtre) . '</th>';
	print '<th>'  . sort_link('Prenom',                      'sp.firstname',      $sortfield, $sortorder, $url_base, $filtre) . '</th>';
	print '<th>'  . sort_link('Genre',                       'sp.poste',          $sortfield, $sortorder, $url_base, $filtre) . '</th>';
	print '<th>Fonction</th>';
	print '<th class="center">' . sort_link('Date de naissance', 'sp.birthday',   $sortfield, $sortorder, $url_base, $filtre) . '</th>';
	print '<th class="center">' . sort_link('Age au 1er jan ' . $annee_ref, 'ex.age_1jan', $sortfield, $sortorder, $url_base, $filtre) . '</th>';
	print '<th class="center">' . sort_link('Fete des enfants',  'ex.fete_enfants', $sortfield, $sortorder, $url_base, $filtre) . '</th>';
	print '<th class="center">Fiche</th>';
	print '</tr>';

	if (empty($rows)) {
		print '<tr class="oddeven"><td colspan="9" class="center opacitymedium">';
		print ($filtre === 'invites') ? 'Aucun enfant invite trouve. Lancez le cron puis reessayez.' : 'Aucun enfant (poste Fils/Fille) trouve.';
		print '</td></tr>';
	} else {
		foreach ($rows as $obj) {
			$age  = is_null($obj->age_1jan) ? null : (int)$obj->age_1jan;
			$fete = (int)$obj->fete_enfants;

			print '<tr class="oddeven">';

			// Colonne Tiers en 1er
			if ($obj->tiers_id) {
				print '<td><a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . ((int)$obj->tiers_id) . '">' . dol_escape_htmltag($obj->tiers_nom) . '</a></td>';
			} else {
				print '<td><span class="opacitymedium">Non lie</span></td>';
			}

			print '<td>' . dol_escape_htmltag($obj->lastname)      . '</td>';
			print '<td>' . dol_escape_htmltag($obj->firstname)     . '</td>';
			print '<td>' . dol_escape_htmltag($obj->poste)         . '</td>';
			print '<td>' . dol_escape_htmltag($obj->tiers_fonction). '</td>';
			print '<td class="center">' . (!empty($obj->birthday) ? dol_print_date($db->jdate($obj->birthday), 'day') : '<i>&mdash;</i>') . '</td>';

			if (is_null($age)) {
				print '<td class="center opacitymedium">Non calcule</td>';
			} else {
				$couleur = ($age < 16) ? '#28a745' : '#666';
				print '<td class="center" style="font-weight:bold;color:' . $couleur . '">' . $age . ' ans</td>';
			}

			print '<td class="center">';
			if ($fete) {
				print '<span style="color:#28a745;font-size:1.3em" title="Invite(e)">&#10004;</span>';
			} else {
				print '<span style="color:#dc3545;font-size:1.3em" title="Non invite(e)">&#10008;</span>';
			}
			print '</td>';

			print '<td class="center"><a href="' . DOL_URL_ROOT . '/contact/card.php?id=' . ((int)$obj->rowid) . '">Voir</a></td>';
			print '</tr>';
		}
	}
	print '</table>';
} else {
	print '<div class="error">';
	print '<b>Erreur SQL :</b> ' . $db->lasterror() . '<br>';
	print 'Si le module vient d\'etre mis a jour, desactivez-le puis reactivez-le pour creer les nouveaux champs.';
	print '</div>';
}

print '</div>'; // fichecenter

llxFooter();
$db->close();