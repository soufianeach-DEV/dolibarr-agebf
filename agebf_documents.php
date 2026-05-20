<?php
/* Copyright (C) 2026 Bruxelles Formation — Module AgeBF v3.1 */

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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

$filtre = GETPOST('filtre', 'aZ09');
if (!in_array($filtre, ['tous', 'manquants'])) $filtre = 'tous';

$url_base = DOL_URL_ROOT . '/custom/agebf/agebf_documents.php';

llxHeader("", "Helpy — Documents Tiers", '', '', 0, 0, '', '', '', 'mod-agebf page-documents');

print load_fiche_titre("Documents par Tiers", '', 'fa-folder-open');

// ── Récupération de tous les Tiers ───────────────────────────────────────────
$sql  = "SELECT rowid, nom FROM " . MAIN_DB_PREFIX . "societe";
$sql .= " WHERE entity = " . $conf->entity;
$sql .= " ORDER BY nom ASC";

$resql = $db->query($sql);
if (!$resql) {
	print '<div class="error">' . $db->lasterror() . '</div>';
	llxFooter(); $db->close(); exit;
}

$tiers_list = array();
while ($obj = $db->fetch_object($resql)) {
	$tiers_list[] = $obj;
}
$db->free($resql);

// ── Pour chaque Tiers : compter les fichiers dans son dossier ────────────────
$rows    = array();
$nb_ok   = 0;
$nb_ko   = 0;

foreach ($tiers_list as $obj) {
	$dir   = $conf->societe->dir_output . '/' . dol_sanitizeFileName($obj->rowid) . '/';
	$files = dol_dir_list($dir, 'files', 0, '', '(\.meta|_preview.*\.png)$');
	$nb    = count($files);

	// Détection composition de ménage (nom du fichier contient "compos" ou "menage" ou "ménage")
	$has_compo = false;
	foreach ($files as $f) {
		if (preg_match('/compos|menage|m.nage/i', $f['name'])) {
			$has_compo = true;
			break;
		}
	}

	if ($nb > 0) $nb_ok++; else $nb_ko++;

	$rows[] = array(
		'id'        => $obj->rowid,
		'nom'       => $obj->nom,
		'nb'        => $nb,
		'has_compo' => $has_compo,
		'files'     => $files,
	);
}

// ── Stats ────────────────────────────────────────────────────────────────────
$total = count($rows);
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield" style="margin-bottom:12px">';
print '<tr>';
print '  <td class="titlefield center" style="width:25%">Total Tiers</td>';
print '  <td class="center" style="font-weight:bold">' . $total . '</td>';
print '  <td class="titlefield center" style="width:25%;color:#28a745">Avec documents</td>';
print '  <td class="center" style="font-weight:bold;color:#28a745">' . $nb_ok . '</td>';
print '  <td class="titlefield center" style="width:25%;color:#dc3545">Sans documents</td>';
print '  <td class="center" style="font-weight:bold;color:#dc3545">' . $nb_ko . '</td>';
print '</tr>';
print '</table>';

// ── Filtres ──────────────────────────────────────────────────────────────────
print '<div style="margin-bottom:10px">';
if ($filtre === 'manquants') {
	print '<span class="butActionSelected">&#9888; Sans composition</span> ';
	print '<a class="butAction" href="' . $url_base . '?filtre=tous">Voir tous les Tiers</a>';
} else {
	print '<a class="butAction" href="' . $url_base . '?filtre=manquants">&#9888; Sans composition seulement</a> ';
	print '<span class="butActionSelected">Tous les Tiers</span>';
}
print '</div>';

// ── Tableau ──────────────────────────────────────────────────────────────────
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Tiers</th>';
print '<th class="center">Nb documents</th>';
print '<th class="center">Composition de menage</th>';
print '<th class="center">Fiche</th>';
print '</tr>';

$displayed = 0;
foreach ($rows as $row) {
	// Filtre
	if ($filtre === 'manquants' && ($row['has_compo'] || $row['nb'] > 0)) continue;

	$displayed++;
	$style = ($row['nb'] == 0) ? ' style="background-color:#fff5f5"' : '';

	print '<tr class="oddeven"' . $style . '>';

	// Nom du Tiers
	print '<td><a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . ((int)$row['id']) . '">';
	print dol_escape_htmltag($row['nom']);
	print '</a></td>';

	// Nb documents
	if ($row['nb'] == 0) {
		print '<td class="center"><span style="color:#dc3545;font-weight:bold">0</span></td>';
	} else {
		print '<td class="center"><span style="color:#28a745;font-weight:bold">' . $row['nb'] . '</span></td>';
	}

	// Composition de ménage
	if ($row['has_compo']) {
		print '<td class="center"><span style="color:#28a745;font-size:1.2em" title="Presente">&#10004;</span></td>';
	} elseif ($row['nb'] > 0) {
		print '<td class="center"><span style="color:#fd7e14;font-size:1.2em" title="Documents presents mais pas de composition detectee">?</span></td>';
	} else {
		print '<td class="center"><span style="color:#dc3545;font-size:1.2em" title="Aucun document">&#10008;</span></td>';
	}

	// Lien vers les documents du Tiers
	print '<td class="center"><a href="' . DOL_URL_ROOT . '/societe/document.php?socid=' . ((int)$row['id']) . '">Documents</a></td>';

	print '</tr>';
}

if ($displayed === 0) {
	print '<tr class="oddeven"><td colspan="4" class="center opacitymedium">Aucun Tiers trouve.</td></tr>';
}

print '</table>';
print '</div>';

// ── Légende ──────────────────────────────────────────────────────────────────
print '<div style="margin-top:16px;font-size:0.9em;color:#666">';
print '<b>Legende :</b> ';
print '<span style="color:#28a745">&#10004;</span> Fichier avec "compos" ou "menage" dans le nom &nbsp;|&nbsp; ';
print '<span style="color:#fd7e14">?</span> Documents presents mais nom non reconnu &nbsp;|&nbsp; ';
print '<span style="color:#dc3545">&#10008;</span> Aucun document';
print '</div>';

llxFooter();
$db->close();
