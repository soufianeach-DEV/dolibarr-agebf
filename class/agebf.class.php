<?php
/* Copyright (C) 2026 Bruxelles Formation — Module AgeBF v2.0 */

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

class AgeBF
{
	public $db;
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Vérifie si une colonne existe dans une table
	 */
	private function columnExists($table, $column)
	{
		$sql = "SHOW COLUMNS FROM " . $table . " LIKE '" . $this->db->escape($column) . "'";
		$res = $this->db->query($sql);
		return ($res && $this->db->num_rows($res) > 0);
	}

	/**
	 * Calcule l'âge de TOUS les contacts au 1er janvier de l'année en cours.
	 * - Met à jour age_1jan pour tous les contacts ayant une date de naissance
	 * - Met fete_enfants = 1 sur les Fils/Fille de moins de 16 ans
	 * - Propage fete_enfants = 1 au parent (via extrafield fk_parent) si au moins un enfant qualifie
	 * - Propage fete_enfants = 1 au Tiers si au moins un contact lié qualifie
	 *
	 * @return int  0 si OK, -1 si erreur
	 */
	public function calculerAgesContacts()
	{
		$annee    = (int) date('Y');
		$date_ref = $annee . '-01-01';
		$table_sp = MAIN_DB_PREFIX . 'socpeople_extrafields';
		$table_sc = MAIN_DB_PREFIX . 'societe_extrafields';

		// Vérifie quelles colonnes sont disponibles
		$has_fete     = $this->columnExists($table_sp, 'fete_enfants');
		$has_parent   = $this->columnExists($table_sp, 'fk_parent');
		$has_fete_soc = $this->columnExists($table_sc, 'fete_enfants');

		// ── STEP 1 : Calcul de l'âge + fete_enfants pour TOUS les contacts ──────
		$sql  = "SELECT sp.rowid, sp.birthday, sp.poste";
		$sql .= " FROM " . MAIN_DB_PREFIX . "socpeople AS sp";
		$sql .= " WHERE sp.birthday IS NOT NULL AND sp.birthday != '0000-00-00'";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$nb_ok  = 0;
		$nb_err = 0;
		$reference = new DateTime($date_ref);

		while ($obj = $this->db->fetch_object($resql)) {
			$naissance = new DateTime($obj->birthday);
			$age       = (int) $reference->diff($naissance)->y;
			$poste     = strtolower(trim((string) $obj->poste));
			$is_enfant = in_array($poste, ['fils', 'fille']);
			$fete      = ($is_enfant && $age < 16) ? 1 : 0;

			// Vérifie si une ligne extrafield existe déjà
			$sql_chk = "SELECT rowid FROM " . $table_sp . " WHERE fk_object = " . ((int) $obj->rowid);
			$res_chk = $this->db->query($sql_chk);
			$exists  = ($res_chk && $this->db->num_rows($res_chk) > 0);

			if ($exists) {
				$set = "age_1jan = " . ((int) $age);
				if ($has_fete) {
					$set .= ", fete_enfants = " . ((int) $fete);
				}
				$result = $this->db->query(
					"UPDATE " . $table_sp . " SET $set WHERE fk_object = " . ((int) $obj->rowid)
				);
			} else {
				$cols = "fk_object, age_1jan";
				$vals = ((int) $obj->rowid) . ", " . ((int) $age);
				if ($has_fete) {
					$cols .= ", fete_enfants";
					$vals .= ", " . ((int) $fete);
				}
				$result = $this->db->query(
					"INSERT INTO " . $table_sp . " ($cols) VALUES ($vals)"
				);
			}

			$result ? $nb_ok++ : $nb_err++;
		}
		$this->db->free($resql);

		// ── STEP 2 : (supprimé — le lien enfant→parent se fait via fk_soc) ──────

		// ── STEP 3 : Propagation fete_enfants + nb_enfants_invites → Tiers ──────
		if ($has_fete && $has_fete_soc) {
			$has_nb = $this->columnExists($table_sc, 'nb_enfants_invites');

			// Remet tout à 0 sur les Tiers
			if ($has_nb) {
				$this->db->query("UPDATE " . $table_sc . " SET fete_enfants = 0, nb_enfants_invites = 0");
			} else {
				$this->db->query("UPDATE " . $table_sc . " SET fete_enfants = 0");
			}

			// Calcule par Tiers : nb d'enfants invités et présence d'au moins 1
			$sql_t  = "SELECT sp.fk_soc, COUNT(*) AS nb";
			$sql_t .= " FROM " . MAIN_DB_PREFIX . "socpeople AS sp";
			$sql_t .= " INNER JOIN " . $table_sp . " AS ex ON ex.fk_object = sp.rowid";
			$sql_t .= " WHERE ex.fete_enfants = 1 AND sp.fk_soc IS NOT NULL AND sp.fk_soc > 0";
			$sql_t .= " GROUP BY sp.fk_soc";
			$res_t  = $this->db->query($sql_t);

			if ($res_t) {
				while ($t = $this->db->fetch_object($res_t)) {
					$fk  = (int) $t->fk_soc;
					$nb  = (int) $t->nb;
					$set = "fete_enfants = 1";
					if ($has_nb) { $set .= ", nb_enfants_invites = $nb"; }

					$sql_chk = "SELECT rowid FROM " . $table_sc . " WHERE fk_object = $fk";
					$res_chk = $this->db->query($sql_chk);
					if ($res_chk && $this->db->num_rows($res_chk) > 0) {
						$this->db->query("UPDATE " . $table_sc . " SET $set WHERE fk_object = $fk");
					} else {
						$cols = "fk_object, fete_enfants" . ($has_nb ? ", nb_enfants_invites" : "");
						$vals = "$fk, 1"                  . ($has_nb ? ", $nb"                : "");
						$this->db->query("INSERT INTO " . $table_sc . " ($cols) VALUES ($vals)");
					}
				}
				$this->db->free($res_t);
			}
		}

		dol_syslog("AgeBF v2.0 :: calculerAgesContacts OK=$nb_ok ERR=$nb_err ref=$date_ref", LOG_INFO);
		return ($nb_err > 0) ? -1 : 0;
	}
}
