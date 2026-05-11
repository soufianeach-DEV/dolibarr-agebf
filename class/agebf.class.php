<?php
/* Copyright (C) 2026 Bruxelles Formation */

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
	 * Calcule l'âge de tous les contacts au 1er janvier de l'année en cours
	 * et met à jour le champ extrafield age_1jan
	 *
	 * @return int 0 si OK, <0 si erreur
	 */
	public function calculerAgesContacts()
	{
		$annee = (int) date('Y');
		$date_ref = $annee . '-01-01';

		// Récupère tous les contacts avec une date de naissance renseignée
		$sql = "SELECT sp.rowid, sp.birthday";
		$sql .= " FROM ".MAIN_DB_PREFIX."socpeople as sp";
		$sql .= " WHERE sp.birthday IS NOT NULL";

		$resql = $this->db->query($sql);

		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$nb_ok = 0;
		$nb_err = 0;

		while ($obj = $this->db->fetch_object($resql)) {
			$naissance = new DateTime($this->db->jdate($obj->birthday));
			$reference = new DateTime($date_ref);
			$age = (int) $reference->diff($naissance)->y;

			// Vérifie si l'extrafield existe déjà pour ce contact
			$sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."socpeople_extrafields";
			$sql_check .= " WHERE fk_object = ".((int) $obj->rowid);
			$res_check = $this->db->query($sql_check);

			if ($res_check && $this->db->num_rows($res_check) > 0) {
				$sql_update = "UPDATE ".MAIN_DB_PREFIX."socpeople_extrafields";
				$sql_update .= " SET age_1jan = ".((int) $age);
				$sql_update .= " WHERE fk_object = ".((int) $obj->rowid);
				$result = $this->db->query($sql_update);
			} else {
				$sql_insert = "INSERT INTO ".MAIN_DB_PREFIX."socpeople_extrafields (fk_object, age_1jan)";
				$sql_insert .= " VALUES (".((int) $obj->rowid).", ".((int) $age).")";
				$result = $this->db->query($sql_insert);
			}

			if ($result) {
				$nb_ok++;
			} else {
				$nb_err++;
			}
		}

		dol_syslog("AgeBF::calculerAgesContacts - OK: $nb_ok, Erreurs: $nb_err", LOG_INFO);

		return ($nb_err > 0) ? -1 : 0;
	}
}
