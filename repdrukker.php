<?php

class nl_sp_repdrukker extends CRM_Report_Form {

  protected $_addressField = FALSE;
	protected $_emailField = FALSE;
	protected $_summary = NULL;
	protected $_customGroupExtends = array('Membership');
	protected $_customGroupGroupBy = FALSE; 
	protected $_memberships;

	function __construct() {
		$this->_groupFilter = FALSE;
		$this->_tagFilter = FALSE;
		parent::__construct();
		$this->fetchCustom();
	}

	function fetchCustom() {
		try{
			$this->_memberships 						= new stdClass();
			$this->_memberships->proef 					= civicrm_api3('MembershipType', 'getsingle', array("name" => "Abonnee Blad-Tribune Proef"));
			$this->_memberships->gratis 				= civicrm_api3('MembershipType', 'getsingle', array("name" => "Abonnee Blad-Tribune Gratis"));
			$this->_memberships->betaald 				= civicrm_api3('MembershipType', 'getsingle', array("name" => "Abonnee Blad-Tribune Betaald"));
			$this->_location_type 						= new stdClass();
			$this->_location_type->tribune 				= civicrm_api3('LocationType', 'getsingle', array("name" => "Tribuneadres"));
			$this->_custom_fields 						= new stdClass();
			$this->_custom_fields->group 				= civicrm_api3('CustomGroup', 'getsingle', array("name" => "Bezorggebieden"));
			$this->_custom_fields->name 				= civicrm_api3('CustomField', 'getsingle', array("name" => "Bezorggebied_naam", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->start_cijfer_range 	= civicrm_api3('CustomField', 'getsingle', array("name" => "start_cijfer_range", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->eind_cijfer_range 	= civicrm_api3('CustomField', 'getsingle', array("name" => "eind_cijfer_range", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->start_letter_range 	= civicrm_api3('CustomField', 'getsingle', array("name" => "start_letter_range", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->eind_letter_range 	= civicrm_api3('CustomField', 'getsingle', array("name" => "eind_letter_range", "custom_group_id" => $this->_custom_fields->group['id']));
			$this->_custom_fields->per_post 			= civicrm_api3('CustomField', 'getsingle', array("name" => "Per_Post", "custom_group_id" => $this->_custom_fields->group['id']));
		} catch (Exception $e) {
			echo "<h1>Er gaat iets mis!</h1>";
			echo $e;
			die();
		}
	}

	function preProcess() {
		$this->assign('reportTitle', ts('Membership Detail Report'));
		parent::preProcess();
	}

	function postProcess() {
		$this->beginPostProcess();
		$this->_columnHeaders = array(
			'organization_name' => array("title" => 'Naam'), 
			'street_address' => array("title" => 'Adres'), 
			'postal_code' => array("title" => 'Postcode'), 
			'city' => array("title" => 'Woonplaats'), 
			'aantal' => array("title" => 'Aantal Tribunes'),
			'pallet' => array("title" => 'Pallet'),
		);
		$query = "
			SELECT 'Stratumsedijk 17' as `street_address`, '5611 NA' as `postal_code`, 'Eindhoven' as `city`, '2' as `pallet`, COUNT(*) as `aantal`, 'Prevision' as `organization_name`
			FROM `civicrm_membership` as `cm`
			LEFT JOIN `civicrm_address` as `ca` ON `cm`.`contact_id` = `ca`.`contact_id`
			LEFT JOIN `civicrm_contact` as `cc` ON `cm`.`contact_id` = `cc`.`id`
			WHERE `cm`.`membership_type_id` IN (".$this->_memberships->proef['id'].",".$this->_memberships->gratis['id'].",".$this->_memberships->betaald['id'].") 
			AND `cm`.`status_id` IN (1,2)
			AND ((`cm`.`end_date` IS NULL) OR (`cm`.`end_date` >= now()))
			AND NOT EXISTS (
				SELECT 1
				FROM `".$this->_custom_fields->group['table_name']."` as `bzgarea`
				WHERE (
					(SUBSTR(REPLACE(`ca`.`postal_code`, ' ', ''), 1, 4) BETWEEN `bzgarea`.`".$this->_custom_fields->start_cijfer_range['column_name']."` AND `bzgarea`.`".$this->_custom_fields->eind_cijfer_range['column_name']."`)
						AND
					(SUBSTR(REPLACE(`ca`.`postal_code`, ' ', ''), -2) BETWEEN `bzgarea`.`".$this->_custom_fields->start_letter_range['column_name']."` AND `bzgarea`.`".$this->_custom_fields->eind_letter_range['column_name']."`)
				)
			)
			UNION
			SELECT 
			`ca`.`street_address`,`ca`.`postal_code`,`ca`.`city`, 
			IFNULL((
				SELECT 1 
				FROM `civicrm_address` as `casub`
				WHERE `casub`.`master_id` = `ca`.`id`
			), 0) as `pallet`,
			(
				SELECT SUM(`aantal`) FROM (	
					SELECT `entity_id`, `".$this->_custom_fields->name['column_name']."` as `bezorggebied`,
					(
						SELECT COUNT(*) from `civicrm_address` as `casub`
						LEFT JOIN `civicrm_membership` as `cm` ON `casub`.`contact_id` = `cm`.`contact_id`
						WHERE ( 
							(SUBSTR(REPLACE(`casub`.`postal_code`, ' ', ''), 1, 4) BETWEEN `bzgarea`.`".$this->_custom_fields->start_cijfer_range['column_name']."` AND `bzgarea`.`".$this->_custom_fields->eind_cijfer_range['column_name']."`)
								AND
							(SUBSTR(REPLACE(`casub`.`postal_code`, ' ', ''), -2) BETWEEN `bzgarea`.`".$this->_custom_fields->start_letter_range['column_name']."` AND `bzgarea`.`".$this->_custom_fields->eind_letter_range['column_name']."`)
						)
						AND `cm`.`membership_type_id` in (".$this->_memberships->proef['id'].",".$this->_memberships->gratis['id'].",".$this->_memberships->betaald['id'].")
						AND `cm`.`status_id` IN (1,2)
						AND (
							 (`cm`.`end_date` IS NULL)
							 OR
							 (`cm`.`end_date` >= now())
						)
					) as `aantal`
					FROM `".$this->_custom_fields->group['table_name']."` as `bzgarea`
				) as `sumTable`
				WHERE `sumTable`.`entity_id` IN (
					SELECT `contact_id` 
					FROM `civicrm_address` 
					WHERE `id` IN (
						SELECT IFNULL(`master_id`, `id`) 
						FROM `civicrm_address` 
						WHERE `contact_id` = `ca`.`contact_id`
						AND `location_type_id` = ".$this->_location_type->tribune['id']."
					)
					OR `master_id` IN (
						SELECT IFNULL(`master_id`, `id`) 
						FROM `civicrm_address` 
						WHERE `contact_id` = `ca`.`contact_id`
						AND `location_type_id` = ".$this->_location_type->tribune['id']."
					)
				)
			) as `aantal`,
			`cc`.`organization_name`
			FROM `civicrm_address` as `ca`
			LEFT JOIN `".$this->_custom_fields->group['table_name']."` as `cbzg` ON `ca`.`contact_id` = `cbzg`.`entity_id`
			LEFT JOIN `civicrm_contact` as `cc` ON `ca`.`contact_id` = `cc`.`id`
			WHERE `location_type_id` = ".$this->_location_type->tribune['id']."
			AND `master_id` IS NULL
			GROUP BY `ca`.`id`
			ORDER BY `pallet` DESC
		";
		$rows = array();
		$this->buildRows($query, $rows);
		$this->doTemplateAssignment($rows);
		$this->endPostProcess($rows);
	}
}