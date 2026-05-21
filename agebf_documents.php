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

$filtre  = GETPOST('filtre', 'aZ09');
if (!in_array($filtre, ['tous', 'manquants', 'avec'])) $filtre = 'tous';

$url_base = DOL_URL_ROOT . '/custom/agebf/agebf_documents.php';

// ── Action : renommage de fichier (admin uniquement) ─────────────────────────
$action  = GETPOST('action', 'aZ09');
$msg_ok  = '';
$msg_err = '';

if (GETPOST('renamed', 'int') == 1) {
	$msg_ok = 'Fichier renomme avec succes : <b>' . dol_escape_htmltag(GETPOST('newname', 'nohtml')) . '</b>';
}

if ($action === 'rename' && $user->admin) {
	$socid   = (int) GETPOST('socid', 'int');
	$oldname = basename(GETPOST('oldname', 'nohtml'));
	$newname = trim(basename(GETPOST('newname', 'nohtml')));

	if ($socid > 0 && $oldname && $newname && $oldname !== $newname) {
		$dir     = $conf->societe->dir_output . '/' . dol_sanitizeFileName($socid) . '/';
		$oldpath = $dir . $oldname;
		$newpath = $dir . $newname;

		if (!file_exists($oldpath)) {
			$msg_err = 'Fichier source introuvable : ' . dol_escape_htmltag($oldname);
		} elseif (file_exists($newpath)) {
			$msg_err = 'Un fichier avec ce nom existe deja : ' . dol_escape_htmltag($newname);
		} else {
			if (@rename($oldpath, $newpath)) {
				// ── Mettre à jour llx_ecm_files pour que Dolibarr retrouve le fichier ──
				$sql_ecm = "UPDATE " . MAIN_DB_PREFIX . "ecm_files"
				         . " SET filename = '" . $db->escape($newname) . "'"
				         . ", label = '" . $db->escape(preg_replace('/\.[^.]+$/', '', $newname)) . "'"
				         . " WHERE filename = '" . $db->escape($oldname) . "'"
				         . " AND src_object_type = 'societe'"
				         . " AND src_object_id = " . (int)$socid;
				$db->query($sql_ecm);
				// ── Redirect GET pour éviter "Confirmer le nouvel envoi" au retour ──
				header('Location: ' . $url_base . '?filtre=' . urlencode($filtre) . '&renamed=1&newname=' . urlencode($newname));
				exit;
			} else {
				$msg_err = 'Echec du renommage — verifiez les permissions.';
			}
		}
	}
}

llxHeader("", "Helpy — Documents Tiers", '', '', 0, 0, '', '', '', 'mod-agebf page-documents');

// ── Modal visualisation ───────────────────────────────────────────────────────
print '
<style>
#agebf-modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.6);
    z-index:9999; align-items:center; justify-content:center;
}
#agebf-modal-overlay.open { display:flex; }
#agebf-modal-box {
    background:#fff; border-radius:6px; box-shadow:0 8px 32px rgba(0,0,0,.4);
    width:80vw; height:80vh;
    display:flex; flex-direction:column; overflow:hidden;
}
#agebf-modal-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 16px; background:#f5f5f5; border-bottom:1px solid #ddd;
    font-weight:bold; font-size:.95em; color:#333;
}
#agebf-modal-header a {
    font-size:.8em; font-weight:normal; color:#6c757d;
    text-decoration:none; margin-right:12px;
}
#agebf-modal-header a:hover { text-decoration:underline; }
#agebf-modal-close {
    cursor:pointer; font-size:1.4em; color:#888; border:none;
    background:none; line-height:1; padding:0 4px;
}
#agebf-modal-close:hover { color:#333; }
#agebf-modal-iframe { flex:1; border:none; width:100%; }
</style>

<div id="agebf-modal-overlay">
  <div id="agebf-modal-box">
    <div id="agebf-modal-header">
      <span id="agebf-modal-title">Document</span>
      <div>
        <a id="agebf-modal-newtab" href="#" target="_blank">&#x2197; Ouvrir dans un onglet</a>
        <button id="agebf-modal-close" onclick="agebfCloseModal()" title="Fermer">&times;</button>
      </div>
    </div>
    <iframe id="agebf-modal-iframe" src="about:blank"></iframe>
  </div>
</div>

<script>
function agebfOpenModal(url, filename) {
    document.getElementById("agebf-modal-iframe").src = url;
    document.getElementById("agebf-modal-title").textContent = filename;
    document.getElementById("agebf-modal-newtab").href = url;
    document.getElementById("agebf-modal-overlay").classList.add("open");
}
function agebfCloseModal() {
    document.getElementById("agebf-modal-overlay").classList.remove("open");
    document.getElementById("agebf-modal-iframe").src = "about:blank";
}
document.getElementById("agebf-modal-overlay").addEventListener("click", function(e) {
    if (e.target === this) agebfCloseModal();
});
document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") agebfCloseModal();
});
</script>
';

print load_fiche_titre("Documents par Tiers", '', 'fa-folder-open');

// Messages retour
if ($msg_ok)  print '<div class="ok">' . $msg_ok . '</div>';
if ($msg_err) print '<div class="error">' . $msg_err . '</div>';

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
$rows        = array();
$nb_ok       = 0;  // avec au moins 1 document
$nb_ko       = 0;  // sans aucun document
$nb_fournie  = 0;  // composition présente
$nb_manquante = 0; // docs présents mais composition manquante

foreach ($tiers_list as $obj) {
	$dir   = $conf->societe->dir_output . '/' . dol_sanitizeFileName($obj->rowid) . '/';
	$files = dol_dir_list($dir, 'files', 0, '', '(\.meta|_preview.*\.png)$');
	$nb    = count($files);

	$has_compo = false;
	foreach ($files as $f) {
		if (preg_match('/composi?tion|m[eé]nage/i', $f['name'])) {
			$has_compo = true;
			break;
		}
	}

	if ($nb > 0) $nb_ok++; else $nb_ko++;
	if ($has_compo) $nb_fournie++;
	elseif ($nb > 0) $nb_manquante++;

	$rows[] = array(
		'id'        => $obj->rowid,
		'nom'       => $obj->nom,
		'nb'        => $nb,
		'has_compo' => $has_compo,
		'files'     => $files,
		'dir'       => $dir,
	);
}

// ── Stats ────────────────────────────────────────────────────────────────────
$total = count($rows);
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield" style="margin-bottom:12px">';
print '<tr>';
print '  <td class="titlefield center">Total Tiers</td>';
print '  <td class="center" style="font-weight:bold">' . $total . '</td>';
print '  <td class="titlefield center" style="color:#28a745">Composition fournie</td>';
print '  <td class="center" style="font-weight:bold;color:#28a745">' . $nb_fournie . '</td>';
print '  <td class="titlefield center" style="color:#fd7e14">Composition manquante</td>';
print '  <td class="center" style="font-weight:bold;color:#fd7e14">' . $nb_manquante . '</td>';
print '  <td class="titlefield center" style="color:#dc3545">Aucun document</td>';
print '  <td class="center" style="font-weight:bold;color:#dc3545">' . $nb_ko . '</td>';
print '</tr>';
print '</table>';

// ── Filtres ──────────────────────────────────────────────────────────────────
print '<div style="margin-bottom:10px">';
if ($filtre === 'tous') {
	print '<span class="butActionSelected">Tous les Tiers</span> ';
	print '<a class="butAction" href="' . $url_base . '?filtre=avec">&#10004; Avec composition</a> ';
	print '<a class="butAction" href="' . $url_base . '?filtre=manquants">&#9888; Sans composition</a>';
} elseif ($filtre === 'avec') {
	print '<a class="butAction" href="' . $url_base . '?filtre=tous">Tous les Tiers</a> ';
	print '<span class="butActionSelected">&#10004; Avec composition</span> ';
	print '<a class="butAction" href="' . $url_base . '?filtre=manquants">&#9888; Sans composition</a>';
} else {
	print '<a class="butAction" href="' . $url_base . '?filtre=tous">Tous les Tiers</a> ';
	print '<a class="butAction" href="' . $url_base . '?filtre=avec">&#10004; Avec composition</a> ';
	print '<span class="butActionSelected">&#9888; Sans composition</span>';
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
	if ($filtre === 'manquants' && $row['has_compo']) continue;
	if ($filtre === 'avec'      && !$row['has_compo']) continue;

	$displayed++;
	$bg = ($row['nb'] == 0) ? ' style="background-color:#fff5f5"' : '';

	print '<tr class="oddeven"' . $bg . '>';

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

	// Statut composition
	if ($row['has_compo']) {
		print '<td class="center"><span style="color:#28a745;font-weight:bold">Fournie</span></td>';
	} elseif ($row['nb'] > 0) {
		print '<td class="center"><span style="color:#fd7e14;font-weight:bold">Manquante</span></td>';
	} else {
		print '<td class="center"><span style="color:#dc3545;font-weight:bold">Aucun document</span></td>';
	}

	// Lien fiche
	print '<td class="center"><a href="' . DOL_URL_ROOT . '/societe/document.php?socid=' . ((int)$row['id']) . '">Documents</a></td>';

	print '</tr>';

	// ── Zone fichiers (tous les Tiers avec documents) ────────────────────────
	if ($row['nb'] > 0) {
		$bg_zone = $row['has_compo'] ? '#f0fff4' : '#fffdf0';
		print '<tr>';
		print '<td colspan="4" style="padding:6px 20px 10px 30px;background:' . $bg_zone . ';border-top:none">';

		if ($user->admin && !$row['has_compo']) {
			// Admin + manquante : afficher aide renommage
			print '<div style="font-size:0.88em;color:#555;margin-bottom:6px">';
			print '<b>Fichiers presents</b> — renommez un fichier pour qu\'il soit reconnu comme composition de m&eacute;nage ';
			print '<span style="color:#888">(le nom doit contenir &laquo;&nbsp;composition&nbsp;&raquo;, &laquo;&nbsp;compostion&nbsp;&raquo; ou &laquo;&nbsp;m&eacute;nage&nbsp;&raquo;)</span>';
			print '</div>';
		} else {
			print '<div style="font-size:0.88em;color:#555;margin-bottom:6px"><b>Fichiers presents</b></div>';
		}

		foreach ($row['files'] as $f) {
			$fname   = dol_escape_htmltag($f['name']);
			$fileurl = DOL_URL_ROOT . '/document.php?modulepart=societe&attachment=0'
			         . '&file=' . urlencode((int)$row['id'] . '/' . $f['name']);

			// Mettre en evidence le fichier composition
			$is_compo = (bool) preg_match('/composi?tion|m[eé]nage/i', $f['name']);
			$name_style = $is_compo
				? 'color:#28a745;font-weight:bold'
				: 'color:#555';

			$btn_voir = '<a href="javascript:void(0)" onclick="agebfOpenModal('
			          . htmlspecialchars(json_encode($fileurl), ENT_QUOTES) . ','
			          . htmlspecialchars(json_encode($f['name']), ENT_QUOTES) . ')" '
			          . 'style="display:inline-flex;align-items:center;padding:3px 10px;background:#6c757d;color:#fff;border-radius:3px;font-size:0.85em;text-decoration:none;white-space:nowrap">'
			          . img_picto('', 'fa-eye', 'class="paddingright"') . ' Voir</a>';

			if ($user->admin && !$row['has_compo']) {
				// Admin + manquante : Voir + Renommer
				print '<form method="POST" action="' . $url_base . '" style="display:flex;align-items:center;gap:8px;margin:4px 0">';
				print '<input type="hidden" name="action"  value="rename">';
				print '<input type="hidden" name="token"   value="' . newToken() . '">';
				print '<input type="hidden" name="filtre"  value="' . dol_escape_htmltag($filtre) . '">';
				print '<input type="hidden" name="socid"   value="' . (int)$row['id'] . '">';
				print '<input type="hidden" name="oldname" value="' . $fname . '">';
				print $btn_voir;
				print '<input type="text" name="newname" value="' . $fname . '" ';
				print '       style="width:320px;padding:3px 8px;font-size:0.9em;border:1px solid #ccc;border-radius:3px">';
				print '<button type="submit" class="butAction" style="padding:3px 14px;font-size:0.85em;margin:0">Renommer</button>';
				print '</form>';
			} else {
				// Tous : Voir uniquement
				print '<div style="display:flex;align-items:center;gap:10px;margin:4px 0">';
				print $btn_voir;
				print '<span style="font-size:0.9em;' . $name_style . '">' . $fname . '</span>';
				print '</div>';
			}
		}

		print '</td></tr>';
	}
}

if ($displayed === 0) {
	print '<tr class="oddeven"><td colspan="4" class="center opacitymedium">Aucun Tiers trouve.</td></tr>';
}

print '</table>';
print '</div>';

// ── Légende ──────────────────────────────────────────────────────────────────
print '<div style="margin-top:16px;font-size:0.9em;color:#666">';
print '<b>Legende :</b> ';
print '<span style="color:#28a745;font-weight:bold">Fournie</span> = composition de m&eacute;nage d&eacute;tect&eacute;e &nbsp;|&nbsp; ';
print '<span style="color:#fd7e14;font-weight:bold">Manquante</span> = des documents sont presents mais aucune composition de m&eacute;nage &nbsp;|&nbsp; ';
print '<span style="color:#dc3545;font-weight:bold">Aucun document</span> = aucun fichier joint au Tiers';
print '</div>';

llxFooter();
$db->close();
