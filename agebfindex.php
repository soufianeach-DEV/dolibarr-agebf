<?php
/* Copyright (C) 2026 Bruxelles Formation */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

$langs->loadLangs(array("agebf@agebf"));

$annee_ref = (int) date('Y');
$date_ref  = $annee_ref . '-01-01';

// Récupère tous les enfants avec leur age_1jan
$sql  = "SELECT sp.rowid, sp.lastname, sp.firstname, sp.birthday, ex.age_1jan";
$sql .= " FROM " . MAIN_DB_PREFIX . "socpeople as sp";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople_extrafields as ex ON ex.fk_object = sp.rowid";
$sql .= " WHERE sp.poste = 'Enfant'";
$sql .= " ORDER BY ex.age_1jan ASC, sp.lastname ASC";

$resql = $db->query($sql);

llxHeader("", "AgeBF — Fête des enfants", '', '', 0, 0, '', '', '', 'mod-agebf page-index');

print load_fiche_titre("Fête des enfants " . $annee_ref . " — Liste des enfants", '', 'fa-child');

// Statistiques rapides
if ($resql) {
	$total     = $db->num_rows($resql);
	$invites   = 0;
	$rows      = array();
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
		if (!is_null($obj->age_1jan) && (int)$obj->age_1jan < 15) {
			$invites++;
		}
	}
	$non_invites = $total - $invites;

	// Bandeau stats
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr>';
	print '<td class="titlefield">' . $langs->trans("Référence de calcul") . '</td>';
	print '<td><b>1er janvier ' . $annee_ref . '</b></td>';
	print '<td class="titlefield">Total enfants</td>';
	print '<td><b>' . $total . '</b></td>';
	print '<td class="titlefield"><span style="color:#28a745">Invités (&lt; 15 ans)</span></td>';
	print '<td><b style="color:#28a745">' . $invites . '</b></td>';
	print '<td class="titlefield"><span style="color:#dc3545">Non invités (≥ 15 ans)</span></td>';
	print '<td><b style="color:#dc3545">' . $non_invites . '</b></td>';
	print '</tr>';
	print '</table>';
	print '</div><br>';

	// Tableau des enfants
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>Nom</th>';
	print '<th>Prénom</th>';
	print '<th>Date de naissance</th>';
	print '<th class="center">Âge au 1er janvier ' . $annee_ref . '</th>';
	print '<th class="center">Invité(e) à la fête</th>';
	print '<th class="center">Fiche contact</th>';
	print '</tr>';

	if (count($rows) === 0) {
		print '<tr class="oddeven"><td colspan="6" class="center opacitymedium">Aucun enfant trouvé. Vérifiez que les contacts ont le poste "Enfant" et que le cron a été exécuté.</td></tr>';
	} else {
		foreach ($rows as $obj) {
			$age     = is_null($obj->age_1jan) ? null : (int)$obj->age_1jan;
			$invite  = (!is_null($age) && $age < 15);

			print '<tr class="oddeven">';
			print '<td>' . dol_escape_htmltag($obj->lastname) . '</td>';
			print '<td>' . dol_escape_htmltag($obj->firstname) . '</td>';
			print '<td>' . (!empty($obj->birthday) ? dol_print_date($db->jdate($obj->birthday), 'day') : '<i>Non renseignée</i>') . '</td>';

			if (is_null($age)) {
				print '<td class="center"><span class="opacitymedium">Non calculé</span></td>';
				print '<td class="center"><span class="opacitymedium">—</span></td>';
			} else {
				print '<td class="center"><b>' . $age . ' ans</b></td>';
				if ($invite) {
					print '<td class="center"><span style="color:#28a745;font-weight:bold">✔ Oui</span></td>';
				} else {
					print '<td class="center"><span style="color:#dc3545;font-weight:bold">✘ Non</span></td>';
				}
			}

			print '<td class="center"><a href="' . DOL_URL_ROOT . '/contact/card.php?id=' . ((int)$obj->rowid) . '">Voir</a></td>';
			print '</tr>';
		}
	}
	print '</table>';

	$db->free($resql);
} else {
	print '<div class="error">' . $db->lasterror() . '</div>';
}

print '<br><div class="center">';
print '<a class="butAction" href="' . DOL_URL_ROOT . '/cron/list.php">Gérer le cron</a>';
print '</div>';

llxFooter();
$db->close();
