<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Users_Module_Model extends Vtiger_Module_Model {
	/**
	 * Function to get list view query for popup window
	 * @param <String> $sourceModule Parent module
	 * @param <String> $field parent fieldname
	 * @param <Integer> $record parent id
	 * @param <String> $listQuery
	 * @return <String> Listview Query
	 */
	public function getQueryByModuleField($sourceModule, $field, $record, $listQuery) {
		if($sourceModule == 'Users' && $field == 'reports_to_id') {
			$overRideQuery = $listQuery. " AND vtiger_users.id != ". $record;
			return $overRideQuery;
		}
	}

	/**
	 * Function searches the records in the module, if parentId & parentModule
	 * is given then searches only those records related to them.
	 * @param <String> $searchValue - Search value
	 * @param <Integer> $parentId - parent recordId
	 * @param <String> $parentModule - parent module name
	 * @return <Array of Users_Record_Model>
	 */
	public function searchRecord($searchValue, $parentId=false, $parentModule=false, $relatedModule=false) {
		if(!empty($searchValue)) {
			$db = PearDatabase::getInstance();

			$query = 'SELECT * FROM vtiger_users WHERE (first_name LIKE ? OR last_name LIKE ?) AND status = ?';
			$params = array("%$searchValue%", "%$searchValue%", 'Active');

			$result = $db->pquery($query, $params);
			$noOfRows = $db->num_rows($result);

			$matchingRecords = array();
			for($i=0; $i<$noOfRows; ++$i) {
				$row = $db->query_result_rowdata($result, $i);
				$modelClassName = Vtiger_Loader::getComponentClassName('Model', 'Record', 'Users');
				$recordInstance = new $modelClassName();
				$matchingRecords['Users'][$row['id']] = $recordInstance->setData($row)->setModuleFromInstance($this);
			}
			return $matchingRecords;
		}
	}
	
	/**
	 * Function returns the default column for Alphabetic search 
	 * @return <String> columnname
	 */
	public function getAlphabetSearchField(){
		return 'last_name';
	}

	/**
	 * Function to get the url for the Create Record view of the module
	 * @return <String> - url
	 */
	public function getCreateRecordUrl() {
		return 'index.php?module=' . $this->get('name') . '&parent=Settings&view=' . $this->getEditViewName();
	}

	public function checkDuplicateUser($userName){
		$db = PearDatabase::getInstance();
		
		$query = 'SELECT user_name FROM vtiger_users WHERE user_name = ?';
		$result = $db->pquery($query, array($userName));
		if($db->num_rows($result) > 0){
			return true;
		}
		return false;
	}
	
	/**
	 * Function to delete a given record model of the current module
	 * @param Vtiger_Record_Model $recordModel
	 */
	public function deleteRecord(Vtiger_Record_Model $recordModel) {
		$db = PearDatabase::getInstance();
		$moduleName = $this->get('name');
		$date_var = date('Y-m-d H:i:s');
        $query = "UPDATE vtiger_users SET status=?, date_modified=?, modified_user_id=? WHERE id=?";
        $db->pquery($query, array('Inactive', $adb->formatDate($date_var, true), $recordModel->getId(), $recordModel->getId()), true,"Error marking record deleted: ");
	}
	
	/**
	 * Function to get the url for list view of the module
	 * @return <string> - url
	 */
	public function getListViewUrl() {
		return 'index.php?module='.$this->get('name').'&parent=Settings&view='.$this->getListViewName();
	}
	
	/**
    * Function to update Base Currency of Product
    * @param- $_REQUEST array
    */
	public function updateBaseCurrency($currencyName) {
		$db = PearDatabase::getInstance();
		$result = $db->pquery('SELECT currency_code, currency_symbol FROM vtiger_currencies WHERE currency_name = ?', array($currencyName));
		$num_rows = $db->num_rows($result);
		if ($num_rows > 0) {
			$currency_code = decode_html($db->query_result($result, 0, 'currency_code'));
			$currency_symbol = decode_html($db->query_result($result, 0,'currency_symbol'));
		}

		//Updating Database
		$query = 'UPDATE vtiger_currency_info SET currency_name = ?, currency_code = ?, currency_symbol = ? WHERE id = ?';
		$params = array($currencyName, $currency_code, $currency_symbol, '1');
		$db->pquery($query, $params);
		
		$this->updateConfigFile($currencyName);
	}
	
	/**
    * Function to update Config file
    * @param- $_REQUEST array
    */
	public function updateConfigFile($currencyName) {
		$currencyName = '$currency_name = \''.$currencyName.'\'';

		//Updating in config inc file
		$filename = 'config.inc.php';
		if (file_exists($filename)) {
			$contents = file_get_contents($filename);
			$contents = str_replace('$currency_name = \'USA, Dollars\'', $currencyName, $contents);
			file_put_contents($filename, $contents);
		}
   }
   
   /**
	 * Function to get user setup status
	 * @return-is First User or not
	 */
	public static function insertEntryIntoCRMSetup($userId) {
		$db = PearDatabase::getInstance();

		//updating user setup status into database
		$insertQuery = 'INSERT INTO vtiger_crmsetup (userid, setup_status) VALUES (?, ?)';
		$db->pquery($insertQuery, array($userId, '1'));
	}
	
	/**
	 * Function to store the login history
	 * @param type $username
	 */
	public function saveLoginHistory($username){
		$adb = PearDatabase::getInstance();
		
		$userIPAddress = $_SERVER['REMOTE_ADDR'];
		$loginTime = date("Y-m-d H:i:s");
		$query = "Insert into vtiger_loginhistory (user_name, user_ip, logout_time, login_time, status) values (?,?,?,?,?)";
		$params = array($username, $userIPAddress, '0000-00-00 00:00:00',  $loginTime, 'Signed in');
		$adb->pquery($query, $params);
	}
	
	/**
	 * Function to store the logout history
	 * @param type $username
	 */
	public function saveLogoutHistory(){
		$adb = PearDatabase::getInstance();
		
		$userRecordModel = Users_Record_Model::getCurrentUserModel();
		$userIPAddress = $_SERVER['REMOTE_ADDR'];
		$outtime = date("Y-m-d H:i:s");
		
		$loginIdQuery = "SELECT max(login_id) AS login_id from vtiger_loginhistory where user_name=? and user_ip=?";
		$result = $adb->pquery($loginIdQuery, array($userRecordModel->get('user_name'), $userIPAddress));
		$loginid = $adb->query_result($result,0,"login_id");
		
		if (!empty($loginid)){
			$query = "Update vtiger_loginhistory set logout_time =?, status=? where login_id = ?";
			$result = $adb->pquery($query, array($outtime, 'Signed off', $loginid));
		}
	}
	
	/**
	 * Function to save packages info
	 * @param <type> $packagesList
	 */
	public static function savePackagesInfo($packagesList) {
		$adb = PearDatabase::getInstance();
		$packagesListFromDB = Users_CRMSetup::getPackagesList();
		$disabledModulesList = array();

		foreach ($packagesListFromDB as $packageName => $packageInfo) {
			if (!$packagesList[$packageName]) {
				$disabledModulesList = array_merge($disabledModulesList, array_keys($packageInfo['modules']));
			}
		}

		if ($disabledModulesList) {
			$updateQuery = 'UPDATE vtiger_tab SET presence = CASE WHEN name IN (' . generateQuestionMarks($disabledModulesList) . ') THEN 1 ';
			$updateQuery .= 'ELSE 0 END WHERE presence != 2 ';
		} else {
			$updateQuery = 'UPDATE vtiger_tab SET presence = 0 WHERE presence != 2';
		}
		
		$adb->pquery($updateQuery, $disabledModulesList);
	}
	
	/**
	* @return an array with the list of currencies which are available in source
	*/
    public function getCurrenciesList() {
	  $adb = PearDatabase::getInstance();

	   $currency_query = 'SELECT currency_name, currency_code, currency_symbol FROM vtiger_currencies ORDER BY currency_name';
	   $result = $adb->pquery($currency_query, array());
	   $num_rows = $adb->num_rows($result);
	   for($i = 0; $i<$num_rows; $i++) {
		   $currencyname = decode_html($adb->query_result($result, $i, 'currency_name'));
		   $currencycode = decode_html($adb->query_result($result, $i, 'currency_code'));
		   $currencysymbol = decode_html($adb->query_result($result, $i, 'currency_symbol'));
		   $currencies[$currencyname] = array($currencycode,$currencysymbol);
	   }
	   return $currencies;
   }

   /**
	* @return an array with the list of time zones which are availables in source
	*/
   public function getTimeZonesList() {
	   $adb = PearDatabase::getInstance();

	   $timezone_query = 'SELECT time_zone FROM vtiger_time_zone';
	   $result = $adb->pquery($timezone_query, array());
	   $num_rows = $adb->num_rows($result);
	   for($i = 0; $i<$num_rows; $i++) {
		   $time_zone = decode_html($adb->query_result($result, $i, 'time_zone'));
		   $time_zones_list[$time_zone] = $time_zone;
	   }
	   return $time_zones_list;
   }

   /**
	* @return an array with the list of languages which are available in source
	*/
   public function getLanguagesList() {
	   $adb = PearDatabase::getInstance();

	   $language_query = 'SELECT prefix, label FROM vtiger_language';
	   $result = $adb->pquery($language_query, array());
	   $num_rows = $adb->num_rows($result);
	   for($i = 0; $i<$num_rows; $i++) {
		   $lang_prefix = decode_html($adb->query_result($result, $i, 'prefix'));
		   $label = decode_html($adb->query_result($result, $i, 'label'));
		   $languages_list[$lang_prefix] = $label;
	   }
	   return $languages_list;
   }

}