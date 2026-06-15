<?php
/**
 * Script de génération de noms créatifs pour les contacts et Tiers
 * Accès : http://localhost/dolibarr/htdocs/custom/agebf/gen_names_test.php
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) { $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php"; }
if (!$res && file_exists("../main.inc.php"))   { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

// ── Pools de noms ─────────────────────────────────────────────────────────────

$prenoms_garcons = [
	'Hugo', 'Théo', 'Lucas', 'Noah', 'Arthur', 'Raphaël', 'Axel', 'Tom',
	'Matteo', 'Mathis', 'Victor', 'Nathan', 'Antoine', 'Jules', 'Ethan',
	'Maxime', 'Louis', 'Liam', 'Oscar', 'Gabriel', 'Simon', 'Quentin',
	'Baptiste', 'Romain', 'Florian', 'Tristan', 'Clément', 'Alexis',
	'Kylian', 'Yanis', 'Sébastien', 'Kevin', 'Dylan', 'Enzo', 'Lorenzo',
	'Damien', 'Pierre', 'Julien', 'Adrien', 'Nicolas',
];

$prenoms_filles = [
	'Emma', 'Chloé', 'Jade', 'Léa', 'Inès', 'Camille', 'Manon', 'Zoé',
	'Alice', 'Lucie', 'Clara', 'Sarah', 'Eva', 'Anaïs', 'Valentine',
	'Margot', 'Élise', 'Pauline', 'Océane', 'Laure', 'Maëlys', 'Romane',
	'Ambre', 'Lola', 'Noemie', 'Célia', 'Justine', 'Salomé', 'Héloïse',
	'Capucine', 'Luisa', 'Amélie', 'Sophie', 'Sabine', 'Nathalie',
	'Audrey', 'Céline', 'Virginie', 'Delphine', 'Isabelle',
];

$prenoms_adultes_h = [
	'Marc', 'Jean', 'Pierre', 'André', 'Alain', 'Philippe', 'Michel',
	'François', 'Patrick', 'Christophe', 'Laurent', 'Stéphane', 'David',
	'Thomas', 'Nicolas', 'Frédéric', 'Olivier', 'Xavier', 'Guillaume',
	'Benoît', 'Sébastien', 'Éric', 'Thierry', 'Bruno', 'Luc',
];

$prenoms_adultes_f = [
	'Marie', 'Sophie', 'Isabelle', 'Nathalie', 'Catherine', 'Véronique',
	'Christine', 'Sandrine', 'Sylvie', 'Valérie', 'Aurélie', 'Émilie',
	'Céline', 'Virginie', 'Laetitia', 'Stéphanie', 'Delphine', 'Audrey',
	'Patricia', 'Mélanie', 'Caroline', 'Françoise', 'Martine', 'Hélène',
	'Anne-Sophie',
];

// Noms de famille créatifs (belges/français variés)
$noms_famille = [
	'Fontaine', 'Lecomte', 'Renard', 'Marchetti', 'Nguyen', 'De Smet',
	'Lambert', 'Chevalier', 'Lefebvre', 'Pirard', 'Collin', 'Maes',
	'Jacobs', 'Vanderberg', 'Henrard', 'Gilles', 'Charlier', 'Peeters',
	'Claes', 'Wouters', 'Hermans', 'Stevens', 'Dubois', 'Dumont',
	'Lacroix', 'Mertens', 'Willems', 'Vermeersch', 'Antoine', 'Rousseau',
	'Simon', 'Morin', 'Garnier', 'Girard', 'Moreau', 'Faure', 'Thomas',
	'Schmitz', 'Bastin', 'Lejeune', 'Adam', 'Denis', 'Leonard',
	'Bodart', 'Hanon', 'Philippot', 'Demoulin', 'Habran', 'Franck',
	'Magnette', 'Etienne', 'Mathieu', 'Parmentier', 'Detry',
];

// ── Récupère tous les Tiers ───────────────────────────────────────────────────
$tiers_list = [];
$rq = $db->query("SELECT rowid, nom FROM " . MAIN_DB_PREFIX . "societe ORDER BY rowid");
if ($rq) { while ($o = $db->fetch_object($rq)) $tiers_list[] = $o; }

// ── Récupère tous les contacts ────────────────────────────────────────────────
$contacts_list = [];
$rq2 = $db->query(
	"SELECT c.rowid, c.fk_soc, c.poste, c.lastname, c.firstname
	 FROM " . MAIN_DB_PREFIX . "socpeople c
	 ORDER BY c.fk_soc, c.rowid"
);
if ($rq2) { while ($o = $db->fetch_object($rq2)) $contacts_list[] = $o; }

// ── Génère les noms ───────────────────────────────────────────────────────────
srand(99); // seed fixe → reproductible

// Noms de famille pour les Tiers — shuffle, pas de doublons
$noms_shuffle = $noms_famille;
shuffle($noms_shuffle);

// Mapping tiers_id → nouveau nom de famille
$tiers_name_map = [];
foreach ($tiers_list as $idx => $t) {
	$nom = $noms_shuffle[$idx % count($noms_shuffle)];
	// Tirage d'un prénom adulte selon parité
	$p = ($idx % 2 === 0)
		? $prenoms_adultes_h[array_rand($prenoms_adultes_h)]
		: $prenoms_adultes_f[array_rand($prenoms_adultes_f)];
	$tiers_name_map[$t->rowid] = ['nom' => $nom, 'prenom' => $p];
}

// Contacts : assigne un prénom unique par poste au sein de chaque Tiers
// On garde une liste de prénoms disponibles par genre
$used_garcons = []; // par fk_soc
$used_filles  = [];
$used_adultes = [];

$contact_name_map = []; // cid → [lastname, firstname]

foreach ($contacts_list as $c) {
	$soc = $c->fk_soc;
	$nom_famille = isset($tiers_name_map[$soc]) ? $tiers_name_map[$soc]['nom'] : 'Durand';
	$poste = strtolower(trim($c->poste));

	if ($poste === 'fils') {
		if (!isset($used_garcons[$soc])) {
			$pool = $prenoms_garcons;
			shuffle($pool);
			$used_garcons[$soc] = $pool;
		}
		$prenom = array_shift($used_garcons[$soc]) ?? 'Hugo';
	} elseif ($poste === 'fille') {
		if (!isset($used_filles[$soc])) {
			$pool = $prenoms_filles;
			shuffle($pool);
			$used_filles[$soc] = $pool;
		}
		$prenom = array_shift($used_filles[$soc]) ?? 'Léa';
	} else {
		// contact adulte : prénom selon alternance M/F
		if (!isset($used_adultes[$soc])) {
			$pool = array_merge($prenoms_adultes_h, $prenoms_adultes_f);
			shuffle($pool);
			$used_adultes[$soc] = $pool;
		}
		$prenom = array_shift($used_adultes[$soc]) ?? 'Alex';
	}

	$contact_name_map[$c->rowid] = [
		'lastname'  => $nom_famille,
		'firstname' => $prenom,
	];
}

// ── Action : exécuter ─────────────────────────────────────────────────────────
$done_tiers = $done_contacts = $errors = 0;

if (!empty($_POST['go'])) {
	// Mise à jour Tiers
	foreach ($tiers_name_map as $tid => $info) {
		$nom = $db->escape($info['nom'] . ' ' . $info['prenom']);
		$sql = "UPDATE " . MAIN_DB_PREFIX . "societe SET nom='" . $nom . "' WHERE rowid=" . (int)$tid;
		if ($db->query($sql)) $done_tiers++; else $errors++;
	}
	// Mise à jour contacts
	foreach ($contact_name_map as $cid => $info) {
		$ln = $db->escape($info['lastname']);
		$fn = $db->escape($info['firstname']);
		$sql = "UPDATE " . MAIN_DB_PREFIX . "socpeople"
		     . " SET lastname='" . $ln . "', firstname='" . $fn . "'"
		     . " WHERE rowid=" . (int)$cid;
		if ($db->query($sql)) $done_contacts++; else $errors++;
	}
}

// ── Affichage ─────────────────────────────────────────────────────────────────
$executed = !empty($_POST['go']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gen — Noms créatifs</title>
<style>
body { font-family: Arial, sans-serif; max-width: 1000px; margin: 40px auto; padding: 0 20px; }
h2, h3 { color: #333; }
table { border-collapse: collapse; width: 100%; margin-top: 12px; font-size: 13px; }
th, td { border: 1px solid #ddd; padding: 6px 12px; text-align: left; }
th { background: #f0f0f0; font-weight: bold; }
tr:hover { background: #f9f9f9; }
.tiers-row { background: #e8f0fe; font-weight: bold; }
.ok  { background:#d4edda; border:1px solid #c3e6cb; padding:10px 16px; border-radius:6px; margin:12px 0; }
.btn { background:#0d6efd; color:#fff; border:none; padding:10px 28px; border-radius:6px; font-size:15px; cursor:pointer; }
.btn:hover { background:#0b5ed7; }
.badge-fils  { display:inline-block; padding:1px 7px; border-radius:8px; background:#0d6efd; color:#fff; font-size:11px; }
.badge-fille { display:inline-block; padding:1px 7px; border-radius:8px; background:#d63384; color:#fff; font-size:11px; }
.badge-adult { display:inline-block; padding:1px 7px; border-radius:8px; background:#198754; color:#fff; font-size:11px; }
.arrow { color:#999; font-size:11px; margin: 0 6px; }
</style>
</head>
<body>

<h2>✏️ Génération de noms créatifs</h2>

<?php if ($executed): ?>
<div class="ok">
	✅ <strong><?= $done_tiers ?> Tiers</strong> renommés &nbsp;·&nbsp;
	<strong><?= $done_contacts ?> contacts</strong> renommés
	<?= $errors ? " · <span style='color:red'>$errors erreur(s)</span>" : '' ?>
</div>
<p><a href="agebf_assurances.php">→ Voir la page Assurances</a></p>
<?php else: ?>

<p>Aperçu des noms qui seront assignés. Cliquez <strong>Appliquer</strong> pour écrire en base.</p>

<h3>Tiers (<?= count($tiers_list) ?>)</h3>
<table>
<tr><th>#</th><th>Ancien nom</th><th></th><th>Nouveau nom</th></tr>
<?php foreach ($tiers_list as $t):
	$info = $tiers_name_map[$t->rowid];
	$new  = $info['nom'] . ' ' . $info['prenom'];
?>
<tr class="tiers-row">
	<td><?= $t->rowid ?></td>
	<td><?= htmlspecialchars($t->nom) ?></td>
	<td class="arrow">→</td>
	<td><?= htmlspecialchars($new) ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3>Contacts (<?= count($contacts_list) ?>)</h3>
<table>
<tr><th>#</th><th>Poste</th><th>Ancien</th><th></th><th>Nouveau prénom</th><th>Nouveau nom</th></tr>
<?php foreach ($contacts_list as $c):
	$info  = $contact_name_map[$c->rowid];
	$poste = strtolower(trim($c->poste));
	if ($poste === 'fils')   $badge = '<span class="badge-fils">Fils</span>';
	elseif ($poste === 'fille') $badge = '<span class="badge-fille">Fille</span>';
	else                    $badge = '<span class="badge-adult">' . htmlspecialchars($c->poste) . '</span>';
?>
<tr>
	<td><?= $c->rowid ?></td>
	<td><?= $badge ?></td>
	<td style="color:#999"><?= htmlspecialchars($c->firstname . ' ' . $c->lastname) ?></td>
	<td class="arrow">→</td>
	<td><strong><?= htmlspecialchars($info['firstname']) ?></strong></td>
	<td><?= htmlspecialchars($info['lastname']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<br>
<form method="post">
	<button type="submit" name="go" value="1" class="btn">
		🚀 Appliquer (<?= count($tiers_list) ?> Tiers + <?= count($contacts_list) ?> contacts)
	</button>
</form>

<?php endif; ?>
</body>
</html>
