<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

/**
 * TODO need to organize into classes based on functional grouping.
 */

class Vtiger_Functions {

	static function userIsAdministrator($user) {
		return (isset($user->is_admin) && $user->is_admin == 'on');
	}

	static function currentUserJSDateFormat($localformat) {
		global $current_user;
		if ($current_user->date_format == 'dd-mm-yyyy') {
			$dt_popup_fmt = "%d-%m-%Y";
		} elseif ($current_user->date_format == 'mm-dd-yyyy') {
			$dt_popup_fmt = "%m-%d-%Y";
		} elseif ($current_user->date_format == 'yyyy-mm-dd') {
			$dt_popup_fmt = "%Y-%m-%d";
		}
		return $dt_popup_fmt;
	}

	/**
	 * This function returns the date in user specified format.
	 * limitation is that mm-dd-yyyy and dd-mm-yyyy will be considered same by this API.
	 * As in the date value is on mm-dd-yyyy and user date format is dd-mm-yyyy then the mm-dd-yyyy
	 * value will be return as the API will be considered as considered as in same format.
	 * this due to the fact that this API tries to consider the where given date is in user date
	 * format. we need a better gauge for this case.
	 * @global Users $current_user
	 * @param Date $cur_date_val the date which should a changed to user date format.
	 * @return Date
	 */
	static function currentUserDisplayDate($value) {
		global $current_user;
		$dat_fmt = $current_user->date_format;
		if ($dat_fmt == '') {
			$dat_fmt = 'dd-mm-yyyy';
		}
		$date = new DateTimeField($value);
		return $date->getDisplayDate();
	}

	static function currentUserDisplayDateNew() {
		global $log, $current_user;
		$date = new DateTimeField(null);
		return $date->getDisplayDate($current_user);
	}

	// i18n
	static function getTranslatedString($str, $module = '') {
		return Vtiger_Language_Handler::getTranslatedString($str, $module);
	}

	// CURRENCY
	protected static $userIdCurrencyIdCache = array();

	static function userCurrencyId($userid) {
		global $adb;
		if (!isset(self::$userIdCurrencyIdCache[$userid])) {
			$result = $adb->pquery('SELECT id,currency_id FROM vtiger_users', array());
			while ($row = $adb->fetch_array($result)) {
				self::$userIdCurrencyIdCache[$row['id']] =
						$row['currency_id'];
			}
		}
		return self::$userIdCurrencyIdCache[$userid];
	}

	protected static $currencyInfoCache = array();

	protected static function getCurrencyInfo($currencyid) {
		global $adb;
		if (!isset(self::$currencyInfoCache[$currencyid])) {
			$result = $adb->pquery('SELECT * FROM vtiger_currency_info', array());
			while ($row = $adb->fetch_array($result)) {
				self::$currencyInfoCache[$row['id']] = $row;
			}
		}
		return self::$currencyInfoCache[$currencyid];
	}

	static function getCurrencyName($currencyid, $show_symbol = true) {
		$currencyInfo = self::getCurrencyInfo($currencyid);
		if ($show_symbol) {
			return sprintf("%s : %s", Vtiger_Deprecated::getTranslatedCurrencyString($currencyInfo['currency_name']), $currencyInfo['currency_symbol']);
		}
		return $currencyInfo['currency_name'];
	}

	static function getCurrencySymbolandRate($currencyid) {
		$currencyInfo = self::getCurrencyInfo($currencyid);
		$currencyRateSymbol = array(
			'rate' => $currencyInfo['conversion_rate'],
			'symbol'=>$currencyInfo['currency_symbol']
		);
		return $currencyRateSymbol;
	}

	// MODULE
	protected static $moduleIdNameCache = array();
	protected static $moduleNameIdCache = array();
	protected static $moduleIdDataCache = array();

	protected static function getBasicModuleInfo($mixed) {
		$id = $name = NULL;
		if (is_numeric($mixed)) $id = $mixed;
		else $name = $mixed;
		$reload = false;
		if ($name) {
			if (!isset(self::$moduleNameIdCache[$name])) {$reload = true;}
		} else if ($id) {
			if (!isset(self::$moduleIdNameCache[$id])) {$reload = true;}
		}
		if ($reload) {
			global $adb;
			$result = $adb->pquery('SELECT tabid, name, ownedby FROM vtiger_tab', array());
			while ($row = $adb->fetch_array($result)) {
				self::$moduleIdNameCache[$row['tabid']] = $row;
				self::$moduleNameIdCache[$row['name']]  = $row;
			}
		}
		return $id ? self::$moduleIdNameCache[$id] : self::$moduleNameIdCache[$name];
	}

	static function getModuleData($mixed) {
		$id = $name = NULL;
		if (is_numeric($mixed)) $id = $mixed;
		else $name = (string)$mixed;
		$reload = false;

		if ($name && !isset(self::$moduleNameIdCache[$name])) {$reload = true;}
		else if ($id && !isset(self::$moduleIdNameCache[$id])) {$reload = true;}
		else {
			if (!$id) $id = self::$moduleNameIdCache[$name]['tabid'];
			if (!isset(self::$moduleIdDataCache[$id])) { $reload = true; }
		}

		if ($reload) {
			global $adb;
			$result = $adb->pquery('SELECT * FROM vtiger_tab', array());
			while ($row = $adb->fetch_array($result)) {
				self::$moduleIdNameCache[$row['tabid']] = $row;
				self::$moduleNameIdCache[$row['name']]  = $row;
				self::$moduleIdDataCache[$row['tabid']] = $row;
			}
			if ($name && isset(self::$moduleNameIdCache[$name])) {
				$id = self::$moduleNameIdCache[$name]['tabid'];
			}
		}
		return $id ? self::$moduleIdDataCache[$id] : NULL;
	}

	static function getModuleId($name) {
		$moduleInfo = self::getBasicModuleInfo($name);
		return $moduleInfo ? $moduleInfo['tabid'] : NULL;
	}

	static function getModuleName($id) {
		$moduleInfo = self::getBasicModuleInfo($id);
		return $moduleInfo ? $moduleInfo['name'] : NULL;
	}

	static function getModuleOwner($name) {
		$moduleInfo = self::getBasicModuleInfo($name);
		return $moduleInfo ? $moduleInfo['ownedby'] : NULL;
	}

	protected static $moduleEntityCache = array();

	static function getEntityModuleInfo($mixed) {
		$name = NULL;
		if (is_numeric($mixed)) $name = self::getModuleName ($mixed);
		else $name = $mixed;

		if ($name && !isset(self::$moduleEntityCache[$name])) {
			global $adb;
			$result = $adb->pquery('SELECT fieldname,modulename,tablename,entityidfield,entityidcolumn from vtiger_entityname', array());
			while ($row = $adb->fetch_array($result)) {
				self::$moduleEntityCache[$row['modulename']] = $row;
			}
		}

		return isset(self::$moduleEntityCache[$name])?
			self::$moduleEntityCache[$name] : NULL;
	}

	static function getEntityModuleSQLColumnString($mixed) {
		$data = array();
		$info = self::getEntityModuleInfo($mixed);
		if ($info) {
			$data['tablename'] = $info['tablename'];
			$fieldnames = $info['fieldname'];
			if (strpos(',', $fieldnames) !== false) {
				$fieldnames = sprintf("concat(%s)", implode(",' ',", explode(',', $fieldnames)));
			}
			$data['fieldname'] = $fieldnames;
		}
		return $data;
	}

	static function getEntityModuleInfoFieldsFormatted($mixed) {
		$info = self::getEntityModuleInfo($mixed);
		$fieldnames = $info ? $info['fieldname'] : NULL;
		if ($fieldnames && stripos($fieldnames, ',') !== false) {
			$fieldnames = explode(',', $fieldnames);
		}
		$info['fieldname'] = $fieldnames;
		return $info;
	}

	// MODULE RECORD
	protected static $crmRecordIdMetadataCache = array();

	protected static function getCRMRecordMetadata($mixedid) {
		global $adb;

		$multimode = is_array($mixedid);

		$ids = $multimode ? $mixedid : array($mixedid);
		$missing = array();
		foreach ($ids as $id) {
			if ($id && !isset(self::$crmRecordIdMetadataCache[$id])) {
				$missing[] = $id;
			}
		}

		if ($missing) {
			$sql = sprintf("SELECT crmid, setype, label FROM vtiger_crmentity WHERE %s", implode(' OR ', array_fill(0, count($missing), 'crmid=?')));
			$result = $adb->pquery($sql, $missing);
			while ($row = $adb->fetch_array($result)) {
				self::$crmRecordIdMetadataCache[$row['crmid']] = $row;
			}
		}

		$result = array();
		foreach ($ids as $id) {
			if (isset(self::$crmRecordIdMetadataCache[$id])) {
				$result[$id] = self::$crmRecordIdMetadataCache[$id];
			} else {
				$result[$id] = NULL;
			}
		}

		return $multimode?  $result : array_shift($result);
	}

	static function getCRMRecordType($id) {
		$metadata = self::getCRMRecordMetadata($id);
		return $metadata ? $metadata['setype'] : NULL;
	}

	static function getCRMRecordLabel($id, $default='') {
		$metadata = self::getCRMRecordMetadata($id);
		return $metadata ? $metadata['label'] : $default;
	}

	static function getUserRecordLabel($id, $default='') {
		$labels = self::getCRMRecordLabels('Users', $id);
		return isset($labels[$id])? $labels[$id] : $default;
	}

	static function getGroupRecordLabel($id, $default='') {
		$labels = self::getCRMRecordLabels('Groups', $id);
		return isset($labels[$id])? $labels[$id] : $default;
	}

	static function getCRMRecordLabels($module, $ids) {
		if (!is_array($ids)) $ids = array($ids);

		if ($module == 'Users' || $module == 'Groups') {
			// TODO Cache separately?
			return self::computeCRMRecordLabels($module, $ids);
		} else {
			$metadatas = self::getCRMRecordMetadata($ids);
			$result = array();
			foreach ($metadatas as $data) {
				$result[$data['crmid']] = $data['label'];
			}
			return $result;
		}
	}

	static function updateCRMRecordLabel($module, $id, $label) {
		global $adb;
		$labelInfo = self::computeCRMRecordLabels($module, $id);
		if ($labelInfo) {
			$label = decode_html($labelInfo[$id]);
			$adb->pquery('UPDATE vtiger_crmentity SET label=? WHERE crmid=?', array($label, $id));
			self::$crmRecordIdMetadataCache[$id] = array(
				'setype' => $module,
				'crmid'  => $id,
				'label'  => $labelInfo[$id]
			);
		}
	}

	static function getOwnerRecordLabel($id) {
		$result = self::getOwnerRecordLabels($id);
		return $result ? array_shift($result) : NULL;
	}

	static function getOwnerRecordLabels($ids) {
		if (!is_array($ids)) $ids = array($ids);

		$nameList = array();
		if ($ids) {
			$nameList = self::getCRMRecordLabels('Users', $ids);
			$groups = array();
			$diffIds = array_diff($ids, array_keys($nameList));
			if ($diffIds) {
				$groups = self::getCRMRecordLabels('Groups', array_values($diffIds));
			}
			if ($groups) {
				foreach ($groups as $id => $label) {
					$nameList[$id] = $label;
				}
			}
		}

		return $nameList;
	}

	static function computeCRMRecordLabels($module, $ids) {
		global $adb;

		if (!is_array($ids)) $ids = array($ids);

		if ($module == 'Events') {
			$module = 'Calendar';
		}

		if ($module) {
			$entityDisplay = array();

			if ($ids) {

				if ($module == 'Groups') {
					$metainfo = array('tablename' => 'vtiger_groups','entityidfield' => 'groupid','fieldname' => 'groupname');
				} else {
					$metainfo = self::getEntityModuleInfo($module);
				}

				$table = $metainfo['tablename'];
				$idcolumn = $metainfo['entityidfield'];
				$columns  = explode(',', $metainfo['fieldname']);

				// NOTE: Ignore field-permission check for non-admin (to compute record label).
				$columnString = count($columns) < 2? $columns[0] :
					sprintf("concat(%s)", implode(",' ',", $columns));

				$sql = sprintf('SELECT %s AS label, %s AS id FROM %s WHERE %s IN (%s)',
						$columnString, $idcolumn, $table, $idcolumn, generateQuestionMarks($ids));

				$result = $adb->pquery($sql, $ids);

				while ($row = $adb->fetch_array($result)) {
					$entityDisplay[$row['id']] = $row['label'];
				}
			}

			return $entityDisplay;
		}
	}

	protected static $groupIdNameCache = array();

	static function getGroupName($id) {
		global $adb;
		if (!self::$groupIdNameCache[$id]) {
			$result = $adb->pquery('SELECT groupid, groupname FROM vtiger_groups');
			while ($row = $adb->fetch_array($result)) {
				self::$groupIdNameCache[$row['groupid']] = $row['groupname'];
			}
		}
		$result = array();
		if (isset(self::$groupIdNameCache[$id])) {
			$result[] = decode_html(self::$groupIdNameCache[$id]);
			$result[] = $id;
		}
		return $result;
	}

	protected static $userIdNameCache = array();

	static function getUserName($id) {
		global $adb;
		if (!self::$userIdNameCache[$id]) {
			$result = $adb->pquery('SELECT id, user_name FROM vtiger_users');
			while ($row = $adb->fetch_array($result)) {
				self::$userIdNameCache[$row['id']] = $row['user_name'];
			}
		}
		return (isset(self::$userIdNameCache[$id])) ? self::$userIdNameCache[$id] : NULL;
	}

	protected static $moduleFieldInfoByNameCache = array();

	static function getModuleFieldInfos($mixed) {
		global $adb;

		$moduleInfo = self::getBasicModuleInfo($mixed);
		$module = $moduleInfo['name'];

		if ($module && !isset(self::$moduleFieldInfoByNameCache[$module])) {
			$result =
				($module == 'Calendar')?
				$adb->pquery('SELECT * FROM vtiger_field WHERE tabid=? OR tabid=?', array(9, 16)) :
				$adb->pquery('SELECT * FROM vtiger_field WHERE tabid=?', array(self::getModuleId($module)));

			self::$moduleFieldInfoByNameCache[$module] = array();
			while ($row = $adb->fetch_array($result)) {
				self::$moduleFieldInfoByNameCache[$module][$row['fieldname']] = $row;
			}
		}
		return isset(self::$moduleFieldInfoByNameCache[$module]) ? self::$moduleFieldInfoByNameCache[$module] : NULL;
	}

	static function getModuleFieldInfoWithId($fieldid) {
		global $adb;
		$result = $adb->pquery('SELECT * FROM vtiger_field WHERE fieldid=?', array($fieldid));
		return ($adb->num_rows($result))? $adb->fetch_array($result) : NULL;
	}

	static function getModuleFieldInfo($moduleid, $mixed) {
		$field = NULL;
		if (empty($moduleid) && is_numeric($mixed)) {
			$field = self::getModuleFieldInfoWithId($mixed);
		} else {
			$fieldsInfo = self::getModuleFieldInfos($moduleid);
			if ($fieldsInfo) {
				if (is_numeric($mixed)) {
					foreach ($fieldsInfo as $name => $row) {
						if ($row['fieldid'] == $mixed) {
							$field = $row;
							break;
						}
					}
				} else {
					$field = isset($fieldsInfo[$mixed]) ? $fieldsInfo[$mixed] : NULL;
				}
			}
		}
		return $field;
	}

	static function getModuleFieldId($moduleid, $mixed, $onlyactive=true) {
		$field = self::getModuleFieldInfo($moduleid, $mixed, $onlyactive);

		if ($field) {
			if ($onlyactive && ($field['presence'] != '0' && $field['presence'] != '2')) {
				$field = NULL;
			}
		}
		return $field ? $field['fieldid'] : false;
	}


	// Utility
	static function formatDecimal($value){
		$fld_value = explode('.', $value);
		if($fld_value[1] != ''){
			$fld_value = rtrim($value, '0');
			$value = rtrim($fld_value, '.');
		}
		return $value;
	}

	static function fromHTML($string, $encode=true) {
		if (is_string($string)) {
			if (preg_match('/(script).*(\/script)/i', $string)) {
				$string = preg_replace(array('/</', '/>/', '/"/'), array('&lt;', '&gt;', '&quot;'), $string);
			}
		}
		return $string;
	}

	static function fromHTML_FCK($string) {
		if (is_string($string)) {
			if (preg_match('/(script).*(\/script)/i', $string)) {
				$string = str_replace('script', '', $string);
			}
		}
		return $string;
	}

	static function fromHTML_Popup($string, $encode = true) {
		$popup_toHtml = array(
			'"' => '&quot;',
			"'" => '&#039;',
		);
		//if($encode && is_string($string))$string = html_entity_decode($string, ENT_QUOTES);
		if ($encode && is_string($string)) {
			$string = addslashes(str_replace(array_values($popup_toHtml), array_keys($popup_toHtml), $string));
		}
		return $string;
	}

	static function br2nl($str) {
		$str = preg_replace("/(\r\n)/", "\\r\\n", $str);
		$str = preg_replace("/'/", " ", $str);
		$str = preg_replace("/\"/", " ", $str);
		return $str;
	}

	static function suppressHTMLTags($string) {
		return preg_replace(array('/</', '/>/', '/"/'), array('&lt;', '&gt;', '&quot;'), $string);
	}

	static function getInventoryTermsAndCondition() {
		global $adb;
		$sql = "select tandc from vtiger_inventory_tandc";
		$result = $adb->pquery($sql, array());
		$tandc = $adb->query_result($result, 0, "tandc");
		return $tandc;
	}

	static function initStorageFileDirectory() {
		$filepath = 'storage/';

		$year  = date('Y');
		$month = date('F');
		$day   = date('j');
		$week  = '';

		if (!is_dir($filepath . $year)) {
			//create new folder
			mkdir($filepath . $year);
		}

		if (!is_dir($filepath . $year . "/" . $month)) {
			//create new folder
			mkdir($filepath . "$year/$month");
		}

		if ($day > 0 && $day <= 7)
			$week = 'week1';
		elseif ($day > 7 && $day <= 14)
			$week = 'week2';
		elseif ($day > 14 && $day <= 21)
			$week = 'week3';
		elseif ($day > 21 && $day <= 28)
			$week = 'week4';
		else
			$week = 'week5';

		if (!is_dir($filepath . $year . "/" . $month . "/" . $week)) {
			//create new folder
			mkdir($filepath . "$year/$month/$week");
		}

		$filepath = $filepath . $year . "/" . $month . "/" . $week . "/";

		return $filepath;
	}

	static function validateImage($file_details) {
		global $app_strings;
		$file_type_details = explode("/", $file_details['type']);
		$filetype = $file_type_details['1'];
		if (!empty($filetype))
			$filetype = strtolower($filetype);
		if (($filetype == "jpeg" ) || ($filetype == "png") || ($filetype == "jpg" ) || ($filetype == "pjpeg" ) || ($filetype == "x-png") || ($filetype == "gif") || ($filetype == 'bmp')) {
			$saveimage = 'true';
		} else {
			$saveimage = 'false';
			$_SESSION['image_type_error'] .= "<br> &nbsp;&nbsp;<b>" . $file_details[name] . "</b>" . $app_strings['MSG_IS_NOT_UPLOADED'];
		}
		return $saveimage;
	}

	static function getMergedDescription($description, $id, $parent_type) {
		global $current_user;
		$token_data_pair = explode('$', $description);
		$emailTemplate = new EmailTemplate($parent_type, $description, $id, $current_user);
		$description = $emailTemplate->getProcessedDescription();
		$tokenDataPair = explode('$', $description);
		$fields = Array();
		for ($i = 1; $i < count($token_data_pair); $i+=2) {
			$module = explode('-', $tokenDataPair[$i]);
			$fields[$module[0]][] = $module[1];
		}
		if (is_array($fields['custom']) && count($fields['custom']) > 0) {
			$description = self::getMergedDescriptionCustomVars($fields, $description);
		}
		return $description;
	}

	static function getMergedDescriptionCustomVars($fields, $description) {
		foreach ($fields['custom'] as $columnname) {
			$token_data = '$custom-' . $columnname . '$';
			$token_value = '';
			switch ($columnname) {
				case 'currentdate': $token_value = date("F j, Y");
					break;
				case 'currenttime': $token_value = date("G:i:s T");
					break;
			}
			$description = str_replace($token_data, $token_value, $description);
		}
		return $description;
	}

	static function getSingleFieldValue($tablename, $fieldname, $idname, $id) {
		global $adb;
		$fieldval = $adb->query_result($adb->pquery("select $fieldname from $tablename where $idname = ?", array($id)), 0, $fieldname);
		return $fieldval;
	}

	static function getRecurringObjValue() {
		$recurring_data = array();
		if (isset($_REQUEST['recurringtype']) && $_REQUEST['recurringtype'] != null && $_REQUEST['recurringtype'] != '--None--') {
			if (!empty($_REQUEST['date_start'])) {
				$startDate = $_REQUEST['date_start'];
			}
			if (!empty($_REQUEST['calendar_repeat_limit_date'])) {
				$endDate = $_REQUEST['calendar_repeat_limit_date'];
			} elseif (isset($_REQUEST['due_date']) && $_REQUEST['due_date'] != null) {
				$endDate = $_REQUEST['due_date'];
			}
			if (!empty($_REQUEST['time_start'])) {
				$startTime = $_REQUEST['time_start'];
			}
			if (!empty($_REQUEST['time_end'])) {
				$endTime = $_REQUEST['time_end'];
			}

			$recurring_data['startdate'] = $startDate;
			$recurring_data['starttime'] = $startTime;
			$recurring_data['enddate'] = $endDate;
			$recurring_data['endtime'] = $endTime;

			$recurring_data['type'] = $_REQUEST['recurringtype'];
			if ($_REQUEST['recurringtype'] == 'Weekly') {
				if (isset($_REQUEST['sun_flag']) && $_REQUEST['sun_flag'] != null)
					$recurring_data['sun_flag'] = true;
				if (isset($_REQUEST['mon_flag']) && $_REQUEST['mon_flag'] != null)
					$recurring_data['mon_flag'] = true;
				if (isset($_REQUEST['tue_flag']) && $_REQUEST['tue_flag'] != null)
					$recurring_data['tue_flag'] = true;
				if (isset($_REQUEST['wed_flag']) && $_REQUEST['wed_flag'] != null)
					$recurring_data['wed_flag'] = true;
				if (isset($_REQUEST['thu_flag']) && $_REQUEST['thu_flag'] != null)
					$recurring_data['thu_flag'] = true;
				if (isset($_REQUEST['fri_flag']) && $_REQUEST['fri_flag'] != null)
					$recurring_data['fri_flag'] = true;
				if (isset($_REQUEST['sat_flag']) && $_REQUEST['sat_flag'] != null)
					$recurring_data['sat_flag'] = true;
			}
			elseif ($_REQUEST['recurringtype'] == 'Monthly') {
				if (isset($_REQUEST['repeatMonth']) && $_REQUEST['repeatMonth'] != null)
					$recurring_data['repeatmonth_type'] = $_REQUEST['repeatMonth'];
				if ($recurring_data['repeatmonth_type'] == 'date') {
					if (isset($_REQUEST['repeatMonth_date']) && $_REQUEST['repeatMonth_date'] != null)
						$recurring_data['repeatmonth_date'] = $_REQUEST['repeatMonth_date'];
					else
						$recurring_data['repeatmonth_date'] = 1;
				}
				elseif ($recurring_data['repeatmonth_type'] == 'day') {
					$recurring_data['repeatmonth_daytype'] = $_REQUEST['repeatMonth_daytype'];
					switch ($_REQUEST['repeatMonth_day']) {
						case 0 :
							$recurring_data['sun_flag'] = true;
							break;
						case 1 :
							$recurring_data['mon_flag'] = true;
							break;
						case 2 :
							$recurring_data['tue_flag'] = true;
							break;
						case 3 :
							$recurring_data['wed_flag'] = true;
							break;
						case 4 :
							$recurring_data['thu_flag'] = true;
							break;
						case 5 :
							$recurring_data['fri_flag'] = true;
							break;
						case 6 :
							$recurring_data['sat_flag'] = true;
							break;
					}
				}
			}
			if (isset($_REQUEST['repeat_frequency']) && $_REQUEST['repeat_frequency'] != null)
				$recurring_data['repeat_frequency'] = $_REQUEST['repeat_frequency'];

			$recurObj = RecurringType::fromUserRequest($recurring_data);
			return $recurObj;
		}
	}

	static function getTicketComments($ticketid) {
		global $adb;
		$moduleName = getSalesEntityType($ticketid);
		$commentlist = '';
		$sql = "SELECT commentcontent FROM vtiger_modcomments WHERE related_to = ?";
		$result = $adb->pquery($sql, array($ticketid));
		for ($i = 0; $i < $adb->num_rows($result); $i++) {
			$comment = $adb->query_result($result, $i, 'commentcontent');
			if ($comment != '') {
				$commentlist .= '<br><br>' . $comment;
			}
		}
		if ($commentlist != '')
			$commentlist = '<br><br>' . getTranslatedString("The comments are", $moduleName) . ' : ' . $commentlist;
		return $commentlist;
	}

	static function generateRandomPassword() {
		$salt = "abcdefghijklmnopqrstuvwxyz0123456789";
		srand((double) microtime() * 1000000);
		$i = 0;
		while ($i <= 7) {
			$num = rand() % 33;
			$tmp = substr($salt, $num, 1);
			$pass = $pass . $tmp;
			$i++;
		}
		return $pass;
	}

	static function getTagCloudView($id = "") {
		global $adb;
		if ($id == '') {
			$tag_cloud_status = 1;
		} else {
			$query = "select visible from vtiger_homestuff where userid=? and stufftype='Tag Cloud'";
			$tag_cloud_status = $adb->query_result($adb->pquery($query, array($id)), 0, 'visible');
		}

		if ($tag_cloud_status == 0) {
			$tag_cloud_view = 'true';
		} else {
			$tag_cloud_view = 'false';
		}
		return $tag_cloud_view;
	}

	static function transformFieldTypeOfData($table_name, $column_name, $type_of_data) {
		$field = $table_name . ":" . $column_name;
		//Add the field details in this array if you want to change the advance filter field details

		static $new_field_details = Array(
			//Contacts Related Fields
			"vtiger_contactdetails:accountid" => "V",
			"vtiger_contactsubdetails:birthday" => "D",
			"vtiger_contactdetails:email" => "V",
			"vtiger_contactdetails:secondaryemail" => "V",
			//Potential Related Fields
			"vtiger_potential:campaignid" => "V",
			//Account Related Fields
			"vtiger_account:parentid" => "V",
			"vtiger_account:email1" => "V",
			"vtiger_account:email2" => "V",
			//Lead Related Fields
			"vtiger_leaddetails:email" => "V",
			"vtiger_leaddetails:secondaryemail" => "V",
			//Documents Related Fields
			"vtiger_senotesrel:crmid" => "V",
			//Calendar Related Fields
			"vtiger_seactivityrel:crmid" => "V",
			"vtiger_seactivityrel:contactid" => "V",
			"vtiger_recurringevents:recurringtype" => "V",
			//HelpDesk Related Fields
			"vtiger_troubletickets:parent_id" => "V",
			"vtiger_troubletickets:product_id" => "V",
			//Product Related Fields
			"vtiger_products:discontinued" => "C",
			"vtiger_products:vendor_id" => "V",
			"vtiger_products:parentid" => "V",
			//Faq Related Fields
			"vtiger_faq:product_id" => "V",
			//Vendor Related Fields
			"vtiger_vendor:email" => "V",
			//Quotes Related Fields
			"vtiger_quotes:potentialid" => "V",
			"vtiger_quotes:inventorymanager" => "V",
			"vtiger_quotes:accountid" => "V",
			//Purchase Order Related Fields
			"vtiger_purchaseorder:vendorid" => "V",
			"vtiger_purchaseorder:contactid" => "V",
			//SalesOrder Related Fields
			"vtiger_salesorder:potentialid" => "V",
			"vtiger_salesorder:quoteid" => "V",
			"vtiger_salesorder:contactid" => "V",
			"vtiger_salesorder:accountid" => "V",
			//Invoice Related Fields
			"vtiger_invoice:salesorderid" => "V",
			"vtiger_invoice:contactid" => "V",
			"vtiger_invoice:accountid" => "V",
			//Campaign Related Fields
			"vtiger_campaign:product_id" => "V",
			//Related List Entries(For Report Module)
			"vtiger_activityproductrel:activityid" => "V",
			"vtiger_activityproductrel:productid" => "V",
			"vtiger_campaigncontrel:campaignid" => "V",
			"vtiger_campaigncontrel:contactid" => "V",
			"vtiger_campaignleadrel:campaignid" => "V",
			"vtiger_campaignleadrel:leadid" => "V",
			"vtiger_cntactivityrel:contactid" => "V",
			"vtiger_cntactivityrel:activityid" => "V",
			"vtiger_contpotentialrel:contactid" => "V",
			"vtiger_contpotentialrel:potentialid" => "V",
			"vtiger_pricebookproductrel:pricebookid" => "V",
			"vtiger_pricebookproductrel:productid" => "V",
			"vtiger_seactivityrel:crmid" => "V",
			"vtiger_seactivityrel:activityid" => "V",
			"vtiger_senotesrel:crmid" => "V",
			"vtiger_senotesrel:notesid" => "V",
			"vtiger_seproductsrel:crmid" => "V",
			"vtiger_seproductsrel:productid" => "V",
			"vtiger_seticketsrel:crmid" => "V",
			"vtiger_seticketsrel:ticketid" => "V",
			"vtiger_vendorcontactrel:vendorid" => "V",
			"vtiger_vendorcontactrel:contactid" => "V",
			"vtiger_pricebook:currency_id" => "V",
		);

		//If the Fields details does not match with the array, then we return the same typeofdata
		if (isset($new_field_details[$field])) {
			$type_of_data = $new_field_details[$field];
		}
		return $type_of_data;
	}

	static function getPickListValuesFromTableForRole($tablename, $roleid) {
		global $adb;
		$query = "select $tablename from vtiger_$tablename inner join vtiger_role2picklist on vtiger_role2picklist.picklistvalueid = vtiger_$tablename.picklist_valueid where roleid=? and picklistid in (select picklistid from vtiger_picklist) order by sortid";
		$result = $adb->pquery($query, array($roleid));
		$fldVal = Array();
		while ($row = $adb->fetch_array($result)) {
			$fldVal [] = $row[$tablename];
		}
		return $fldVal;
	}

	static function getActivityType($id) {
		global $adb;
		$query = "select activitytype from vtiger_activity where activityid=?";
		$res = $adb->pquery($query, array($id));
		$activity_type = $adb->query_result($res, 0, "activitytype");
		return $activity_type;
	}

	static function getInvoiceStatus($id) {
		global $adb;
		$result = $adb->pquery("SELECT invoicestatus FROM vtiger_invoice where invoiceid=?", array($id));
		$invoiceStatus = $adb->query_result($result,0,'invoicestatus');
		return $invoiceStatus;
	}

	static function mkCountQuery($query) {
		// Remove all the \n, \r and white spaces to keep the space between the words consistent.
		// This is required for proper pattern matching for words like ' FROM ', 'ORDER BY', 'GROUP BY' as they depend on the spaces between the words.
		$query = preg_replace("/[\n\r\s]+/"," ",$query);

		//Strip of the current SELECT fields and replace them by "select count(*) as count"
		// Space across FROM has to be retained here so that we do not have a clash with string "from" found in select clause
		$query = "SELECT count(*) AS count ".substr($query, stripos($query,' FROM '),strlen($query));

		//Strip of any "GROUP BY" clause
		if(stripos($query,'GROUP BY') > 0)
		$query = substr($query, 0, stripos($query,'GROUP BY'));

		//Strip of any "ORDER BY" clause
		if(stripos($query,'ORDER BY') > 0)
		$query = substr($query, 0, stripos($query,'ORDER BY'));

		return $query;
	}
}