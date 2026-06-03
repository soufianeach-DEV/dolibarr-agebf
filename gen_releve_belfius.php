<?php
// Generateur de faux releve Belfius (format CSV reel) a partir des virements de test Dolibarr.
$db = new mysqli('127.0.0.1', 'root', '', 'dolibarr');
if ($db->connect_errno) { fwrite(STDERR, "DB error: " . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8');

$sql = "SELECT p.rowid AS pay_id,
               DATE_FORMAT(p.datep,'%d/%m/%Y') AS date_op,
               DATE_FORMAT(p.datep,'%Y/%m')    AS ym,
               pff.amount                       AS montant,
               s.nom                            AS tiers,
               p.num_paiement                   AS ref_sepa,
               f.ref                            AS facture
        FROM llx_paiementfourn p
        JOIN llx_paiementfourn_facturefourn pff ON pff.fk_paiementfourn = p.rowid
        JOIN llx_facture_fourn f ON f.rowid = pff.fk_facturefourn
        JOIN llx_societe s ON s.rowid = f.fk_soc
        ORDER BY p.datep, p.rowid";
$res = $db->query($sql);

// Numero d'extrait : un par mois (2026/0001, 2026/0002, ...)
$extraits = [];
$rows = [];
while ($r = $res->fetch_object()) { $rows[] = $r; }
$mois_uniques = [];
foreach ($rows as $r) { $mois_uniques[$r->ym] = true; }
ksort($mois_uniques);
$i = 0;
foreach (array_keys($mois_uniques) as $ym) { $i++; $extraits[$ym] = '2026/' . str_pad($i, 4, '0', STR_PAD_LEFT); }

$compte_org = 'BE68 5390 0754 7034';   // compte Belfius de l'association (fictif)
$out = [];

// --- Bloc d'en-tete facon Belfius Direct Net ---
$out[] = '"Compte :";"' . $compte_org . '"';
$out[] = '"Type :";"Compte courant"';
$out[] = '"Periode :";"01/01/2026 - 31/05/2026"';
$out[] = '"Devise :";"EUR"';
$out[] = '"Date d\'extraction :";"03/06/2026"';
$out[] = '';   // ligne vide avant les titres
// --- Ligne des titres (15 colonnes) ---
$out[] = implode(';', array_map(function ($c) { return '"' . $c . '"'; }, [
    'Compte', 'Date de comptabilisation', "Numero d'extrait", 'Numero de transaction',
    'Compte contrepartie', 'Nom de la contrepartie', 'Rue et numero', 'Code postal et localite',
    'Transaction', 'Date valeur', 'Montant', 'Devise', 'BIC', 'Code pays', 'Communication',
]));

// --- Lignes de transactions (debits = virements sortants) ---
$tx = 0;
foreach ($rows as $r) {
    $tx++;
    $extrait   = $extraits[$r->ym];
    $num_tx    = '20260' . str_pad($tx, 5, '0', STR_PAD_LEFT);
    $iban_cp   = 'BE' . str_pad((string)(10 + $r->pay_id), 2, '0', STR_PAD_LEFT) . ' 0000 0000 ' . str_pad((string)$r->pay_id, 4, '0', STR_PAD_LEFT);
    $montant   = '-' . number_format((float)$r->montant, 2, ',', '.');   // debit, format belge
    $commun    = $r->facture . ' - cotisation';

    $champs = [
        $compte_org, $r->date_op, $extrait, $num_tx,
        $iban_cp, $r->tiers, '', '',
        'Virement SEPA', $r->date_op, $montant, 'EUR', 'GKCCBEBB', 'BE', $commun,
    ];
    $out[] = implode(';', array_map(function ($c) { return '"' . str_replace('"', '""', $c) . '"'; }, $champs));
}

$content = implode("\r\n", $out) . "\r\n";
$content = mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');   // encodage Belfius
file_put_contents(__DIR__ . '/releve_belfius_test.csv', $content);
echo "OK - " . count($rows) . " lignes ecrites dans releve_belfius_test.csv\n";
echo "Extraits: " . implode(', ', array_map(function($k,$v){return $k.'='.$v;}, array_keys($extraits), array_values($extraits))) . "\n";
