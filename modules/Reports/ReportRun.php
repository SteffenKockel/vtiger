<?php
/*+********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/
global $calpath;
global $app_strings,$mod_strings;
global $theme;
global $log;

$theme_path="themes/".$theme."/";
$image_path=$theme_path."images/";
require_once('include/database/PearDatabase.php');
require_once('data/CRMEntity.php');
require_once("modules/Reports/Reports.php");
require_once 'modules/Reports/ReportUtils.php';
require_once("vtlib/Vtiger/Module.php");
require_once('modules/Vtiger/helpers/Util.php');

/*
 * Helper class to determine the associative dependency between tables.
 */
class ReportRunQueryDependencyMatrix {
	protected $matrix = array();
	protected $computedMatrix = null;

	function setDependency($table, array $dependents) {
		$this->matrix[$table] = $dependents;
	}

	function addDependency($table, $dependent) {
		if (isset($this->matrix[$table]) && !in_array($dependent, $this->matrix[$table])) {
			$this->matrix[$table][] = $dependent;
		} else {
			$this->setDependency($table, array($dependent));
		}
	}

	function getDependents($table) {
		$this->computeDependencies();
		return isset($this->computedMatrix[$table])? $this->computedMatrix[$table] : array();
	}

	protected function computeDependencies() {
		if ($this->computedMatrix !== null) return;

		$this->computedMatrix = array();
		foreach ($this->matrix as $key => $values) {
			$this->computedMatrix[$key] =
				$this->computeDependencyForKey($key, $values);
		}
	}
	protected function computeDependencyForKey($key, $values) {
		$merged = array();
		foreach ($values as $value) {
			$merged[] = $value;
			if (isset($this->matrix[$value])) {
				$merged = array_merge($merged, $this->matrix[$value]);
			}
		}
		return $merged;
	}
}

class ReportRunQueryPlanner {
	// Turn-off the query planning to revert back - backward compatiblity
	protected $disablePlanner = false;

	protected $tables = array();
	protected $tempTables = array();
	protected $tempTablesInitialized = false;

	// Turn-off in case the query result turns-out to be wrong.
	protected $allowTempTables = true;
	protected $tempTablePrefix = 'vtiger_reptmptbl_';
	protected static $tempTableCounter   = 0;
	protected $registeredCleanup = false;


        function addTable($table) {
		$this->tables[$table] = $table;
	}

	function requireTable($table, $dependencies=null) {

		if ($this->disablePlanner) {
			return true;
		}

		if (isset($this->tables[$table])) {
			return true;
		}
		if (is_array($dependencies)) {
			foreach ($dependencies as $dependentTable) {
				if (isset($this->tables[$dependentTable])) {
					return true;
				}
			}
		} else if ($dependencies instanceof ReportRunQueryDependencyMatrix) {
			$dependents = $dependencies->getDependents($table);
			if ($dependents) {
				return count(array_intersect($this->tables, $dependents)) > 0;
			}
		}
		return false;
	}

	function getTables() {
		return $this->tables;
	}

	function newDependencyMatrix() {
		return new ReportRunQueryDependencyMatrix();
	}

	function registerTempTable($query, $keyColumns) {
		if ($this->allowTempTables && !$this->disablePlanner) {
			global $current_user;

			$keyColumns = is_array($keyColumns)? array_unique($keyColumns) : array($keyColumns);

			// Minor optimization to avoid re-creating similar temporary table.
			$uniqueName = NULL;
			foreach ($this->tempTables as $tmpUniqueName => $tmpTableInfo) {
				if (strcasecmp($query, $tmpTableInfo['query']) === 0) {
					// Capture any additional key columns
					$tmpTableInfo['keycolumns'] = array_unique(array_merge($tmpTableInfo['keycolumns'], $keyColumns));
					$uniqueName = $tmpUniqueName;
					break;
				}
			}

			// Nothing found?
			if ($uniqueName === NULL) {
			// TODO Adding randomness in name to avoid concurrency
			// even when same-user opens the report multiple instances at same-time.
			$uniqueName = $this->tempTablePrefix .
					str_replace('.', '', uniqid($current_user->id , true)) . (self::$tempTableCounter++);

			$this->tempTables[$uniqueName] = array(
				'query' => $query,
					'keycolumns' => is_array($keyColumns)? array_unique($keyColumns) : array($keyColumns),
				);
			}

			return $uniqueName;
		}
		return "($query)";
	}

	function initializeTempTables() {
		global $adb;

		$oldDieOnError = $adb->dieOnError;
		$adb->dieOnError = false; // If query planner is re-used there could be attempt for temp table...
		foreach ($this->tempTables as $uniqueName => $tempTableInfo) {
			$query1 = sprintf('CREATE TEMPORARY TABLE %s AS %s', $uniqueName, $tempTableInfo['query']);
			$adb->pquery($query1, array());

			$keyColumns = $tempTableInfo['keycolumns'];
			foreach ($keyColumns as $keyColumn) {
				$query2 = sprintf('ALTER TABLE %s ADD INDEX (%s)', $uniqueName, $keyColumn);
			$adb->pquery($query2, array());
			}
		}

		$adb->dieOnError = $oldDieOnError;

		// Trigger cleanup of temporary tables when the execution of the request ends.
		// NOTE: This works better than having in __destruct
		// (as the reference to this object might end pre-maturely even before query is executed)
		if (!$this->registeredCleanup) {
			register_shutdown_function(array($this, 'cleanup'));
			// To avoid duplicate registration on this instance.
			$this->registeredCleanup = true;
		}

	}

	function cleanup() {
		global $adb;

		$oldDieOnError = $adb->dieOnError;
		$adb->dieOnError = false; // To avoid abnormal termination during shutdown...
		foreach ($this->tempTables as $uniqueName => $tempTableInfo) {
			$adb->pquery('DROP TABLE ' . $uniqueName, array());
		}
		$adb->dieOnError = $oldDieOnError;

		$this->tempTables = array();
	}
}

class ReportRun extends CRMEntity
{
	// Maximum rows that should be emitted in HTML view.
	static $HTMLVIEW_MAX_ROWS = 1000;

	var $reportid;
	var $primarymodule;
	var $secondarymodule;
	var $orderbylistsql;
	var $orderbylistcolumns;

	var $selectcolumns;
	var $groupbylist;
	var $reporttype;
	var $reportname;
	var $totallist;

	var $_groupinglist  = false;
	var $_columnslist    = false;
	var $_stdfilterlist = false;
	var $_columnstotallist = false;
	var $_advfiltersql = false;

    // All UItype 72 fields are added here so that in reports the values are append currencyId::value
	var $append_currency_symbol_to_value = array('Products_Unit_Price','Services_Price',
						'Invoice_Total', 'Invoice_Sub_Total', 'Invoice_Pre_Tax_Total', 'Invoice_S&H_Amount', 'Invoice_Discount_Amount', 'Invoice_Adjustment',
						'Quotes_Total', 'Quotes_Sub_Total', 'Quotes_Pre_Tax_Total', 'Quotes_S&H_Amount', 'Quotes_Discount_Amount', 'Quotes_Adjustment',
						'SalesOrder_Total', 'SalesOrder_Sub_Total', 'SalesOrder_Pre_Tax_Total', 'SalesOrder_S&H_Amount', 'SalesOrder_Discount_Amount', 'SalesOrder_Adjustment',
						'PurchaseOrder_Total', 'PurchaseOrder_Sub_Total', 'PurchaseOrder_Pre_Tax_Total', 'PurchaseOrder_S&H_Amount', 'PurchaseOrder_Discount_Amount', 'PurchaseOrder_Adjustment',
                        'Invoice_Received','PurchaseOrder_Paid','Invoice_Balance','PurchaseOrder_Balance'
						);
	var $ui10_fields = array();
	var $ui101_fields = array();
	var $groupByTimeParent = array( 'Quarter'=>array('Year'),
									'Month'=>array('Year')
								);

	var $queryPlanner = null;


    protected static $instances = false;
	// Added to support line item fields calculation, if line item fields
	// are selected then module fields cannot be selected and vice versa
	var $lineItemFieldsInCalculation = false;

        /** Function to set reportid,primarymodule,secondarymodule,reporttype,reportname, for given reportid
	 *  This function accepts the $reportid as argument
	 *  It sets reportid,primarymodule,secondarymodule,reporttype,reportname for the given reportid
         *  To ensure single-instance is present for $reportid
         *  as we optimize using ReportRunPlanner and setup temporary tables.
	 */

        function ReportRun($reportid)
	{
		$oReport = new Reports($reportid);
		$this->reportid = $reportid;
		$this->primarymodule = $oReport->primodule;
		$this->secondarymodule = $oReport->secmodule;
		$this->reporttype = $oReport->reporttype;
		$this->reportname = $oReport->reportname;
		$this->queryPlanner = new ReportRunQueryPlanner();
	}

        public static function getInstance($reportid) {
            if (!isset(self::$instances[$reportid])) {
                self::$instances[$reportid] = new ReportRun($reportid);
            }
            return self::$instances[$reportid];
        }

	/** Function to get the columns for the reportid
	 *  This function accepts the $reportid and $outputformat (optional)
	 *  This function returns  $columnslist Array($tablename:$columnname:$fieldlabel:$fieldname:$typeofdata=>$tablename.$columnname As Header value,
	 *					      $tablename1:$columnname1:$fieldlabel1:$fieldname1:$typeofdata1=>$tablename1.$columnname1 As Header value,
	 *					      					|
 	 *					      $tablenamen:$columnnamen:$fieldlabeln:$fieldnamen:$typeofdatan=>$tablenamen.$columnnamen As Header value
	 *				      	     )
	 *
	 */
	function getQueryColumnsList($reportid,$outputformat='')
	{
		// Have we initialized information already?
		if($this->_columnslist !== false) {
			return $this->_columnslist;
		}

		global $adb;
		global $modules;
		global $log,$current_user,$current_language;
		$ssql = "select vtiger_selectcolumn.* from vtiger_report inner join vtiger_selectquery on vtiger_selectquery.queryid = vtiger_report.queryid";
		$ssql .= " left join vtiger_selectcolumn on vtiger_selectcolumn.queryid = vtiger_selectquery.queryid";
		$ssql .= " where vtiger_report.reportid = ?";
		$ssql .= " order by vtiger_selectcolumn.columnindex";
		$result = $adb->pquery($ssql, array($reportid));
		$permitted_fields = Array();

		while($columnslistrow = $adb->fetch_array($result))
		{
			$fieldname ="";
			$fieldcolname = $columnslistrow["columnname"];
			list($tablename,$colname,$module_field,$fieldname,$single) = split(":",$fieldcolname);
			list($module,$field) = split("_",$module_field,2);
			$inventory_fields = array('serviceid');
			$inventory_modules = getInventoryModules();
			require('user_privileges/user_privileges_'.$current_user->id.'.php');
			if(sizeof($permitted_fields[$module]) == 0 && $is_admin == false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1)
			{
				$permitted_fields[$module] = $this->getaccesfield($module);
			}
			if(in_array($module,$inventory_modules)){
				if (!empty ($permitted_fields)) {
					foreach ($inventory_fields as $value) {
						array_push($permitted_fields[$module], $value);
					}
				}
			}
			$selectedfields = explode(":",$fieldcolname);
			if($is_admin == false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1
					&& !in_array($selectedfields[3], $permitted_fields[$module])) {
				//user has no access to this field, skip it.
				continue;
			}
			$concatSql = getSqlForNameInDisplayFormat(array('first_name'=>$selectedfields[0].".first_name",'last_name'=>$selectedfields[0].".last_name"), 'Users');
			$querycolumns = $this->getEscapedColumns($selectedfields);
			if(isset($module) && $module!="") {
				$mod_strings = return_module_language($current_language,$module);
			}

			$targetTableName = $tablename;

			$fieldlabel = trim(preg_replace("/$module/"," ",$selectedfields[2],1));
			$mod_arr=explode('_',$fieldlabel);
			$fieldlabel = trim(str_replace("_"," ",$fieldlabel));
			//modified code to support i18n issue
			$fld_arr = explode(" ",$fieldlabel);
			if(($mod_arr[0] == '')) {
				$mod = $module;
				$mod_lbl = getTranslatedString($module,$module); //module
			} else {
				$mod = $mod_arr[0];
				array_shift($fld_arr);
				$mod_lbl = getTranslatedString($fld_arr[0],$mod); //module
			}
			$fld_lbl_str = implode(" ",$fld_arr);
			$fld_lbl = getTranslatedString($fld_lbl_str,$module); //fieldlabel
			$fieldlabel = $mod_lbl." ".$fld_lbl;
			if(($selectedfields[0] == "vtiger_usersRel1")  && ($selectedfields[1] == 'user_name') && ($selectedfields[2] == 'Quotes_Inventory_Manager')){
				$columnslist[$fieldcolname] = "trim( $concatSql ) as ".$module."_Inventory_Manager";
                                $this->queryPlanner->addTable($selectedfields[0]);
				continue;
			}
			if((CheckFieldPermission($fieldname,$mod) != 'true' && $colname!="crmid" && (!in_array($fieldname,$inventory_fields) && in_array($module,$inventory_modules))) || empty($fieldname))
			{
				continue;
			}
			else
			{
				$this->labelMapping[$selectedfields[2]] = str_replace(" ","_",$fieldlabel);
				$header_label = $selectedfields[2]; // Header label to be displayed in the reports table
				// To check if the field in the report is a custom field
				// and if yes, get the label of this custom field freshly from the vtiger_field as it would have been changed.
				// Asha - Reference ticket : #4906

				if($querycolumns == "")
				{
					if($selectedfields[4] == 'C')
					{
						$field_label_data = split("_",$selectedfields[2]);
						$module= $field_label_data[0];
						if($module!=$this->primarymodule){
							$columnslist[$fieldcolname] = "case when (".$selectedfields[0].".".$selectedfields[1]."='1')then 'yes' else case when (vtiger_crmentity$module.crmid !='') then 'no' else '-' end end as '$selectedfields[2]'";
							$this->queryPlanner->addTable("vtiger_crmentity$module");
						}
						else{
							$columnslist[$fieldcolname] = "case when (".$selectedfields[0].".".$selectedfields[1]."='1')then 'yes' else case when (vtiger_crmentity.crmid !='') then 'no' else '-' end end as '$selectedfields[2]'";
							$this->queryPlanner->addTable("vtiger_crmentity$module");
						}
					}
					elseif($selectedfields[0] == 'vtiger_activity' && $selectedfields[1] == 'status')
					{
						$columnslist[$fieldcolname] = " case when (vtiger_activity.status not like '') then vtiger_activity.status else vtiger_activity.eventstatus end as Calendar_Status";
					}
					elseif($selectedfields[0] == 'vtiger_activity' && $selectedfields[1] == 'date_start') {
						if($module == 'Emails') {
							$columnslist[$fieldcolname] = "cast(concat(vtiger_activity.date_start,'  ',vtiger_activity.time_start) as DATE) as Emails_Date_Sent";
						} else {
							$columnslist[$fieldcolname] = "cast(concat(vtiger_activity.date_start,'  ',vtiger_activity.time_start) as DATETIME) as Calendar_Start_Date_and_Time";
						}
					}
					elseif(stristr($selectedfields[0],"vtiger_users") && ($selectedfields[1] == 'user_name'))
					{
						$temp_module_from_tablename = str_replace("vtiger_users","",$selectedfields[0]);
						if($module!=$this->primarymodule){
							$condition = "and vtiger_crmentity".$module.".crmid!=''";
							$this->queryPlanner->addTable("vtiger_crmentity$module");

						} else {
							$condition = "and vtiger_crmentity.crmid!=''";
						}
						if($temp_module_from_tablename == $module) {
							$columnslist[$fieldcolname] = " case when(".$selectedfields[0].".last_name NOT LIKE '' $condition ) THEN ".$concatSql." else vtiger_groups".$module.".groupname end as '".$module."_$field'";
							$this->queryPlanner->addTable('vtiger_groups'.$module); // Auto-include the dependent module table.
						}
						else//Some Fields can't assigned to groups so case avoided (fields like inventory manager)
							$columnslist[$fieldcolname] = $selectedfields[0].".user_name as '".$header_label."'";

					}
                    elseif(stristr($selectedfields[0],"vtiger_crmentity") && ($selectedfields[1] == 'modifiedby')) {
						$targetTableName = 'vtiger_lastModifiedBy'.$module;
                        $concatSql = getSqlForNameInDisplayFormat(array('last_name'=>$targetTableName.'.last_name', 'first_name'=>$targetTableName.'.first_name'), 'Users');
						$columnslist[$fieldcolname] = "trim($concatSql) as $header_label";
						$this->queryPlanner->addTable("vtiger_crmentity$module");
						$this->queryPlanner->addTable($targetTableName);
					}
					elseif($selectedfields[0] == "vtiger_crmentity".$this->primarymodule)
					{
						$columnslist[$fieldcolname] = "vtiger_crmentity.".$selectedfields[1]." AS '".$header_label."'";
					}
				    elseif($selectedfields[0] == 'vtiger_products' && $selectedfields[1] == 'unit_price')//handled for product fields in Campaigns Module Reports
					{
						$columnslist[$fieldcolname] = "concat(".$selectedfields[0].".currency_id,'::',innerProduct.actual_unit_price) as '". $header_label ."'";
						$this->queryPlanner->addTable("innerProduct");
					}
					elseif(in_array($selectedfields[2], $this->append_currency_symbol_to_value)) {
						if($selectedfields[1] == 'discount_amount') {
							$columnslist[$fieldcolname] = "CONCAT(".$selectedfields[0].".currency_id,'::', IF(".$selectedfields[0].".discount_amount != '',".$selectedfields[0].".discount_amount, (".$selectedfields[0].".discount_percent/100) * ".$selectedfields[0].".subtotal)) AS ".$header_label;
						} else {
							$columnslist[$fieldcolname] = "concat(".$selectedfields[0].".currency_id,'::',".$selectedfields[0].".".$selectedfields[1].") as '" . $header_label ."'";
						}
					}
					elseif($selectedfields[0] == 'vtiger_notes' && ($selectedfields[1] == 'filelocationtype' || $selectedfields[1] == 'filesize' || $selectedfields[1] == 'folderid' || $selectedfields[1]=='filestatus'))//handled for product fields in Campaigns Module Reports
					{
						if($selectedfields[1] == 'filelocationtype'){
							$columnslist[$fieldcolname] = "case ".$selectedfields[0].".".$selectedfields[1]." when 'I' then 'Internal' when 'E' then 'External' else '-' end as '$selectedfields[2]'";
						} else if($selectedfields[1] == 'folderid'){
							$columnslist[$fieldcolname] = "vtiger_attachmentsfolder.foldername as '$selectedfields[2]'";
						} elseif($selectedfields[1] == 'filestatus'){
							$columnslist[$fieldcolname] = "case ".$selectedfields[0].".".$selectedfields[1]." when '1' then 'yes' when '0' then 'no' else '-' end as '$selectedfields[2]'";
						} elseif($selectedfields[1] == 'filesize'){
							$columnslist[$fieldcolname] = "case ".$selectedfields[0].".".$selectedfields[1]." when '' then '-' else concat(".$selectedfields[0].".".$selectedfields[1]."/1024,'  ','KB') end as '$selectedfields[2]'";
						}
					}
					elseif($selectedfields[0] == 'vtiger_inventoryproductrel')//handled for product fields in Campaigns Module Reports
					{
						if($selectedfields[1] == 'discount_amount'){
							$columnslist[$fieldcolname] = " case when (vtiger_inventoryproductrel{$module}.discount_amount != '') then vtiger_inventoryproductrel{$module}.discount_amount else ROUND((vtiger_inventoryproductrel{$module}.listprice * vtiger_inventoryproductrel{$module}.quantity * (vtiger_inventoryproductrel{$module}.discount_percent/100)),3) end as '" . $header_label ."'";
							$this->queryPlanner->addTable($selectedfields[0].$module);
						} else if($selectedfields[1] == 'productid'){
							$columnslist[$fieldcolname] = "vtiger_products{$module}.productname as '" . $header_label ."'";
							$this->queryPlanner->addTable("vtiger_products{$module}");
						} else if($selectedfields[1] == 'serviceid'){
							$columnslist[$fieldcolname] = "vtiger_service{$module}.servicename as '" . $header_label ."'";
							$this->queryPlanner->addTable("vtiger_service{$module}");
						} else if($selectedfields[1] == 'listprice') {
							$moduleInstance = CRMEntity::getInstance($module);
							$columnslist[$fieldcolname] = $selectedfields[0].$module.".".$selectedfields[1]."/".$moduleInstance->table_name.".conversion_rate as '".$header_label."'";
							$this->queryPlanner->addTable($selectedfields[0].$module);
						} else {
							$columnslist[$fieldcolname] = $selectedfields[0].$module.".".$selectedfields[1]." as '".$header_label."'";
							$this->queryPlanner->addTable($selectedfields[0].$module);
						}
					}
					elseif(stristr($selectedfields[1],'cf_')==true && stripos($selectedfields[1],'cf_')==0)
					{
						$columnslist[$fieldcolname] = $selectedfields[0].".".$selectedfields[1]." AS '".$adb->sql_escape_string(decode_html($header_label))."'";
					}
					else
					{
						$columnslist[$fieldcolname] = $selectedfields[0].".".$selectedfields[1]." AS '".$header_label."'";
					}
				}
				else
				{
					$columnslist[$fieldcolname] = $querycolumns;
				}

				$this->queryPlanner->addTable($targetTableName);
			}
		}

		if ($outputformat == "HTML" || $outputformat == "PDF") {
            $columnslist['vtiger_crmentity:crmid:LBL_ACTION:crmid:I'] = 'vtiger_crmentity.crmid AS "'.$this->primarymodule.'_LBL_ACTION"' ;
        }

		// Save the information
		$this->_columnslist = $columnslist;

		$log->info("ReportRun :: Successfully returned getQueryColumnsList".$reportid);
		return $columnslist;
	}

	/** Function to get field columns based on profile
	 *  @ param $module : Type string
	 *  returns permitted fields in array format
	 */
	function getaccesfield($module) {
		global $current_user;
		global $adb;
		$access_fields = Array();

		$profileList = getCurrentUserProfileList();
		$query = "select vtiger_field.fieldname from vtiger_field inner join vtiger_profile2field on vtiger_profile2field.fieldid=vtiger_field.fieldid inner join vtiger_def_org_field on vtiger_def_org_field.fieldid=vtiger_field.fieldid where";
		$params = array();
		if($module == "Calendar")
		{
			if (count($profileList) > 0) {
				$query .= " vtiger_field.tabid in (9,16) and vtiger_field.displaytype in (1,2,3) and vtiger_profile2field.visible=0 and vtiger_def_org_field.visible=0
								and vtiger_field.presence IN (0,2) and vtiger_profile2field.profileid in (". generateQuestionMarks($profileList) .") group by vtiger_field.fieldid order by block,sequence";
				array_push($params, $profileList);
			} else {
				$query .= " vtiger_field.tabid in (9,16) and vtiger_field.displaytype in (1,2,3) and vtiger_profile2field.visible=0 and vtiger_def_org_field.visible=0
								and vtiger_field.presence IN (0,2) group by vtiger_field.fieldid order by block,sequence";
			}
		}
		else
		{
			array_push($params, $module);
			if (count($profileList) > 0) {
				$query .= " vtiger_field.tabid in (select tabid from vtiger_tab where vtiger_tab.name in (?)) and vtiger_field.displaytype in (1,2,3,5) and vtiger_profile2field.visible=0
								and vtiger_field.presence IN (0,2) and vtiger_def_org_field.visible=0 and vtiger_profile2field.profileid in (". generateQuestionMarks($profileList) .") group by vtiger_field.fieldid order by block,sequence";
				array_push($params, $profileList);
			} else {
				$query .= " vtiger_field.tabid in (select tabid from vtiger_tab where vtiger_tab.name in (?)) and vtiger_field.displaytype in (1,2,3,5) and vtiger_profile2field.visible=0
								and vtiger_field.presence IN (0,2) and vtiger_def_org_field.visible=0 group by vtiger_field.fieldid order by block,sequence";
			}
		}
		$result = $adb->pquery($query, $params);

		while($collistrow = $adb->fetch_array($result))
		{
			$access_fields[] = $collistrow["fieldname"];
		}
		//added to include ticketid for Reports module in select columnlist for all users
		if($module == "HelpDesk")
			$access_fields[] = "ticketid";
		return $access_fields;
	}

	/** Function to get Escapedcolumns for the field in case of multiple parents
	 *  @ param $selectedfields : Type Array
	 *  returns the case query for the escaped columns
	 */
	function getEscapedColumns($selectedfields) {

		$tableName = $selectedfields[0];
		$columnName = $selectedfields[1];
		$moduleFieldLabel = $selectedfields[2];
		$fieldName = $selectedfields[3];
		list($moduleName, $fieldLabel) = explode('_', $moduleFieldLabel, 2);
		$fieldInfo = getFieldByReportLabel($moduleName, $fieldLabel);

		if($moduleName == 'ModComments' && $fieldName == 'creator') {
			$concatSql = getSqlForNameInDisplayFormat(array('first_name' => 'vtiger_usersModComments.first_name',
															'last_name' => 'vtiger_usersModComments.last_name'), 'Users');
			$queryColumn = "trim(case when (vtiger_usersModComments.user_name not like '' and vtiger_crmentity.crmid!='') then $concatSql end) as 'ModComments_Creator'";

		} elseif(($fieldInfo['uitype'] == '10' || isReferenceUIType($fieldInfo['uitype']))
				&& $fieldInfo['uitype'] != '52' && $fieldInfo['uitype'] != '53') {
			$fieldSqlColumns = $this->getReferenceFieldColumnList($moduleName, $fieldInfo);
			if(count($fieldSqlColumns) > 0) {
				$queryColumn = "(CASE WHEN $tableName.$columnName NOT LIKE '' THEN (CASE";
				foreach($fieldSqlColumns as $columnSql) {
					$queryColumn .= " WHEN $columnSql NOT LIKE '' THEN $columnSql";
				}
				$queryColumn .= " ELSE '' END) ELSE '' END) AS $moduleFieldLabel";
				$this->queryPlanner->addTable($tableName);
			}
		}
		return $queryColumn;
	}

	/** Function to get selectedcolumns for the given reportid
	 *  @ param $reportid : Type Integer
	 *  returns the query of columnlist for the selected columns
	 */
	function getSelectedColumnsList($reportid)
	{

		global $adb;
		global $modules;
		global $log;

		$ssql = "select vtiger_selectcolumn.* from vtiger_report inner join vtiger_selectquery on vtiger_selectquery.queryid = vtiger_report.queryid";
		$ssql .= " left join vtiger_selectcolumn on vtiger_selectcolumn.queryid = vtiger_selectquery.queryid where vtiger_report.reportid = ? ";
		$ssql .= " order by vtiger_selectcolumn.columnindex";

		$result = $adb->pquery($ssql, array($reportid));
		$noofrows = $adb->num_rows($result);

		if ($this->orderbylistsql != "")
		{
			$sSQL .= $this->orderbylistsql.", ";
		}

		for($i=0; $i<$noofrows; $i++)
		{
			$fieldcolname = $adb->query_result($result,$i,"columnname");
			$ordercolumnsequal = true;
			if($fieldcolname != "")
			{
				for($j=0;$j<count($this->orderbylistcolumns);$j++)
				{
					if($this->orderbylistcolumns[$j] == $fieldcolname)
					{
						$ordercolumnsequal = false;
						break;
					}else
					{
						$ordercolumnsequal = true;
					}
				}
				if($ordercolumnsequal)
				{
					$selectedfields = explode(":",$fieldcolname);
					if($selectedfields[0] == "vtiger_crmentity".$this->primarymodule)
						$selectedfields[0] = "vtiger_crmentity";
					$sSQLList[] = $selectedfields[0].".".$selectedfields[1]." '".$selectedfields[2]."'";
				}
			}
		}
		$sSQL .= implode(",",$sSQLList);

		$log->info("ReportRun :: Successfully returned getSelectedColumnsList".$reportid);
		return $sSQL;
	}

	/** Function to get advanced comparator in query form for the given Comparator and value
	 *  @ param $comparator : Type String
	 *  @ param $value : Type String
	 *  returns the check query for the comparator
	 */
	function getAdvComparator($comparator,$value,$datatype="")
	{

		global $log,$adb,$default_charset,$ogReport;
		$value=html_entity_decode(trim($value),ENT_QUOTES,$default_charset);
		$value_len = strlen($value);
		$is_field = false;
		if($value_len > 1 && $value[0]=='$' && $value[$value_len-1]=='$'){
			$temp = str_replace('$','',$value);
			$is_field = true;
		}
		if($datatype=='C'){
			$value = str_replace("yes","1",str_replace("no","0",$value));
		}

		if($is_field==true){
			$value = $this->getFilterComparedField($temp);
		}
		if($comparator == "e")
		{
			if(trim($value) == "NULL")
			{
				$rtvalue = " is NULL";
			}elseif(trim($value) != "")
			{
				$rtvalue = " = ".$adb->quote($value);
			}elseif(trim($value) == "" && $datatype == "V")
			{
				$rtvalue = " = ".$adb->quote($value);
			}else
			{
				$rtvalue = " is NULL";
			}
		}
		if($comparator == "n")
		{
			if(trim($value) == "NULL")
			{
				$rtvalue = " is NOT NULL";
			}elseif(trim($value) != "")
			{
				$rtvalue = " <> ".$adb->quote($value);
			}elseif(trim($value) == "" && $datatype == "V")
			{
				$rtvalue = " <> ".$adb->quote($value);
			}else
			{
				$rtvalue = " is NOT NULL";
			}
		}
		if($comparator == "s")
		{
			$rtvalue = " like '". formatForSqlLike($value, 2,$is_field) ."'";
		}
		if($comparator == "ew")
		{
			$rtvalue = " like '". formatForSqlLike($value, 1,$is_field) ."'";
		}
		if($comparator == "c")
		{
			$rtvalue = " like '". formatForSqlLike($value,0,$is_field) ."'";
		}
		if($comparator == "k")
		{
			$rtvalue = " not like '". formatForSqlLike($value,0,$is_field) ."'";
		}
		if($comparator == "l")
		{
			$rtvalue = " < ".$adb->quote($value);
		}
		if($comparator == "g")
		{
			$rtvalue = " > ".$adb->quote($value);
		}
		if($comparator == "m")
		{
			$rtvalue = " <= ".$adb->quote($value);
		}
		if($comparator == "h")
		{
			$rtvalue = " >= ".$adb->quote($value);
		}
		if($comparator == "b") {
			$rtvalue = " < ".$adb->quote($value);
		}
		if($comparator == "a") {
			$rtvalue = " > ".$adb->quote($value);
		}
		if($is_field==true){
			$rtvalue = str_replace("'","",$rtvalue);
			$rtvalue = str_replace("\\","",$rtvalue);
		}
		$log->info("ReportRun :: Successfully returned getAdvComparator");
		return $rtvalue;
	}

	/** Function to get field that is to be compared in query form for the given Comparator and field
	 *  @ param $field : field
	 *  returns the value for the comparator
	 */
	function getFilterComparedField($field){
		global $adb,$ogReport;
		if(!empty ($this->secondarymodule)){
		    $secModules = explode(':',$this->secondarymodule);
		    foreach ($secModules as $secModule){
			$secondary = CRMEntity::getInstance($secModule);
			$this->queryPlanner->addTable($secondary->table_name);
		    }
		}
			$field = split('#',$field);
			$module = $field[0];
			$fieldname = trim($field[1]);
			$tabid = getTabId($module);
			$field_query = $adb->pquery("SELECT tablename,columnname,typeofdata,fieldname,uitype FROM vtiger_field WHERE tabid = ? AND fieldname= ?",array($tabid,$fieldname));
			$fieldtablename = $adb->query_result($field_query,0,'tablename');
			$fieldcolname = $adb->query_result($field_query,0,'columnname');
			$typeofdata = $adb->query_result($field_query,0,'typeofdata');
			$fieldtypeofdata=ChangeTypeOfData_Filter($fieldtablename,$fieldcolname,$typeofdata[0]);
			$uitype = $adb->query_result($field_query,0,'uitype');
			/*if($tr[0]==$ogReport->primodule)
				$value = $adb->query_result($field_query,0,'tablename').".".$adb->query_result($field_query,0,'columnname');
			else
				$value = $adb->query_result($field_query,0,'tablename').$tr[0].".".$adb->query_result($field_query,0,'columnname');
			*/
			if($uitype == 68 || $uitype == 59)
			{
				$fieldtypeofdata = 'V';
			}
			if($fieldtablename == "vtiger_crmentity" && $module != $this->primarymodule)
			{
				$fieldtablename = $fieldtablename.$module;
			}
			if($fieldname == "assigned_user_id")
			{
				$fieldtablename = "vtiger_users".$module;
				$fieldcolname = "user_name";
			}
            if($fieldtablename == "vtiger_crmentity" && $fieldname == "modifiedby")
			{
				$fieldtablename = "vtiger_lastModifiedBy".$module;
				$fieldcolname = "user_name";
			}
			if($fieldname == "assigned_user_id1")
			{
				$fieldtablename = "vtiger_usersRel1";
				$fieldcolname = "user_name";
			}

			$value = $fieldtablename.".".$fieldcolname;

			$this->queryPlanner->addTable($fieldtablename);
		return $value;
	}
	/** Function to get the advanced filter columns for the reportid
	 *  This function accepts the $reportid
	 *  This function returns  $columnslist Array($columnname => $tablename:$columnname:$fieldlabel:$fieldname:$typeofdata=>$tablename.$columnname filtercriteria,
	 *					      $tablename1:$columnname1:$fieldlabel1:$fieldname1:$typeofdata1=>$tablename1.$columnname1 filtercriteria,
	 *					      					|
 	 *					      $tablenamen:$columnnamen:$fieldlabeln:$fieldnamen:$typeofdatan=>$tablenamen.$columnnamen filtercriteria
	 *				      	     )
	 *
	 */
	 function getAdvFilterList($reportid) {
		global $adb, $log;

		$advft_criteria = array();

		$sql = 'SELECT * FROM vtiger_relcriteria_grouping WHERE queryid = ? ORDER BY groupid';
		$groupsresult = $adb->pquery($sql, array($reportid));

		$i = 1;
		$j = 0;
		while($relcriteriagroup = $adb->fetch_array($groupsresult)) {
			$groupId = $relcriteriagroup["groupid"];
			$groupCondition = $relcriteriagroup["group_condition"];

			$ssql = 'select vtiger_relcriteria.* from vtiger_report
						inner join vtiger_relcriteria on vtiger_relcriteria.queryid = vtiger_report.queryid
						left join vtiger_relcriteria_grouping on vtiger_relcriteria.queryid = vtiger_relcriteria_grouping.queryid
								and vtiger_relcriteria.groupid = vtiger_relcriteria_grouping.groupid';
			$ssql.= " where vtiger_report.reportid = ? AND vtiger_relcriteria.groupid = ? order by vtiger_relcriteria.columnindex";

			$result = $adb->pquery($ssql, array($reportid, $groupId));
			$noOfColumns = $adb->num_rows($result);
			if($noOfColumns <= 0) continue;

			while($relcriteriarow = $adb->fetch_array($result)) {
				$columnIndex = $relcriteriarow["columnindex"];
				$criteria = array();
				$criteria['columnname'] = html_entity_decode($relcriteriarow["columnname"]);
				$criteria['comparator'] = $relcriteriarow["comparator"];
				$advfilterval = $relcriteriarow["value"];
				$col = explode(":",$relcriteriarow["columnname"]);
				$criteria['value'] = $advfilterval;
				$criteria['column_condition'] = $relcriteriarow["column_condition"];

				$advft_criteria[$i]['columns'][$j] = $criteria;
				$advft_criteria[$i]['condition'] = $groupCondition;
				$j++;

				$this->queryPlanner->addTable($col[0]);
			}
			if(!empty($advft_criteria[$i]['columns'][$j-1]['column_condition'])) {
				$advft_criteria[$i]['columns'][$j-1]['column_condition'] = '';
			}
			$i++;
		}
		// Clear the condition (and/or) for last group, if any.
		if(!empty($advft_criteria[$i-1]['condition'])) $advft_criteria[$i-1]['condition'] = '';
		return $advft_criteria;
	}

	function generateAdvFilterSql($advfilterlist) {

		global $adb;

		$advfiltersql = "";
        $customView = new CustomView();
		$dateSpecificConditions = $customView->getStdFilterConditions();

		foreach($advfilterlist as $groupindex => $groupinfo) {
			$groupcondition = $groupinfo['condition'];
			$groupcolumns = $groupinfo['columns'];

			if(count($groupcolumns) > 0) {

				$advfiltergroupsql = "";
				foreach($groupcolumns as $columnindex => $columninfo) {
					$fieldcolname = $columninfo["columnname"];
					$comparator = $columninfo["comparator"];
					$value = $columninfo["value"];
					$columncondition = $columninfo["column_condition"];

					if($fieldcolname != "" && $comparator != "") {
						if(in_array($comparator, $dateSpecificConditions)) {
							if($fieldcolname != 'none') {
								$selectedFields = explode(':',$fieldcolname);
								if($selectedFields[0] == 'vtiger_crmentity'.$this->primarymodule) {
									$selectedFields[0] = 'vtiger_crmentity';
								}

								if($comparator != 'custom') {
									list($startDate, $endDate) = $this->getStandarFiltersStartAndEndDate($comparator);
								} else {
                                    list($startDateTime, $endDateTime) = explode(',', $value);
									list($startDate, $startTime) = explode(' ', $startDateTime);
									list($endDate, $endTime) = explode(' ', $endDateTime);
                                }

								$type = $selectedFields[4];
								if($startDate != '0000-00-00' && $endDate != '0000-00-00' && $startDate != '' && $endDate != '') {
									$startDateTime = new DateTimeField($startDate. ' ' .date('H:i:s'));
									$userStartDate = $startDateTime->getDisplayDate();
									if($type == 'DT') {
										$userStartDate = $userStartDate.' 00:00:00';
									}
									$startDateTime = getValidDBInsertDateTimeValue($userStartDate);

									$endDateTime = new DateTimeField($endDate. ' ' .date('H:i:s'));
									$userEndDate = $endDateTime->getDisplayDate();
									if($type == 'DT') {
										$userEndDate = $userEndDate.' 23:59:59';
									}
									$endDateTime = getValidDBInsertDateTimeValue($userEndDate);

									if ($selectedFields[1] == 'birthday') {
										$tableColumnSql = 'DATE_FORMAT(' . $selectedFields[0] . '.' . $selectedFields[1] . ', "%m%d")';
										$startDateTime = "DATE_FORMAT('$startDateTime', '%m%d')";
										$endDateTime = "DATE_FORMAT('$endDateTime', '%m%d')";
									} else {
										if($selectedFields[0] == 'vtiger_activity' && ($selectedFields[1] == 'date_start')) {
											$tableColumnSql = 'CAST((CONCAT(date_start, " ", time_start)) AS DATETIME)';
										} else {
											$tableColumnSql = $selectedFields[0]. '.' .$selectedFields[1];
										}
										$startDateTime = "'$startDateTime'";
										$endDateTime = "'$endDateTime'";
									}

									$advfiltergroupsql .= "$tableColumnSql BETWEEN $startDateTime AND $endDateTime";
									if(!empty($columncondition)) {
										$advfiltergroupsql .= ' '.$columncondition.' ';
									}

									$this->queryPlanner->addTable($selectedFields[0]);
								}
							}
							continue;
                        }

						$selectedfields = explode(":",$fieldcolname);
						$moduleFieldLabel = $selectedfields[2];
						list($moduleName, $fieldLabel) = explode('_', $moduleFieldLabel, 2);
						$fieldInfo = getFieldByReportLabel($moduleName, $fieldLabel);
                        $concatSql = getSqlForNameInDisplayFormat(array('first_name'=>$selectedfields[0].".first_name",'last_name'=>$selectedfields[0].".last_name"), 'Users');
						// Added to handle the crmentity table name for Primary module
                        if($selectedfields[0] == "vtiger_crmentity".$this->primarymodule) {
                            $selectedfields[0] = "vtiger_crmentity";
                        }
						//Added to handle yes or no for checkbox  field in reports advance filters. -shahul
						if($selectedfields[4] == 'C') {
							if(strcasecmp(trim($value),"yes")==0)
								$value="1";
							if(strcasecmp(trim($value),"no")==0)
								$value="0";
						}
                        if(in_array($comparator,$dateSpecificConditions)) {
                            $customView = new CustomView($moduleName);
                            $columninfo['stdfilter'] = $columninfo['comparator'];
                            $valueComponents = explode(',',$columninfo['value']);
                            if($comparator == 'custom') {
								if($selectedfields[4] == 'DT') {
									$startDateTimeComponents = explode(' ',$valueComponents[0]);
									$endDateTimeComponents = explode(' ',$valueComponents[1]);
									$columninfo['startdate'] = DateTimeField::convertToDBFormat($startDateTimeComponents[0]);
									$columninfo['enddate'] = DateTimeField::convertToDBFormat($endDateTimeComponents[0]);
								} else {
									$columninfo['startdate'] = DateTimeField::convertToDBFormat($valueComponents[0]);
									$columninfo['enddate'] = DateTimeField::convertToDBFormat($valueComponents[1]);
								}
                            }
                            $dateFilterResolvedList = $customView->resolveDateFilterValue($columninfo);
                            $startDate = DateTimeField::convertToDBFormat($dateFilterResolvedList['startdate']);
                            $endDate = DateTimeField::convertToDBFormat($dateFilterResolvedList['enddate']);
                            $columninfo['value'] = $value  = implode(',', array($startDate,$endDate));
                            $comparator = 'bw';
                        }
						$valuearray = explode(",",trim($value));
						$datatype = (isset($selectedfields[4])) ? $selectedfields[4] : "";
						if(isset($valuearray) && count($valuearray) > 1 && $comparator != 'bw') {

							$advcolumnsql = "";
							for($n=0;$n<count($valuearray);$n++) {

		                		if(($selectedfields[0] == "vtiger_users".$this->primarymodule || $selectedfields[0] == "vtiger_users".$this->secondarymodule) && $selectedfields[1] == 'user_name') {
									$module_from_tablename = str_replace("vtiger_users","",$selectedfields[0]);
									$advcolsql[] = " (trim($concatSql)".$this->getAdvComparator($comparator,trim($valuearray[$n]),$datatype)." or vtiger_groups".$module_from_tablename.".groupname ".$this->getAdvComparator($comparator,trim($valuearray[$n]),$datatype).")";
									$this->queryPlanner->addTable("vtiger_groups".$module_from_tablename);
								} elseif($selectedfields[1] == 'status') {//when you use comma seperated values.
									if($selectedfields[2] == 'Calendar_Status')
									$advcolsql[] = "(case when (vtiger_activity.status not like '') then vtiger_activity.status else vtiger_activity.eventstatus end)".$this->getAdvComparator($comparator,trim($valuearray[$n]),$datatype);
									elseif($selectedfields[2] == 'HelpDesk_Status')
									$advcolsql[] = "vtiger_troubletickets.status".$this->getAdvComparator($comparator,trim($valuearray[$n]),$datatype);
								} elseif($selectedfields[1] == 'description') {//when you use comma seperated values.
									if($selectedfields[0]=='vtiger_crmentity'.$this->primarymodule)
										$advcolsql[] = "vtiger_crmentity.description".$this->getAdvComparator($comparator,trim($valuearray[$n]),$datatype);
									else
										$advcolsql[] = $selectedfields[0].".".$selectedfields[1].$this->getAdvComparator($comparator,trim($valuearray[$n]),$datatype);
								} elseif($selectedfields[2] == 'Quotes_Inventory_Manager'){
									$advcolsql[] = ("trim($concatSql)".$this->getAdvComparator($comparator,trim($valuearray[$n]),$datatype));
								} else {
									$advcolsql[] = $selectedfields[0].".".$selectedfields[1].$this->getAdvComparator($comparator,trim($valuearray[$n]),$datatype);
								}
							}
							//If negative logic filter ('not equal to', 'does not contain') is used, 'and' condition should be applied instead of 'or'
							if($comparator == 'n' || $comparator == 'k')
								$advcolumnsql = implode(" and ",$advcolsql);
							else
								$advcolumnsql = implode(" or ",$advcolsql);
							$fieldvalue = " (".$advcolumnsql.") ";
						} elseif($selectedfields[1] == 'user_name') {
							if($selectedfields[0] == "vtiger_users".$this->primarymodule) {
								$module_from_tablename = str_replace("vtiger_users","",$selectedfields[0]);
								$fieldvalue = " trim(case when (".$selectedfields[0].".last_name NOT LIKE '') then ".$concatSql." else vtiger_groups".$module_from_tablename.".groupname end) ".$this->getAdvComparator($comparator,trim($value),$datatype);
								$this->queryPlanner->addTable("vtiger_groups".$module_from_tablename);
							} else {
								$secondaryModules = explode(':', $this->secondarymodule);
								$firstSecondaryModule = "vtiger_users".$secondaryModules[0];
								$secondSecondaryModule = "vtiger_users".$secondaryModules[1];
 								if(($firstSecondaryModule && $firstSecondaryModule == $selectedfields[0]) || ($secondSecondaryModule && $secondSecondaryModule == $selectedfields[0])) {
									$module_from_tablename = str_replace("vtiger_users","",$selectedfields[0]);
									$moduleInstance = CRMEntity::getInstance($module_from_tablename);
									$fieldvalue = " trim(case when (".$selectedfields[0].".last_name NOT LIKE '') then ".$concatSql." else vtiger_groups".$module_from_tablename.".groupname end) ".$this->getAdvComparator($comparator,trim($value),$datatype);
									$this->queryPlanner->addTable("vtiger_groups".$module_from_tablename);
									$this->queryPlanner->addTable($moduleInstance->table_name);
								}
							}
						} elseif($comparator == 'bw' && count($valuearray) == 2) {
							if($selectedfields[0] == "vtiger_crmentity".$this->primarymodule) {
								$fieldvalue = "("."vtiger_crmentity.".$selectedfields[1]." between '".trim($valuearray[0])."' and '".trim($valuearray[1])."')";
							} else {
								$fieldvalue = "(".$selectedfields[0].".".$selectedfields[1]." between '".trim($valuearray[0])."' and '".trim($valuearray[1])."')";
							}
						} elseif($selectedfields[0] == "vtiger_crmentity".$this->primarymodule) {
							$fieldvalue = "vtiger_crmentity.".$selectedfields[1]." ".$this->getAdvComparator($comparator,trim($value),$datatype);
						} elseif($selectedfields[2] == 'Quotes_Inventory_Manager'){
							$fieldvalue = ("trim($concatSql)" . $this->getAdvComparator($comparator,trim($value),$datatype));
						} elseif($selectedfields[1]=='modifiedby') {
                            $module_from_tablename = str_replace("vtiger_crmentity","",$selectedfields[0]);
                            if($module_from_tablename != '') {
								$tableName = 'vtiger_lastModifiedBy'.$module_from_tablename;
							} else {
								$tableName = 'vtiger_lastModifiedBy'.$this->primarymodule;
							}
							$this->queryPlanner->addTable($tableName);
							$fieldvalue = getSqlForNameInDisplayFormat(array('last_name'=>"$tableName.last_name",'first_name'=>"$tableName.first_name"), 'Users').
									$this->getAdvComparator($comparator,trim($value),$datatype);
						} elseif($selectedfields[0] == "vtiger_activity" && $selectedfields[1] == 'status') {
							$fieldvalue = "(case when (vtiger_activity.status not like '') then vtiger_activity.status else vtiger_activity.eventstatus end)".$this->getAdvComparator($comparator,trim($value),$datatype);
						} elseif($comparator == 'y' || ($comparator == 'e' && (trim($value) == "NULL" || trim($value) == ''))) {
							if($selectedfields[0] == 'vtiger_inventoryproductrel') {
								$selectedfields[0]='vtiger_inventoryproductrel'.$moduleName;
							}
							$fieldvalue = "(".$selectedfields[0].".".$selectedfields[1]." IS NULL OR ".$selectedfields[0].".".$selectedfields[1]." = '')";
						} elseif($selectedfields[0] == 'vtiger_inventoryproductrel' ) {
							if($selectedfields[1] == 'productid'){
									$fieldvalue = "vtiger_products$moduleName.productname ".$this->getAdvComparator($comparator,trim($value),$datatype);
									$this->queryPlanner->addTable("vtiger_products$moduleName");
							} else if($selectedfields[1] == 'serviceid'){
								$fieldvalue = "vtiger_service$moduleName.servicename ".$this->getAdvComparator($comparator,trim($value),$datatype);
								$this->queryPlanner->addTable("vtiger_service$moduleName");
							}
							else{
							   //for inventory module table should be follwed by the module name
								$selectedfields[0]='vtiger_inventoryproductrel'.$moduleName;
								$fieldvalue = $selectedfields[0].".".$selectedfields[1].$this->getAdvComparator($comparator, $value, $datatype);
							}
						} elseif($fieldInfo['uitype'] == '10' || isReferenceUIType($fieldInfo['uitype'])) {

							$comparatorValue = $this->getAdvComparator($comparator,trim($value),$datatype);
							$fieldSqls = array();
							$fieldSqlColumns = $this->getReferenceFieldColumnList($moduleName, $fieldInfo);
							foreach($fieldSqlColumns as $columnSql) {
							 	$fieldSqls[] = $columnSql.$comparatorValue;
							}
							$fieldvalue = ' ('. implode(' OR ', $fieldSqls).') ';
							} else {
							$fieldvalue = $selectedfields[0].".".$selectedfields[1].$this->getAdvComparator($comparator,trim($value),$datatype);
						}

						$advfiltergroupsql .= $fieldvalue;
						if(!empty($columncondition)) {
							$advfiltergroupsql .= ' '.$columncondition.' ';
						}

						$this->queryPlanner->addTable($selectedfields[0]);
					}

				}

				if (trim($advfiltergroupsql) != "") {
					$advfiltergroupsql =  "( $advfiltergroupsql ) ";
					if(!empty($groupcondition)) {
						$advfiltergroupsql .= ' '. $groupcondition . ' ';
					}

					$advfiltersql .= $advfiltergroupsql;
				}
			}
		}
		if (trim($advfiltersql) != "") $advfiltersql = '('.$advfiltersql.')';

		return $advfiltersql;
	}

	function getAdvFilterSql($reportid) {
		// Have we initialized information already?
		if($this->_advfiltersql !== false) {
			return $this->_advfiltersql;
		}
		global $log;

		$advfilterlist = $this->getAdvFilterList($reportid);
		$advfiltersql = $this->generateAdvFilterSql($advfilterlist);

		// Save the information
		$this->_advfiltersql = $advfiltersql;

		$log->info("ReportRun :: Successfully returned getAdvFilterSql".$reportid);
		return $advfiltersql;
	}

	/** Function to get the Standard filter columns for the reportid
	 *  This function accepts the $reportid datatype Integer
	 *  This function returns  $stdfilterlist Array($columnname => $tablename:$columnname:$fieldlabel:$fieldname:$typeofdata=>$tablename.$columnname filtercriteria,
	 *					      $tablename1:$columnname1:$fieldlabel1:$fieldname1:$typeofdata1=>$tablename1.$columnname1 filtercriteria,
	 *				      	     )
	 *
	 */
	function getStdFilterList($reportid)
	{
		// Have we initialized information already?
		if($this->_stdfilterlist !== false) {
			return $this->_stdfilterlist;
		}

		global $adb, $log;
		$stdfilterlist = array();

		$stdfiltersql = "select vtiger_reportdatefilter.* from vtiger_report";
		$stdfiltersql .= " inner join vtiger_reportdatefilter on vtiger_report.reportid = vtiger_reportdatefilter.datefilterid";
		$stdfiltersql .= " where vtiger_report.reportid = ?";

		$result = $adb->pquery($stdfiltersql, array($reportid));
		$stdfilterrow = $adb->fetch_array($result);
		if(isset($stdfilterrow)) {
			$fieldcolname = $stdfilterrow["datecolumnname"];
			$datefilter = $stdfilterrow["datefilter"];
			$startdate = $stdfilterrow["startdate"];
			$enddate = $stdfilterrow["enddate"];

			if($fieldcolname != "none") {
				$selectedfields = explode(":",$fieldcolname);
				if($selectedfields[0] == "vtiger_crmentity".$this->primarymodule)
					$selectedfields[0] = "vtiger_crmentity";

				$moduleFieldLabel = $selectedfields[3];
				list($moduleName, $fieldLabel) = explode('_', $moduleFieldLabel, 2);
				$fieldInfo = getFieldByReportLabel($moduleName, $fieldLabel);
				$typeOfData = $fieldInfo['typeofdata'];
				list($type, $typeOtherInfo) = explode('~', $typeOfData, 2);

				if($datefilter != "custom") {
					$startenddate = $this->getStandarFiltersStartAndEndDate($datefilter);
					$startdate = $startenddate[0];
					$enddate = $startenddate[1];
				}

				if($startdate != "0000-00-00" && $enddate != "0000-00-00" && $startdate != "" && $enddate != ""
						&& $selectedfields[0] != "" && $selectedfields[1] != "") {

					$startDateTime = new DateTimeField($startdate.' '. date('H:i:s'));
					$userStartDate = $startDateTime->getDisplayDate();
					if($type == 'DT') {
						$userStartDate = $userStartDate.' 00:00:00';
					}
					$startDateTime = getValidDBInsertDateTimeValue($userStartDate);

					$endDateTime = new DateTimeField($enddate.' '. date('H:i:s'));
					$userEndDate = $endDateTime->getDisplayDate();
					if($type == 'DT') {
						$userEndDate = $userEndDate.' 23:59:00';
					}
					$endDateTime = getValidDBInsertDateTimeValue($userEndDate);

					if ($selectedfields[1] == 'birthday') {
						$tableColumnSql = "DATE_FORMAT(".$selectedfields[0].".".$selectedfields[1].", '%m%d')";
						$startDateTime = "DATE_FORMAT('$startDateTime', '%m%d')";
						$endDateTime = "DATE_FORMAT('$endDateTime', '%m%d')";
					} else {
						if($selectedfields[0] == 'vtiger_activity' && ($selectedfields[1] == 'date_start')) {
							$tableColumnSql = '';
							$tableColumnSql = "CAST((CONCAT(date_start,' ',time_start)) AS DATETIME)";
						} else {
							$tableColumnSql = $selectedfields[0].".".$selectedfields[1];
						}
						$startDateTime = "'$startDateTime'";
						$endDateTime = "'$endDateTime'";
					}

					$stdfilterlist[$fieldcolname] = $tableColumnSql." between ".$startDateTime." and ".$endDateTime;
					$this->queryPlanner->addTable($selectedfields[0]);
				}
			}
		}
		// Save the information
		$this->_stdfilterlist = $stdfilterlist;

		$log->info("ReportRun :: Successfully returned getStdFilterList".$reportid);
		return $stdfilterlist;
	}

	/** Function to get the RunTime filter columns for the given $filtercolumn,$filter,$startdate,$enddate
	 *  @ param $filtercolumn : Type String
	 *  @ param $filter : Type String
	 *  @ param $startdate: Type String
	 *  @ param $enddate : Type String
	 *  This function returns  $stdfilterlist Array($columnname => $tablename:$columnname:$fieldlabel=>$tablename.$columnname 'between' $startdate 'and' $enddate)
	 *
	 */
	function RunTimeFilter($filtercolumn,$filter,$startdate,$enddate)
	{
		if($filtercolumn != "none")
		{
			$selectedfields = explode(":",$filtercolumn);
			if($selectedfields[0] == "vtiger_crmentity".$this->primarymodule)
				$selectedfields[0] = "vtiger_crmentity";
			if($filter == "custom")
			{
				if($startdate != "0000-00-00" && $enddate != "0000-00-00" && $startdate != "" &&
						$enddate != "" && $selectedfields[0] != "" && $selectedfields[1] != "") {
					$stdfilterlist[$filtercolumn] = $selectedfields[0].".".$selectedfields[1]." between '".$startdate." 00:00:00' and '".$enddate." 23:59:00'";
				}
			}else
			{
				if($startdate != "" && $enddate != "")
				{
					$startenddate = $this->getStandarFiltersStartAndEndDate($filter);
					if($startenddate[0] != "" && $startenddate[1] != "" && $selectedfields[0] != "" && $selectedfields[1] != "")
					{
						$stdfilterlist[$filtercolumn] = $selectedfields[0].".".$selectedfields[1]." between '".$startenddate[0]." 00:00:00' and '".$startenddate[1]." 23:59:00'";
					}
				}
			}

		}
		return $stdfilterlist;

	}

	/** Function to get the RunTime Advanced filter conditions
	 *  @ param $advft_criteria : Type Array
	 *  @ param $advft_criteria_groups : Type Array
	 *  This function returns  $advfiltersql
	 *
	 */
	function RunTimeAdvFilter($advft_criteria,$advft_criteria_groups) {
		$adb = PearDatabase::getInstance();

		$advfilterlist = array();

		if(!empty($advft_criteria)) {
			foreach($advft_criteria as $column_index => $column_condition) {

				if(empty($column_condition)) continue;

				$adv_filter_column = $column_condition["columnname"];
				$adv_filter_comparator = $column_condition["comparator"];
				$adv_filter_value = $column_condition["value"];
				$adv_filter_column_condition = $column_condition["columncondition"];
				$adv_filter_groupid = $column_condition["groupid"];

				$column_info = explode(":",$adv_filter_column);

				$moduleFieldLabel = $column_info[2];
				$fieldName = $column_info[3];
				list($module, $fieldLabel) = explode('_', $moduleFieldLabel, 2);
				$fieldInfo = getFieldByReportLabel($module, $fieldLabel);
				$fieldType = null;
				if(!empty($fieldInfo)) {
					$field = WebserviceField::fromArray($adb, $fieldInfo);
					$fieldType = $field->getFieldDataType();
				}

				if($fieldType == 'currency') {
					// Some of the currency fields like Unit Price, Total, Sub-total etc of Inventory modules, do not need currency conversion
					if($field->getUIType() == '72') {
						$adv_filter_value = CurrencyField::convertToDBFormat($adv_filter_value, null, true);
					} else {
						$adv_filter_value = CurrencyField::convertToDBFormat($adv_filter_value);
					}
				}

                $temp_val = explode(",",$adv_filter_value);
                if(($column_info[4] == 'D' || ($column_info[4] == 'T' && $column_info[1] != 'time_start' && $column_info[1] != 'time_end')
                        || ($column_info[4] == 'DT'))
                    && ($column_info[4] != '' && $adv_filter_value != '' )) {
                    $val = Array();
                    for($x=0;$x<count($temp_val);$x++) {
                        if($column_info[4] == 'D') {
							$date = new DateTimeField(trim($temp_val[$x]));
							$val[$x] = $date->getDBInsertDateValue();
						} elseif($column_info[4] == 'DT') {
							$date = new DateTimeField(trim($temp_val[$x]));
							$val[$x] = $date->getDBInsertDateTimeValue();
						} else {
							$date = new DateTimeField(trim($temp_val[$x]));
							$val[$x] = $date->getDBInsertTimeValue();
						}
                    }
                    $adv_filter_value = implode(",",$val);
                }
				$criteria = array();
				$criteria['columnname'] = $adv_filter_column;
				$criteria['comparator'] = $adv_filter_comparator;
				$criteria['value'] = $adv_filter_value;
				$criteria['column_condition'] = $adv_filter_column_condition;

				$advfilterlist[$adv_filter_groupid]['columns'][] = $criteria;
			}

			foreach($advft_criteria_groups as $group_index => $group_condition_info) {
				if(empty($group_condition_info)) continue;
				if(empty($advfilterlist[$group_index])) continue;
				$advfilterlist[$group_index]['condition'] = $group_condition_info["groupcondition"];
				$noOfGroupColumns = count($advfilterlist[$group_index]['columns']);
				if(!empty($advfilterlist[$group_index]['columns'][$noOfGroupColumns-1]['column_condition'])) {
					$advfilterlist[$group_index]['columns'][$noOfGroupColumns-1]['column_condition'] = '';
				}
			}
			$noOfGroups = count($advfilterlist);
			if(!empty($advfilterlist[$noOfGroups]['condition'])) {
				$advfilterlist[$noOfGroups]['condition'] = '';
			}

			$advfiltersql = $this->generateAdvFilterSql($advfilterlist);
		}
		return $advfiltersql;

	}

	/** Function to get standardfilter for the given reportid
	 *  @ param $reportid : Type Integer
	 *  returns the query of columnlist for the selected columns
	 */

	function getStandardCriterialSql($reportid)
	{
		global $adb;
		global $modules;
		global $log;

		$sreportstdfiltersql = "select vtiger_reportdatefilter.* from vtiger_report";
		$sreportstdfiltersql .= " inner join vtiger_reportdatefilter on vtiger_report.reportid = vtiger_reportdatefilter.datefilterid";
		$sreportstdfiltersql .= " where vtiger_report.reportid = ?";

		$result = $adb->pquery($sreportstdfiltersql, array($reportid));
		$noofrows = $adb->num_rows($result);

		for($i=0; $i<$noofrows; $i++) {
			$fieldcolname = $adb->query_result($result,$i,"datecolumnname");
			$datefilter = $adb->query_result($result,$i,"datefilter");
			$startdate = $adb->query_result($result,$i,"startdate");
			$enddate = $adb->query_result($result,$i,"enddate");

			if($fieldcolname != "none") {
				$selectedfields = explode(":",$fieldcolname);
				if($selectedfields[0] == "vtiger_crmentity".$this->primarymodule)
					$selectedfields[0] = "vtiger_crmentity";
				if($datefilter == "custom") {

					if($startdate != "0000-00-00" && $enddate != "0000-00-00" && $selectedfields[0] != "" && $selectedfields[1] != ""
							&& $startdate != '' && $enddate != '') {

						$startDateTime = new DateTimeField($startdate.' '. date('H:i:s'));
						$startdate = $startDateTime->getDisplayDate();
						$endDateTime = new DateTimeField($enddate.' '. date('H:i:s'));
						$enddate = $endDateTime->getDisplayDate();

						$sSQL .= $selectedfields[0].".".$selectedfields[1]." between '".$startdate."' and '".$enddate."'";
					}
				} else {

					$startenddate = $this->getStandarFiltersStartAndEndDate($datefilter);

					$startDateTime = new DateTimeField($startenddate[0].' '. date('H:i:s'));
					$startdate = $startDateTime->getDisplayDate();
					$endDateTime = new DateTimeField($startenddate[1].' '. date('H:i:s'));
					$enddate = $endDateTime->getDisplayDate();

					if($startenddate[0] != "" && $startenddate[1] != "" && $selectedfields[0] != "" && $selectedfields[1] != "") {
						$sSQL .= $selectedfields[0].".".$selectedfields[1]." between '".$startdate."' and '".$enddate."'";
					}
				}
			}
		}
		$log->info("ReportRun :: Successfully returned getStandardCriterialSql".$reportid);
		return $sSQL;
	}

	/** Function to get standardfilter startdate and enddate for the given type
	 *  @ param $type : Type String
	 *  returns the $datevalue Array in the given format
	 * 		$datevalue = Array(0=>$startdate,1=>$enddate)
	 */


	function getStandarFiltersStartAndEndDate($type)
	{
		$today = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d"), date("Y")));
		$tomorrow  = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")+1, date("Y")));
		$yesterday  = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-1, date("Y")));

		$currentmonth0 = date("Y-m-d",mktime(0, 0, 0, date("m"), "01",   date("Y")));
		$currentmonth1 = date("Y-m-t");
		$lastmonth0 = date("Y-m-d",mktime(0, 0, 0, date("m")-1, "01",   date("Y")));
		$lastmonth1 = date("Y-m-t", strtotime("-1 Month"));
		$nextmonth0 = date("Y-m-d",mktime(0, 0, 0, date("m")+1, "01",   date("Y")));
		$nextmonth1 = date("Y-m-t", strtotime("+1 Month"));

		$lastweek0 = date("Y-m-d",strtotime("-2 week Monday"));
		$lastweek1 = date("Y-m-d",strtotime("-1 week Sunday"));

		$thisweek0 = date("Y-m-d",strtotime("-1 week Monday"));
		$thisweek1 = date("Y-m-d",strtotime("this Sunday"));

		$nextweek0 = date("Y-m-d",strtotime("this Monday"));
		$nextweek1 = date("Y-m-d",strtotime("+1 week Sunday"));

		$next7days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")+6, date("Y")));
		$next30days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")+29, date("Y")));
		$next60days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")+59, date("Y")));
		$next90days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")+89, date("Y")));
		$next120days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")+119, date("Y")));

		$last7days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-6, date("Y")));
		$last30days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-29, date("Y")));
		$last60days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-59, date("Y")));
		$last90days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-89, date("Y")));
		$last120days = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-119, date("Y")));

		$currentFY0 = date("Y-m-d",mktime(0, 0, 0, "01", "01",   date("Y")));
		$currentFY1 = date("Y-m-t",mktime(0, 0, 0, "12", date("d"),   date("Y")));
		$lastFY0 = date("Y-m-d",mktime(0, 0, 0, "01", "01",   date("Y")-1));
		$lastFY1 = date("Y-m-t", mktime(0, 0, 0, "12", date("d"), date("Y")-1));
		$nextFY0 = date("Y-m-d",mktime(0, 0, 0, "01", "01",   date("Y")+1));
		$nextFY1 = date("Y-m-t", mktime(0, 0, 0, "12", date("d"), date("Y")+1));

		if(date("m") <= 3)
		{
			$cFq = date("Y-m-d",mktime(0, 0, 0, "01","01",date("Y")));
			$cFq1 = date("Y-m-d",mktime(0, 0, 0, "03","31",date("Y")));
			$nFq = date("Y-m-d",mktime(0, 0, 0, "04","01",date("Y")));
			$nFq1 = date("Y-m-d",mktime(0, 0, 0, "06","30",date("Y")));
			$pFq = date("Y-m-d",mktime(0, 0, 0, "10","01",date("Y")-1));
			$pFq1 = date("Y-m-d",mktime(0, 0, 0, "12","31",date("Y")-1));
		}else if(date("m") > 3 and date("m") <= 6)
		{
			$pFq = date("Y-m-d",mktime(0, 0, 0, "01","01",date("Y")));
			$pFq1 = date("Y-m-d",mktime(0, 0, 0, "03","31",date("Y")));
			$cFq = date("Y-m-d",mktime(0, 0, 0, "04","01",date("Y")));
			$cFq1 = date("Y-m-d",mktime(0, 0, 0, "06","30",date("Y")));
			$nFq = date("Y-m-d",mktime(0, 0, 0, "07","01",date("Y")));
			$nFq1 = date("Y-m-d",mktime(0, 0, 0, "09","30",date("Y")));

		}else if(date("m") > 6 and date("m") <= 9)
		{
			$nFq = date("Y-m-d",mktime(0, 0, 0, "10","01",date("Y")));
			$nFq1 = date("Y-m-d",mktime(0, 0, 0, "12","31",date("Y")));
			$pFq = date("Y-m-d",mktime(0, 0, 0, "04","01",date("Y")));
			$pFq1 = date("Y-m-d",mktime(0, 0, 0, "06","30",date("Y")));
			$cFq = date("Y-m-d",mktime(0, 0, 0, "07","01",date("Y")));
			$cFq1 = date("Y-m-d",mktime(0, 0, 0, "09","30",date("Y")));
		}
		else if(date("m") > 9 and date("m") <= 12)
		{
			$nFq = date("Y-m-d",mktime(0, 0, 0, "01","01",date("Y")+1));
			$nFq1 = date("Y-m-d",mktime(0, 0, 0, "03","31",date("Y")+1));
			$pFq = date("Y-m-d",mktime(0, 0, 0, "07","01",date("Y")));
			$pFq1 = date("Y-m-d",mktime(0, 0, 0, "09","30",date("Y")));
			$cFq = date("Y-m-d",mktime(0, 0, 0, "10","01",date("Y")));
			$cFq1 = date("Y-m-d",mktime(0, 0, 0, "12","31",date("Y")));

		}

		if($type == "today" )
		{

			$datevalue[0] = $today;
			$datevalue[1] = $today;
		}
		elseif($type == "yesterday" )
		{

			$datevalue[0] = $yesterday;
			$datevalue[1] = $yesterday;
		}
		elseif($type == "tomorrow" )
		{

			$datevalue[0] = $tomorrow;
			$datevalue[1] = $tomorrow;
		}
		elseif($type == "thisweek" )
		{

			$datevalue[0] = $thisweek0;
			$datevalue[1] = $thisweek1;
		}
		elseif($type == "lastweek" )
		{

			$datevalue[0] = $lastweek0;
			$datevalue[1] = $lastweek1;
		}
		elseif($type == "nextweek" )
		{

			$datevalue[0] = $nextweek0;
			$datevalue[1] = $nextweek1;
		}
		elseif($type == "thismonth" )
		{

			$datevalue[0] =$currentmonth0;
			$datevalue[1] = $currentmonth1;
		}

		elseif($type == "lastmonth" )
		{

			$datevalue[0] = $lastmonth0;
			$datevalue[1] = $lastmonth1;
		}
		elseif($type == "nextmonth" )
		{

			$datevalue[0] = $nextmonth0;
			$datevalue[1] = $nextmonth1;
		}
		elseif($type == "next7days" )
		{

			$datevalue[0] = $today;
			$datevalue[1] = $next7days;
		}
		elseif($type == "next30days" )
		{

			$datevalue[0] =$today;
			$datevalue[1] =$next30days;
		}
		elseif($type == "next60days" )
		{

			$datevalue[0] = $today;
			$datevalue[1] = $next60days;
		}
		elseif($type == "next90days" )
		{

			$datevalue[0] = $today;
			$datevalue[1] = $next90days;
		}
		elseif($type == "next120days" )
		{

			$datevalue[0] = $today;
			$datevalue[1] = $next120days;
		}
		elseif($type == "last7days" )
		{

			$datevalue[0] = $last7days;
			$datevalue[1] = $today;
		}
		elseif($type == "last30days" )
		{

			$datevalue[0] = $last30days;
			$datevalue[1] =  $today;
		}
		elseif($type == "last60days" )
		{

			$datevalue[0] = $last60days;
			$datevalue[1] = $today;
		}
		else if($type == "last90days" )
		{

			$datevalue[0] = $last90days;
			$datevalue[1] = $today;
		}
		elseif($type == "last120days" )
		{

			$datevalue[0] = $last120days;
			$datevalue[1] = $today;
		}
		elseif($type == "thisfy" )
		{

			$datevalue[0] = $currentFY0;
			$datevalue[1] = $currentFY1;
		}
		elseif($type == "prevfy" )
		{

			$datevalue[0] = $lastFY0;
			$datevalue[1] = $lastFY1;
		}
		elseif($type == "nextfy" )
		{

			$datevalue[0] = $nextFY0;
			$datevalue[1] = $nextFY1;
		}
		elseif($type == "nextfq" )
		{

			$datevalue[0] = $nFq;
			$datevalue[1] = $nFq1;
		}
		elseif($type == "prevfq" )
		{

			$datevalue[0] = $pFq;
			$datevalue[1] = $pFq1;
		}
		elseif($type == "thisfq" )
		{
			$datevalue[0] = $cFq;
			$datevalue[1] = $cFq1;
		}
		else
		{
			$datevalue[0] = "";
			$datevalue[1] = "";
		}
		return $datevalue;
	}

	function hasGroupingList() {
	    global $adb;
	    $result = $adb->pquery('SELECT 1 FROM vtiger_reportsortcol WHERE reportid=? and columnname <> "none"', array($this->reportid));
	    return ($result && $adb->num_rows($result))? true : false;
	}

	/** Function to get getGroupingList for the given reportid
	 *  @ param $reportid : Type Integer
	 *  returns the $grouplist Array in the following format
	 *  		$grouplist = Array($tablename:$columnname:$fieldlabel:fieldname:typeofdata=>$tablename:$columnname $sorder,
	 *				   $tablename1:$columnname1:$fieldlabel1:fieldname1:typeofdata1=>$tablename1:$columnname1 $sorder,
	 *				   $tablename2:$columnname2:$fieldlabel2:fieldname2:typeofdata2=>$tablename2:$columnname2 $sorder)
	 * This function also sets the return value in the class variable $this->groupbylist
	 */


	function getGroupingList($reportid)
	{
		global $adb;
		global $modules;
		global $log;

		// Have we initialized information already?
		if($this->_groupinglist !== false) {
			return $this->_groupinglist;
		}

		$sreportsortsql = " SELECT vtiger_reportsortcol.*, vtiger_reportgroupbycolumn.* FROM vtiger_report";
		$sreportsortsql .= " inner join vtiger_reportsortcol on vtiger_report.reportid = vtiger_reportsortcol.reportid";
        $sreportsortsql .= " LEFT JOIN vtiger_reportgroupbycolumn ON (vtiger_report.reportid = vtiger_reportgroupbycolumn.reportid AND vtiger_reportsortcol.sortcolid = vtiger_reportgroupbycolumn.sortid)";
		$sreportsortsql .= " where vtiger_report.reportid =? AND vtiger_reportsortcol.columnname IN (SELECT columnname from vtiger_selectcolumn WHERE queryid=?) order by vtiger_reportsortcol.sortcolid";

		$result = $adb->pquery($sreportsortsql, array($reportid,$reportid));
		$grouplist = array();

		$inventoryModules = getInventoryModules();
		while($reportsortrow = $adb->fetch_array($result))
		{
			$fieldcolname = $reportsortrow["columnname"];
			list($tablename,$colname,$module_field,$fieldname,$single) = split(":",$fieldcolname);
			$sortorder = $reportsortrow["sortorder"];

			if($sortorder == "Ascending")
			{
				$sortorder = "ASC";

			}elseif($sortorder == "Descending")
			{
				$sortorder = "DESC";
			}

			if($fieldcolname != "none")
			{
				$selectedfields = explode(":",$fieldcolname);
				if($selectedfields[0] == "vtiger_crmentity".$this->primarymodule)
					$selectedfields[0] = "vtiger_crmentity";
				if(stripos($selectedfields[1],'cf_')==0 && stristr($selectedfields[1],'cf_')==true){
					//In sql queries forward slash(/) is treated as query terminator,so to avoid this problem
					//the column names are enclosed within ('[]'),which will treat this as part of column name
					$sqlvalue = "`".$adb->sql_escape_string(decode_html($selectedfields[2]))."` ".$sortorder; 
				} else {
					$sqlvalue = "`".self::replaceSpecialChar($selectedfields[2])."` ".$sortorder; 
				}
				if($selectedfields[4]=="D" && strtolower($reportsortrow["dategroupbycriteria"])!="none"){
					$groupField = $module_field;
					$groupCriteria = $reportsortrow["dategroupbycriteria"];
					if(in_array($groupCriteria,array_keys($this->groupByTimeParent))){
						$parentCriteria = $this->groupByTimeParent[$groupCriteria];
						foreach($parentCriteria as $criteria){
						  $groupByCondition[]=$this->GetTimeCriteriaCondition($criteria, $groupField)." ".$sortorder;
						}
					}
					$groupByCondition[] =$this->GetTimeCriteriaCondition($groupCriteria, $groupField)." ".$sortorder;
					$sqlvalue = implode(", ",$groupByCondition);
				}
				$grouplist[$fieldcolname] = $sqlvalue;
				$temp = split("_",$selectedfields[2],2);
				$module = $temp[0];
				if (in_array($module, $inventoryModules) && $fieldname == 'serviceid') {
					$grouplist[$fieldcolname] = $sqlvalue;
				} else if(CheckFieldPermission($fieldname,$module) == 'true') {
					$grouplist[$fieldcolname] = $sqlvalue;
				} else {
					$grouplist[$fieldcolname] = $selectedfields[0].".".$selectedfields[1];
				}

				$this->queryPlanner->addTable($tablename);
			}
		}

		// Save the information
		$this->_groupinglist = $grouplist;

		$log->info("ReportRun :: Successfully returned getGroupingList".$reportid);
		return $grouplist;
	}

	/** function to replace special characters
	 *  @ param $selectedfield : type string
	 *  this returns the string for grouplist
	 */

	function replaceSpecialChar($selectedfield){
		$selectedfield = decode_html(decode_html($selectedfield));
		preg_match('/&/', $selectedfield, $matches);
		if(!empty($matches)){
			$selectedfield = str_replace('&', 'and',($selectedfield));
		}
		return $selectedfield;
		}

	/** function to get the selectedorderbylist for the given reportid
	 *  @ param $reportid : type integer
	 *  this returns the columns query for the sortorder columns
	 *  this function also sets the return value in the class variable $this->orderbylistsql
	 */


	function getSelectedOrderbyList($reportid)
	{

		global $adb;
		global $modules;
		global $log;

		$sreportsortsql = "select vtiger_reportsortcol.* from vtiger_report";
		$sreportsortsql .= " inner join vtiger_reportsortcol on vtiger_report.reportid = vtiger_reportsortcol.reportid";
		$sreportsortsql .= " where vtiger_report.reportid =? order by vtiger_reportsortcol.sortcolid";

		$result = $adb->pquery($sreportsortsql, array($reportid));
		$noofrows = $adb->num_rows($result);

		for($i=0; $i<$noofrows; $i++)
		{
			$fieldcolname = $adb->query_result($result,$i,"columnname");
			$sortorder = $adb->query_result($result,$i,"sortorder");

			if($sortorder == "Ascending")
			{
				$sortorder = "ASC";
			}
			elseif($sortorder == "Descending")
			{
				$sortorder = "DESC";
			}

			if($fieldcolname != "none")
			{
				$this->orderbylistcolumns[] = $fieldcolname;
				$n = $n + 1;
				$selectedfields = explode(":",$fieldcolname);
				if($n > 1)
				{
					$sSQL .= ", ";
					$this->orderbylistsql .= ", ";
				}
				if($selectedfields[0] == "vtiger_crmentity".$this->primarymodule)
					$selectedfields[0] = "vtiger_crmentity";
				$sSQL .= $selectedfields[0].".".$selectedfields[1]." ".$sortorder;
				$this->orderbylistsql .= $selectedfields[0].".".$selectedfields[1]." ".$selectedfields[2];
			}
		}
		$log->info("ReportRun :: Successfully returned getSelectedOrderbyList".$reportid);
		return $sSQL;
	}

	/** function to get secondary Module for the given Primary module and secondary module
	 *  @ param $module : type String
	 *  @ param $secmodule : type String
	 *  this returns join query for the given secondary module
	 */

	function getRelatedModulesQuery($module,$secmodule)
	{
		global $log,$current_user;
		$query = '';
		if($secmodule!=''){
			$secondarymodule = explode(":",$secmodule);
			foreach($secondarymodule as $key=>$value) {
					$foc = CRMEntity::getInstance($value);

					// Case handling: Force table requirement ahead of time.
					$this->queryPlanner->addTable('vtiger_crmentity'. $value);

					$focQuery = $foc->generateReportsSecQuery($module,$value, $this->queryPlanner);

					if ($focQuery) {
						$query .= $focQuery . getNonAdminAccessControlQuery($value,$current_user,$value);
					}
			}
		}
		$log->info("ReportRun :: Successfully returned getRelatedModulesQuery".$secmodule);

		return $query;

	}
	/** function to get report query for the given module
	 *  @ param $module : type String
	 *  this returns join query for the given module
	 */

	function getReportsQuery($module, $type='')
	{
		global $log, $current_user;
		$secondary_module ="'";
		$secondary_module .= str_replace(":","','",$this->secondarymodule);
		$secondary_module .="'";

		if($module == "Leads")
		{
			$query = "from vtiger_leaddetails
				inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_leaddetails.leadid";

			if ($this->queryPlanner->requireTable('vtiger_leadsubdetails')) {
				$query .= "	inner join vtiger_leadsubdetails on vtiger_leadsubdetails.leadsubscriptionid=vtiger_leaddetails.leadid";
			}
			if ($this->queryPlanner->requireTable('vtiger_leadaddress')) {
				$query .= "	inner join vtiger_leadaddress on vtiger_leadaddress.leadaddressid=vtiger_leaddetails.leadid";
			}
			if ($this->queryPlanner->requireTable('vtiger_leadscf')) {
				$query .= " inner join vtiger_leadscf on vtiger_leaddetails.leadid = vtiger_leadscf.leadid";
			}
			if ($this->queryPlanner->requireTable('vtiger_groupsLeads')) {
				$query .= "	left join vtiger_groups as vtiger_groupsLeads on vtiger_groupsLeads.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable('vtiger_usersLeads')) {
				$query .= " left join vtiger_users as vtiger_usersLeads on vtiger_usersLeads.id = vtiger_crmentity.smownerid";
			}

			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid
				left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable('vtiger_lastModifiedByLeads')) {
				$query .= " left join vtiger_users as vtiger_lastModifiedByLeads on vtiger_lastModifiedByLeads.id = vtiger_crmentity.modifiedby";
			}

			$query .= " " . $this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0 and vtiger_leaddetails.converted=0";
		}
		else if($module == "Accounts")
		{
			$query = "from vtiger_account
				inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_account.accountid";

			if ($this->queryPlanner->requireTable('vtiger_accountbillads')) {
				$query .= " inner join vtiger_accountbillads on vtiger_account.accountid=vtiger_accountbillads.accountaddressid";
			}
			if ($this->queryPlanner->requireTable('vtiger_accountshipads')) {
				$query .= " inner join vtiger_accountshipads on vtiger_account.accountid=vtiger_accountshipads.accountaddressid";
			}
			if ($this->queryPlanner->requireTable('vtiger_accountscf')) {
				$query .= " inner join vtiger_accountscf on vtiger_account.accountid = vtiger_accountscf.accountid";
			}
			if ($this->queryPlanner->requireTable('vtiger_groupsAccounts')) {
				$query .= " left join vtiger_groups as vtiger_groupsAccounts on vtiger_groupsAccounts.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable('vtiger_accountAccounts')) {
				$query .= "	left join vtiger_account as vtiger_accountAccounts on vtiger_accountAccounts.accountid = vtiger_account.parentid";
			}
			if ($this->queryPlanner->requireTable('vtiger_usersAccounts')) {
				$query .= " left join vtiger_users as vtiger_usersAccounts on vtiger_usersAccounts.id = vtiger_crmentity.smownerid";
			}

			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid
				left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable('vtiger_lastModifiedByAccounts')) {
				$query.= " left join vtiger_users as vtiger_lastModifiedByAccounts on vtiger_lastModifiedByAccounts.id = vtiger_crmentity.modifiedby";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0 ";
		}

		else if($module == "Contacts")
		{
			$query = "from vtiger_contactdetails
				inner join vtiger_crmentity on vtiger_crmentity.crmid = vtiger_contactdetails.contactid";

			if ($this->queryPlanner->requireTable('vtiger_contactaddress')) {
				$query .= "	inner join vtiger_contactaddress on vtiger_contactdetails.contactid = vtiger_contactaddress.contactaddressid";
			}
			if ($this->queryPlanner->requireTable('vtiger_customerdetails')) {
				$query .= "	inner join vtiger_customerdetails on vtiger_customerdetails.customerid = vtiger_contactdetails.contactid";
			}
			if ($this->queryPlanner->requireTable('vtiger_contactsubdetails')) {
				$query .= "	inner join vtiger_contactsubdetails on vtiger_contactdetails.contactid = vtiger_contactsubdetails.contactsubscriptionid";
			}
			if ($this->queryPlanner->requireTable('vtiger_contactscf')) {
				$query .= "	inner join vtiger_contactscf on vtiger_contactdetails.contactid = vtiger_contactscf.contactid";
			}
			if ($this->queryPlanner->requireTable('vtiger_groupsContacts')) {
				$query .= " left join vtiger_groups vtiger_groupsContacts on vtiger_groupsContacts.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable('vtiger_contactdetailsContacts')) {
				$query .= "	left join vtiger_contactdetails as vtiger_contactdetailsContacts on vtiger_contactdetailsContacts.contactid = vtiger_contactdetails.reportsto";
			}
			if ($this->queryPlanner->requireTable('vtiger_accountContacts')) {
				$query .= "	left join vtiger_account as vtiger_accountContacts on vtiger_accountContacts.accountid = vtiger_contactdetails.accountid";
			}
			if ($this->queryPlanner->requireTable('vtiger_usersContacts')) {
				$query .= " left join vtiger_users as vtiger_usersContacts on vtiger_usersContacts.id = vtiger_crmentity.smownerid";
			}

			$query .= " left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid
				left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable('vtiger_lastModifiedByContacts')) {
			        $query .= " left join vtiger_users as vtiger_lastModifiedByContacts on vtiger_lastModifiedByContacts.id = vtiger_crmentity.modifiedby";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0";
		}

		else if($module == "Potentials")
		{
			$query = "from vtiger_potential
				inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_potential.potentialid";

			if ($this->queryPlanner->requireTable('vtiger_potentialscf')) {
				$query .= " inner join vtiger_potentialscf on vtiger_potentialscf.potentialid = vtiger_potential.potentialid";
			}
			if ($this->queryPlanner->requireTable('vtiger_accountPotentials')) {
				$query .= " left join vtiger_account as vtiger_accountPotentials on vtiger_potential.related_to = vtiger_accountPotentials.accountid";
			}
			if ($this->queryPlanner->requireTable('vtiger_contactdetailsPotentials')) {
				$query .= " left join vtiger_contactdetails as vtiger_contactdetailsPotentials on vtiger_potential.contact_id = vtiger_contactdetailsPotentials.contactid";
			}
			if ($this->queryPlanner->requireTable('vtiger_campaignPotentials')) {
				$query .= " left join vtiger_campaign as vtiger_campaignPotentials on vtiger_potential.campaignid = vtiger_campaignPotentials.campaignid";
			}
			if ($this->queryPlanner->requireTable('vtiger_groupsPotentials')) {
				$query .= " left join vtiger_groups vtiger_groupsPotentials on vtiger_groupsPotentials.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable('vtiger_usersPotentials')) {
				$query .= " left join vtiger_users as vtiger_usersPotentials on vtiger_usersPotentials.id = vtiger_crmentity.smownerid";
			}

			// TODO optimize inclusion of these tables
			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable('vtiger_lastModifiedByPotentials')) {
				$query .= " left join vtiger_users as vtiger_lastModifiedByPotentials on vtiger_lastModifiedByPotentials.id = vtiger_crmentity.modifiedby";
			}
			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0 ";
		}

		//For this Product - we can related Accounts, Contacts (Also Leads, Potentials)
		else if($module == "Products")
		{
			$query .= " from vtiger_products";
	    		$query .= " inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_products.productid";
			if ($this->queryPlanner->requireTable("vtiger_productcf")){
			    $query .= " left join vtiger_productcf on vtiger_products.productid = vtiger_productcf.productid";
			}
			if ($this->queryPlanner->requireTable("vtiger_lastModifiedByProducts")){
			    $query .= " left join vtiger_users as vtiger_lastModifiedByProducts on vtiger_lastModifiedByProducts.id = vtiger_crmentity.modifiedby";
			}
			if ($this->queryPlanner->requireTable("vtiger_usersProducts")){
			    $query .= " left join vtiger_users as vtiger_usersProducts on vtiger_usersProducts.id = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable("vtiger_groupsProducts")){
			    $query .= " left join vtiger_groups as vtiger_groupsProducts on vtiger_groupsProducts.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable("vtiger_vendorRelProducts")){
			    $query .= " left join vtiger_vendor as vtiger_vendorRelProducts on vtiger_vendorRelProducts.vendorid = vtiger_products.vendor_id";
			}
			if ($this->queryPlanner->requireTable("innerProduct")){
			    $query .= " LEFT JOIN (
						SELECT vtiger_products.productid,
								(CASE WHEN (vtiger_products.currency_id = 1 ) THEN vtiger_products.unit_price
									ELSE (vtiger_products.unit_price / vtiger_currency_info.conversion_rate) END
								) AS actual_unit_price
						FROM vtiger_products
						LEFT JOIN vtiger_currency_info ON vtiger_products.currency_id = vtiger_currency_info.id
						LEFT JOIN vtiger_productcurrencyrel ON vtiger_products.productid = vtiger_productcurrencyrel.productid
						AND vtiger_productcurrencyrel.currencyid = ". $current_user->currency_id . "
				) AS innerProduct ON innerProduct.productid = vtiger_products.productid";
			}
			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
						getNonAdminAccessControlQuery($this->primarymodule,$current_user)."
				where vtiger_crmentity.deleted=0";
		}

		else if($module == "HelpDesk")
		{
			$matrix = $this->queryPlanner->newDependencyMatrix();

			$matrix->setDependency('vtiger_crmentityRelHelpDesk',array('vtiger_accountRelHelpDesk','vtiger_contactdetailsRelHelpDesk'));

			$query = "from vtiger_troubletickets inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_troubletickets.ticketid";

			if ($this->queryPlanner->requireTable('vtiger_ticketcf')) {
				$query .= " inner join vtiger_ticketcf on vtiger_ticketcf.ticketid = vtiger_troubletickets.ticketid";
			}
			if ($this->queryPlanner->requireTable('vtiger_crmentityRelHelpDesk', $matrix)) {
				$query .= " left join vtiger_crmentity as vtiger_crmentityRelHelpDesk on vtiger_crmentityRelHelpDesk.crmid = vtiger_troubletickets.parent_id";
			}
			if ($this->queryPlanner->requireTable('vtiger_accountRelHelpDesk')) {
				$query .= " left join vtiger_account as vtiger_accountRelHelpDesk on vtiger_accountRelHelpDesk.accountid=vtiger_crmentityRelHelpDesk.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_contactdetailsRelHelpDesk')) {
				$query .= " left join vtiger_contactdetails as vtiger_contactdetailsRelHelpDesk on vtiger_contactdetailsRelHelpDesk.contactid= vtiger_troubletickets.contact_id";
			}
			if ($this->queryPlanner->requireTable('vtiger_productsRel')) {
				$query .= " left join vtiger_products as vtiger_productsRel on vtiger_productsRel.productid = vtiger_troubletickets.product_id";
			}
			if ($this->queryPlanner->requireTable('vtiger_groupsHelpDesk')) {
				$query .= " left join vtiger_groups as vtiger_groupsHelpDesk on vtiger_groupsHelpDesk.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable('vtiger_usersHelpDesk')) {
				$query .= " left join vtiger_users as vtiger_usersHelpDesk on vtiger_crmentity.smownerid=vtiger_usersHelpDesk.id";
			}

			// TODO optimize inclusion of these tables
			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " left join vtiger_users on vtiger_crmentity.smownerid=vtiger_users.id";

			if ($this->queryPlanner->requireTable('vtiger_lastModifiedByHelpDesk')) {
				$query .= "  left join vtiger_users as vtiger_lastModifiedByHelpDesk on vtiger_lastModifiedByHelpDesk.id = vtiger_crmentity.modifiedby";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0 ";
		}

		else if($module == "Calendar")
		{

			$matrix = $this->queryPlanner->newDependencyMatrix();

			$matrix->setDependency('vtiger_cntactivityrel', array('vtiger_contactdetailsCalendar'));
			$matrix->setDependency('vtiger_seactivityrel', array('vtiger_crmentityRelCalendar'));
			$matrix->setDependency('vtiger_crmentityRelCalendar', array('vtiger_accountRelCalendar',
				'vtiger_leaddetailsRelCalendar','vtiger_potentialRelCalendar','vtiger_quotesRelCalendar',
				'vtiger_purchaseorderRelCalendar', 'vtiger_invoiceRelCalendar', 'vtiger_salesorderRelCalendar',
				'vtiger_troubleticketsRelCalendar', 'vtiger_campaignRelCalendar'
			));

			$query = "from vtiger_activity
				inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_activity.activityid";

			if ($this->queryPlanner->requireTable('vtiger_activitycf')) {
				$query .= " left join vtiger_activitycf on vtiger_activitycf.activityid = vtiger_crmentity.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_cntactivityrel', $matrix)) {
				$query .= " left join vtiger_cntactivityrel on vtiger_cntactivityrel.activityid= vtiger_activity.activityid";
			}
			if ($this->queryPlanner->requireTable('vtiger_contactdetailsCalendar')) {
				$query .= " left join vtiger_contactdetails as vtiger_contactdetailsCalendar on vtiger_contactdetailsCalendar.contactid= vtiger_cntactivityrel.contactid";
			}
			if ($this->queryPlanner->requireTable('vtiger_groupsCalendar')) {
				$query .= " left join vtiger_groups as vtiger_groupsCalendar on vtiger_groupsCalendar.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable('vtiger_usersCalendar')) {
				$query .= " left join vtiger_users as vtiger_usersCalendar on vtiger_usersCalendar.id = vtiger_crmentity.smownerid";
			}

			// TODO optimize inclusion of these tables
			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable('vtiger_seactivityrel', $matrix)) {
				$query .= " left join vtiger_seactivityrel on vtiger_seactivityrel.activityid = vtiger_activity.activityid";
			}
			if ($this->queryPlanner->requireTable('vtiger_activity_reminder')) {
				$query .= " left join vtiger_activity_reminder on vtiger_activity_reminder.activity_id = vtiger_activity.activityid";
			}
			if ($this->queryPlanner->requireTable('vtiger_recurringevents')) {
				$query .= " left join vtiger_recurringevents on vtiger_recurringevents.activityid = vtiger_activity.activityid";
			}
			if ($this->queryPlanner->requireTable('vtiger_crmentityRelCalendar', $matrix)) {
				$query .= " left join vtiger_crmentity as vtiger_crmentityRelCalendar on vtiger_crmentityRelCalendar.crmid = vtiger_seactivityrel.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_accountRelCalendar')) {
				$query .= " left join vtiger_account as vtiger_accountRelCalendar on vtiger_accountRelCalendar.accountid=vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_leaddetailsRelCalendar')) {
				$query .= " left join vtiger_leaddetails as vtiger_leaddetailsRelCalendar on vtiger_leaddetailsRelCalendar.leadid = vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_potentialRelCalendar')) {
				$query .= " left join vtiger_potential as vtiger_potentialRelCalendar on vtiger_potentialRelCalendar.potentialid = vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_quotesRelCalendar')) {
				$query .= " left join vtiger_quotes as vtiger_quotesRelCalendar on vtiger_quotesRelCalendar.quoteid = vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_purchaseorderRelCalendar')) {
				$query .= " left join vtiger_purchaseorder as vtiger_purchaseorderRelCalendar on vtiger_purchaseorderRelCalendar.purchaseorderid = vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_invoiceRelCalendar')) {
				$query .= " left join vtiger_invoice as vtiger_invoiceRelCalendar on vtiger_invoiceRelCalendar.invoiceid = vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_salesorderRelCalendar')) {
				$query .= " left join vtiger_salesorder as vtiger_salesorderRelCalendar on vtiger_salesorderRelCalendar.salesorderid = vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_troubleticketsRelCalendar')) {
				$query .= " left join vtiger_troubletickets as vtiger_troubleticketsRelCalendar on vtiger_troubleticketsRelCalendar.ticketid = vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_campaignRelCalendar')) {
				$query .= " left join vtiger_campaign as vtiger_campaignRelCalendar on vtiger_campaignRelCalendar.campaignid = vtiger_crmentityRelCalendar.crmid";
			}
			if ($this->queryPlanner->requireTable('vtiger_lastModifiedByCalendar')) {
				$query .= " left join vtiger_users as vtiger_lastModifiedByCalendar on vtiger_lastModifiedByCalendar.id = vtiger_crmentity.modifiedby";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" WHERE vtiger_crmentity.deleted=0 and (vtiger_activity.activitytype != 'Emails')";
		}

		else if($module == "Quotes")
		{
			$matrix = $this->queryPlanner->newDependencyMatrix();

			$matrix->setDependency('vtiger_inventoryproductrelQuotes',array('vtiger_productsQuotes','vtiger_serviceQuotes'));

			$query = "from vtiger_quotes
			inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_quotes.quoteid";

			if ($this->queryPlanner->requireTable('vtiger_quotesbillads')){
				$query .= " inner join vtiger_quotesbillads on vtiger_quotes.quoteid=vtiger_quotesbillads.quotebilladdressid";
			}
			if ($this->queryPlanner->requireTable('vtiger_quotesshipads')){
				$query .= " inner join vtiger_quotesshipads on vtiger_quotes.quoteid=vtiger_quotesshipads.quoteshipaddressid";
			}
			if ($this->queryPlanner->requireTable("vtiger_currency_info$module")){
				$query .= " left join vtiger_currency_info as vtiger_currency_info$module on vtiger_currency_info$module.id = vtiger_quotes.currency_id";
			}
			if($type !== 'COLUMNSTOTOTAL' || $this->lineItemFieldsInCalculation == true) {
				if ($this->queryPlanner->requireTable("vtiger_inventoryproductrelQuotes", $matrix)){
					$query .= " left join vtiger_inventoryproductrel as vtiger_inventoryproductrelQuotes on vtiger_quotes.quoteid = vtiger_inventoryproductrelQuotes.id";
				}
				if ($this->queryPlanner->requireTable("vtiger_productsQuotes")){
					$query .= " left join vtiger_products as vtiger_productsQuotes on vtiger_productsQuotes.productid = vtiger_inventoryproductrelQuotes.productid";
				}
				if ($this->queryPlanner->requireTable("vtiger_serviceQuotes")){
					$query .= " left join vtiger_service as vtiger_serviceQuotes on vtiger_serviceQuotes.serviceid = vtiger_inventoryproductrelQuotes.productid";
				}
			}
			if ($this->queryPlanner->requireTable("vtiger_quotescf")){
				$query .= " left join vtiger_quotescf on vtiger_quotes.quoteid = vtiger_quotescf.quoteid";
			}
			if ($this->queryPlanner->requireTable("vtiger_groupsQuotes")){
				$query .= " left join vtiger_groups as vtiger_groupsQuotes on vtiger_groupsQuotes.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable("vtiger_usersQuotes")){
				$query .= " left join vtiger_users as vtiger_usersQuotes on vtiger_usersQuotes.id = vtiger_crmentity.smownerid";
			}

			// TODO optimize inclusion of these tables
			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable("vtiger_lastModifiedByQuotes")){
				$query .= " left join vtiger_users as vtiger_lastModifiedByQuotes on vtiger_lastModifiedByQuotes.id = vtiger_crmentity.modifiedby";
			}
			if ($this->queryPlanner->requireTable("vtiger_usersRel1")){
				$query .= " left join vtiger_users as vtiger_usersRel1 on vtiger_usersRel1.id = vtiger_quotes.inventorymanager";
			}
			if ($this->queryPlanner->requireTable("vtiger_potentialRelQuotes")){
				$query .= " left join vtiger_potential as vtiger_potentialRelQuotes on vtiger_potentialRelQuotes.potentialid = vtiger_quotes.potentialid";
			}
			if ($this->queryPlanner->requireTable("vtiger_contactdetailsQuotes")){
				$query .= " left join vtiger_contactdetails as vtiger_contactdetailsQuotes on vtiger_contactdetailsQuotes.contactid = vtiger_quotes.contactid";
			}
			if ($this->queryPlanner->requireTable("vtiger_accountQuotes")){
				$query .= " left join vtiger_account as vtiger_accountQuotes on vtiger_accountQuotes.accountid = vtiger_quotes.accountid";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0";
		}

		else if($module == "PurchaseOrder")
		{

			$matrix = $this->queryPlanner->newDependencyMatrix();

			$matrix->setDependency('vtiger_inventoryproductrelPurchaseOrder',array('vtiger_productsPurchaseOrder','vtiger_servicePurchaseOrder'));

			$query = "from vtiger_purchaseorder
			inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_purchaseorder.purchaseorderid";

			if ($this->queryPlanner->requireTable("vtiger_pobillads")){
				$query .= " inner join vtiger_pobillads on vtiger_purchaseorder.purchaseorderid=vtiger_pobillads.pobilladdressid";
			}
			if ($this->queryPlanner->requireTable("vtiger_poshipads")){
				$query .= " inner join vtiger_poshipads on vtiger_purchaseorder.purchaseorderid=vtiger_poshipads.poshipaddressid";
			}
			if ($this->queryPlanner->requireTable("vtiger_currency_info$module")){
				$query .= " left join vtiger_currency_info as vtiger_currency_info$module on vtiger_currency_info$module.id = vtiger_purchaseorder.currency_id";
			}
			if($type !== 'COLUMNSTOTOTAL' || $this->lineItemFieldsInCalculation == true) {
				if ($this->queryPlanner->requireTable("vtiger_inventoryproductrelPurchaseOrder",$matrix)){
					$query .= " left join vtiger_inventoryproductrel as vtiger_inventoryproductrelPurchaseOrder on vtiger_purchaseorder.purchaseorderid = vtiger_inventoryproductrelPurchaseOrder.id";
				}
				if ($this->queryPlanner->requireTable("vtiger_productsPurchaseOrder")){
					$query .= " left join vtiger_products as vtiger_productsPurchaseOrder on vtiger_productsPurchaseOrder.productid = vtiger_inventoryproductrelPurchaseOrder.productid";
				}
				if ($this->queryPlanner->requireTable("vtiger_servicePurchaseOrder")){
					$query .= " left join vtiger_service as vtiger_servicePurchaseOrder on vtiger_servicePurchaseOrder.serviceid = vtiger_inventoryproductrelPurchaseOrder.productid";
				}
			}
			if ($this->queryPlanner->requireTable("vtiger_purchaseordercf")){
				$query .= " left join vtiger_purchaseordercf on vtiger_purchaseorder.purchaseorderid = vtiger_purchaseordercf.purchaseorderid";
			}
			if ($this->queryPlanner->requireTable("vtiger_groupsPurchaseOrder")){
				$query .= " left join vtiger_groups as vtiger_groupsPurchaseOrder on vtiger_groupsPurchaseOrder.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable("vtiger_usersPurchaseOrder")){
				$query .= " left join vtiger_users as vtiger_usersPurchaseOrder on vtiger_usersPurchaseOrder.id = vtiger_crmentity.smownerid";
			}

			// TODO optimize inclusion of these tables
			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable("vtiger_lastModifiedByPurchaseOrder")){
				$query .= " left join vtiger_users as vtiger_lastModifiedByPurchaseOrder on vtiger_lastModifiedByPurchaseOrder.id = vtiger_crmentity.modifiedby";
			}
			if ($this->queryPlanner->requireTable("vtiger_vendorRelPurchaseOrder")){
				$query .= " left join vtiger_vendor as vtiger_vendorRelPurchaseOrder on vtiger_vendorRelPurchaseOrder.vendorid = vtiger_purchaseorder.vendorid";
			}
			if ($this->queryPlanner->requireTable("vtiger_contactdetailsPurchaseOrder")){
				$query .= " left join vtiger_contactdetails as vtiger_contactdetailsPurchaseOrder on vtiger_contactdetailsPurchaseOrder.contactid = vtiger_purchaseorder.contactid";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0";
		}

		else if($module == "Invoice")
		{
			$matrix = $this->queryPlanner->newDependencyMatrix();

			$matrix->setDependency('vtiger_inventoryproductrelInvoice',array('vtiger_productsInvoice','vtiger_serviceInvoice'));

			$query = "from vtiger_invoice
			inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_invoice.invoiceid";

			if ($this->queryPlanner->requireTable("vtiger_invoicebillads")){
				$query .=" inner join vtiger_invoicebillads on vtiger_invoice.invoiceid=vtiger_invoicebillads.invoicebilladdressid";
			}
			if ($this->queryPlanner->requireTable("vtiger_invoiceshipads")){
				$query .=" inner join vtiger_invoiceshipads on vtiger_invoice.invoiceid=vtiger_invoiceshipads.invoiceshipaddressid";
			}
			if ($this->queryPlanner->requireTable("vtiger_currency_info$module")){
				$query .=" left join vtiger_currency_info as vtiger_currency_info$module on vtiger_currency_info$module.id = vtiger_invoice.currency_id";
			}
			// lineItemFieldsInCalculation - is used to when line item fields are used in calculations
			if($type !== 'COLUMNSTOTOTAL' || $this->lineItemFieldsInCalculation == true) {
			// should be present on when line item fields are selected for calculation
				if ($this->queryPlanner->requireTable("vtiger_inventoryproductrelInvoice",$matrix)){
					$query .=" left join vtiger_inventoryproductrel as vtiger_inventoryproductrelInvoice on vtiger_invoice.invoiceid = vtiger_inventoryproductrelInvoice.id";
				}
				if ($this->queryPlanner->requireTable("vtiger_productsInvoice")){
					$query .=" left join vtiger_products as vtiger_productsInvoice on vtiger_productsInvoice.productid = vtiger_inventoryproductrelInvoice.productid";
				}
				if ($this->queryPlanner->requireTable("vtiger_serviceInvoice")){
					$query .=" left join vtiger_service as vtiger_serviceInvoice on vtiger_serviceInvoice.serviceid = vtiger_inventoryproductrelInvoice.productid";
				}
			}
			if ($this->queryPlanner->requireTable("vtiger_salesorderInvoice")){
				$query .= " left join vtiger_salesorder as vtiger_salesorderInvoice on vtiger_salesorderInvoice.salesorderid=vtiger_invoice.salesorderid";
			}
			if ($this->queryPlanner->requireTable("vtiger_invoicecf")){
				$query .= " left join vtiger_invoicecf on vtiger_invoice.invoiceid = vtiger_invoicecf.invoiceid";
			}
			if ($this->queryPlanner->requireTable("vtiger_groupsInvoice")){
				$query .= " left join vtiger_groups as vtiger_groupsInvoice on vtiger_groupsInvoice.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable("vtiger_usersInvoice")){
				$query .= " left join vtiger_users as vtiger_usersInvoice on vtiger_usersInvoice.id = vtiger_crmentity.smownerid";
			}

			// TODO optimize inclusion of these tables
			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable("vtiger_lastModifiedByInvoice")){
				$query .= " left join vtiger_users as vtiger_lastModifiedByInvoice on vtiger_lastModifiedByInvoice.id = vtiger_crmentity.modifiedby";
			}
			if ($this->queryPlanner->requireTable("vtiger_accountInvoice")){
				$query .= " left join vtiger_account as vtiger_accountInvoice on vtiger_accountInvoice.accountid = vtiger_invoice.accountid";
			}
			if ($this->queryPlanner->requireTable("vtiger_contactdetailsInvoice")){
				$query .= " left join vtiger_contactdetails as vtiger_contactdetailsInvoice on vtiger_contactdetailsInvoice.contactid = vtiger_invoice.contactid";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0";
		}
		else if($module == "SalesOrder")
		{
			$matrix = $this->queryPlanner->newDependencyMatrix();

			$matrix->setDependency('vtiger_inventoryproductrelSalesOrder',array('vtiger_productsSalesOrder','vtiger_serviceSalesOrder'));

			$query = "from vtiger_salesorder
			inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_salesorder.salesorderid";

			if ($this->queryPlanner->requireTable("vtiger_sobillads")){
				$query .= " inner join vtiger_sobillads on vtiger_salesorder.salesorderid=vtiger_sobillads.sobilladdressid";
			}
			if ($this->queryPlanner->requireTable("vtiger_soshipads")){
				$query .= " inner join vtiger_soshipads on vtiger_salesorder.salesorderid=vtiger_soshipads.soshipaddressid";
			}
			if ($this->queryPlanner->requireTable("vtiger_currency_info$module")){
				$query .= " left join vtiger_currency_info as vtiger_currency_info$module on vtiger_currency_info$module.id = vtiger_salesorder.currency_id";
			}
			if($type !== 'COLUMNSTOTOTAL' || $this->lineItemFieldsInCalculation == true) {
				if ($this->queryPlanner->requireTable("vtiger_inventoryproductrelSalesOrder",$matrix)){
					$query .= " left join vtiger_inventoryproductrel as vtiger_inventoryproductrelSalesOrder on vtiger_salesorder.salesorderid = vtiger_inventoryproductrelSalesOrder.id";
				}
				if ($this->queryPlanner->requireTable("vtiger_productsSalesOrder")){
					$query .= " left join vtiger_products as vtiger_productsSalesOrder on vtiger_productsSalesOrder.productid = vtiger_inventoryproductrelSalesOrder.productid";
				}
				if ($this->queryPlanner->requireTable("vtiger_serviceSalesOrder")){
					$query .= " left join vtiger_service as vtiger_serviceSalesOrder on vtiger_serviceSalesOrder.serviceid = vtiger_inventoryproductrelSalesOrder.productid";
				}
			}
			if ($this->queryPlanner->requireTable("vtiger_salesordercf")){
				$query .=" left join vtiger_salesordercf on vtiger_salesorder.salesorderid = vtiger_salesordercf.salesorderid";
			}
			if ($this->queryPlanner->requireTable("vtiger_contactdetailsSalesOrder")){
				$query .= " left join vtiger_contactdetails as vtiger_contactdetailsSalesOrder on vtiger_contactdetailsSalesOrder.contactid = vtiger_salesorder.contactid";
			}
			if ($this->queryPlanner->requireTable("vtiger_quotesSalesOrder")){
				$query .= " left join vtiger_quotes as vtiger_quotesSalesOrder on vtiger_quotesSalesOrder.quoteid = vtiger_salesorder.quoteid";
			}
			if ($this->queryPlanner->requireTable("vtiger_accountSalesOrder")){
				$query .= " left join vtiger_account as vtiger_accountSalesOrder on vtiger_accountSalesOrder.accountid = vtiger_salesorder.accountid";
			}
			if ($this->queryPlanner->requireTable("vtiger_potentialRelSalesOrder")){
				$query .= " left join vtiger_potential as vtiger_potentialRelSalesOrder on vtiger_potentialRelSalesOrder.potentialid = vtiger_salesorder.potentialid";
			}
			if ($this->queryPlanner->requireTable("vtiger_invoice_recurring_info")){
				$query .= " left join vtiger_invoice_recurring_info on vtiger_invoice_recurring_info.salesorderid = vtiger_salesorder.salesorderid";
			}
			if ($this->queryPlanner->requireTable("vtiger_groupsSalesOrder")){
				$query .= " left join vtiger_groups as vtiger_groupsSalesOrder on vtiger_groupsSalesOrder.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable("vtiger_usersSalesOrder")){
				$query .= " left join vtiger_users as vtiger_usersSalesOrder on vtiger_usersSalesOrder.id = vtiger_crmentity.smownerid";
			}

			// TODO optimize inclusion of these tables
			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable("vtiger_lastModifiedBySalesOrder")){
				$query .= " left join vtiger_users as vtiger_lastModifiedBySalesOrder on vtiger_lastModifiedBySalesOrder.id = vtiger_crmentity.modifiedby";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0";
		}
		else if($module == "Campaigns")
		{
			$query = "from vtiger_campaign
			inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_campaign.campaignid";
			if ($this->queryPlanner->requireTable("vtiger_campaignscf")){
				$query .= " inner join vtiger_campaignscf as vtiger_campaignscf on vtiger_campaignscf.campaignid=vtiger_campaign.campaignid";
			}
			if ($this->queryPlanner->requireTable("vtiger_productsCampaigns")){
				$query .= " left join vtiger_products as vtiger_productsCampaigns on vtiger_productsCampaigns.productid = vtiger_campaign.product_id";
				}
			if ($this->queryPlanner->requireTable("vtiger_groupsCampaigns")){
				$query .= " left join vtiger_groups as vtiger_groupsCampaigns on vtiger_groupsCampaigns.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable("vtiger_usersCampaigns")){
				$query .= " left join vtiger_users as vtiger_usersCampaigns on vtiger_usersCampaigns.id = vtiger_crmentity.smownerid";
			}

			// TODO optimize inclusion of these tables
			$query .= " left join vtiger_groups on vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " left join vtiger_users on vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable("vtiger_lastModifiedBy$module")){
				$query .= " left join vtiger_users as vtiger_lastModifiedBy".$module." on vtiger_lastModifiedBy".$module.".id = vtiger_crmentity.modifiedby";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" where vtiger_crmentity.deleted=0";
		}
		else if($module == "Emails") {
			$query = "FROM vtiger_activity
			INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = vtiger_activity.activityid AND vtiger_activity.activitytype = 'Emails'";

			if ($this->queryPlanner->requireTable("vtiger_email_track")){
				$query .= " LEFT JOIN vtiger_email_track ON vtiger_email_track.mailid = vtiger_activity.activityid";
			}
			if ($this->queryPlanner->requireTable("vtiger_groupsEmails")){
				$query .= " LEFT JOIN vtiger_groups AS vtiger_groupsEmails ON vtiger_groupsEmails.groupid = vtiger_crmentity.smownerid";
			}
			if ($this->queryPlanner->requireTable("vtiger_usersEmails")){
				$query .= " LEFT JOIN vtiger_users AS vtiger_usersEmails ON vtiger_usersEmails.id = vtiger_crmentity.smownerid";
			}

			// TODO optimize inclusion of these tables
			$query .= " LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid";
			$query .= " LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid";

			if ($this->queryPlanner->requireTable("vtiger_lastModifiedBy$module")){
				$query .= " LEFT JOIN vtiger_users AS vtiger_lastModifiedBy".$module." ON vtiger_lastModifiedBy".$module.".id = vtiger_crmentity.modifiedby";
			}

			$query .= " ".$this->getRelatedModulesQuery($module,$this->secondarymodule).
					getNonAdminAccessControlQuery($this->primarymodule,$current_user).
					" WHERE vtiger_crmentity.deleted = 0";

		}
		else {
			if($module!=''){
				$focus = CRMEntity::getInstance($module);
				$query = $focus->generateReportsQuery($module, $this->queryPlanner).
						$this->getRelatedModulesQuery($module,$this->secondarymodule).
						getNonAdminAccessControlQuery($this->primarymodule,$current_user).
						" WHERE vtiger_crmentity.deleted=0";
			}
		}
		$log->info("ReportRun :: Successfully returned getReportsQuery".$module);

		return $query;
	}


	/** function to get query for the given reportid,filterlist,type
	 *  @ param $reportid : Type integer
	 *  @ param $filtersql : Type Array
	 *  @ param $module : Type String
	 *  this returns join query for the report
	 */

	function sGetSQLforReport($reportid,$filtersql,$type='',$chartReport=false,$startLimit=false,$endLimit=false)
	{
		global $log;

		$columnlist = $this->getQueryColumnsList($reportid,$type);
		$groupslist = $this->getGroupingList($reportid);
		$groupTimeList = $this->getGroupByTimeList($reportid);
		$stdfilterlist = $this->getStdFilterList($reportid);
		$columnstotallist = $this->getColumnsTotal($reportid);
		$advfiltersql = $this->getAdvFilterSql($reportid);

		$this->totallist = $columnstotallist;
		global $current_user;
		$tab_id = getTabid($this->primarymodule);
		//Fix for ticket #4915.
		$selectlist = $columnlist;
		//columns list
		if(isset($selectlist))
		{
			$selectedcolumns =  implode(", ",$selectlist);
			if($chartReport == true){
				$selectedcolumns .= ", count(*) AS 'groupby_count'";
			}
		}
		//groups list
		if(isset($groupslist))
		{
			$groupsquery = implode(", ",$groupslist);
		}
		if(isset($groupTimeList)){
           	$groupTimeQuery = implode(", ",$groupTimeList);
        }

		//standard list
		if(isset($stdfilterlist))
		{
			$stdfiltersql = implode(", ",$stdfilterlist);
		}
		//columns to total list
		if(isset($columnstotallist))
		{
			$columnstotalsql = implode(", ",$columnstotallist);
		}
		if($stdfiltersql != "")
		{
			$wheresql = " and ".$stdfiltersql;
		}

		if(isset($filtersql) && $filtersql !== false) {
			$advfiltersql = $filtersql;
		}
		if($advfiltersql != "") {
			$wheresql .= " and ".$advfiltersql;
		}

		$reportquery = $this->getReportsQuery($this->primarymodule, $type);

		// If we don't have access to any columns, let us select one column and limit result to shown we have not results
                // Fix for: http://trac.vtiger.com/cgi-bin/trac.cgi/ticket/4758 - Prasad
		$allColumnsRestricted = false;

		if($type == 'COLUMNSTOTOTAL')
		{
			if($columnstotalsql != '')
			{
				$reportquery = "select ".$columnstotalsql." ".$reportquery." ".$wheresql;
			}
		}else
		{
			if($selectedcolumns == '') {
				// Fix for: http://trac.vtiger.com/cgi-bin/trac.cgi/ticket/4758 - Prasad

				$selectedcolumns = "''"; // "''" to get blank column name
				$allColumnsRestricted = true;
			}
			$reportquery = "select DISTINCT ".$selectedcolumns." ".$reportquery." ".$wheresql;
		}
		$reportquery = listQueryNonAdminChange($reportquery, $this->primarymodule);

		if(trim($groupsquery) != "" && $type !== 'COLUMNSTOTOTAL')
		{
            if($chartReport == true){
                $reportquery .= "group by ".$this->GetFirstSortByField($reportid);
            }else{
                $reportquery .= " order by ".$groupsquery;
			}
		}

		// Prasad: No columns selected so limit the number of rows directly.
		if($allColumnsRestricted) {
			$reportquery .= " limit 0";
		} else if($startLimit !== false && $endLimit !== false) {
			$reportquery .= " LIMIT $startLimit, $endLimit";
		}

		preg_match('/&amp;/', $reportquery, $matches);
        if(!empty($matches)){
            $report=str_replace('&amp;', '&', $reportquery);
            $reportquery = $this->replaceSpecialChar($report);
        }
		$log->info("ReportRun :: Successfully returned sGetSQLforReport".$reportid);

		$this->queryPlanner->initializeTempTables();

		return $reportquery;

	}

	/** function to get the report output in HTML,PDF,TOTAL,PRINT,PRINTTOTAL formats depends on the argument $outputformat
	 *  @ param $outputformat : Type String (valid parameters HTML,PDF,TOTAL,PRINT,PRINT_TOTAL)
	 *  @ param $filtersql : Type String
	 *  This returns HTML Report if $outputformat is HTML
         *  		Array for PDF if  $outputformat is PDF
	 *		HTML strings for TOTAL if $outputformat is TOTAL
	 *		Array for PRINT if $outputformat is PRINT
	 *		HTML strings for TOTAL fields  if $outputformat is PRINTTOTAL
	 *		HTML strings for
	 */

	// Performance Optimization: Added parameter directOutput to avoid building big-string!
	function GenerateReport($outputformat,$filtersql, $directOutput=false, $startLimit=false, $endLimit=false)
	{
		global $adb,$current_user,$php_max_execution_time;
		global $modules,$app_strings;
		global $mod_strings,$current_language;
		require('user_privileges/user_privileges_'.$current_user->id.'.php');
		$modules_selected = array();
		$modules_selected[] = $this->primarymodule;
		if(!empty($this->secondarymodule)){
			$sec_modules = split(":",$this->secondarymodule);
			for($i=0;$i<count($sec_modules);$i++){
				$modules_selected[] = $sec_modules[$i];
			}
		}

		// Update Reference fields list list
		$referencefieldres = $adb->pquery("SELECT tabid, fieldlabel, uitype from vtiger_field WHERE uitype in (10,101)", array());
		if($referencefieldres) {
			foreach($referencefieldres as $referencefieldrow) {
				$uiType = $referencefieldrow['uitype'];
				$modprefixedlabel = getTabModuleName($referencefieldrow['tabid']).' '.$referencefieldrow['fieldlabel'];
				$modprefixedlabel = str_replace(' ','_',$modprefixedlabel);

				if($uiType == 10 && !in_array($modprefixedlabel, $this->ui10_fields)) {
					$this->ui10_fields[] = $modprefixedlabel;
				} elseif($uiType == 101 && !in_array($modprefixedlabel, $this->ui101_fields)) {
					$this->ui101_fields[] = $modprefixedlabel;
				}
			}
		}

		if($outputformat == "HTML")
		{
			$sSQL = $this->sGetSQLforReport($this->reportid,$filtersql,$outputformat,false,$startLimit,$endLimit);
			$sSQL .= " LIMIT 0, " . (self::$HTMLVIEW_MAX_ROWS+1); // Pull a record more than limit

			$result = $adb->query($sSQL);
			$error_msg = $adb->database->ErrorMsg();
			if(!$result && $error_msg!=''){
				// Performance Optimization: If direct output is requried
				if($directOutput) {
					echo getTranslatedString('LBL_REPORT_GENERATION_FAILED', $currentModule) . "<br>" . $error_msg;
					$error_msg = false;
				}
				// END
				return $error_msg;
			}

			// Performance Optimization: If direct output is required
			if($directOutput) {
				echo '<table cellpadding="5" cellspacing="0" align="center" class="rptTable"><tr>';
			}
			// END

			if($is_admin==false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1)
				$picklistarray = $this->getAccessPickListValues();
			if($result)
			{
				$y=$adb->num_fields($result);
				$arrayHeaders = Array();
				for ($x=0; $x<$y; $x++)
				{
					$fld = $adb->field_name($result, $x);
					if(in_array($this->getLstringforReportHeaders($fld->name), $arrayHeaders))
					{
						$headerLabel = str_replace("_"," ",$fld->name);
						$arrayHeaders[] = $headerLabel;
					}
					else
					{
						$headerLabel = str_replace($modules," ",$this->getLstringforReportHeaders($fld->name));
						$headerLabel = str_replace("_"," ",$this->getLstringforReportHeaders($fld->name));
						$arrayHeaders[] = $headerLabel;
					}
					/*STRING TRANSLATION starts */
					$mod_name = split(' ',$headerLabel,2);
					$moduleLabel ='';
					if(in_array($mod_name[0],$modules_selected)){
						$moduleLabel = getTranslatedString($mod_name[0],$mod_name[0]);
					}

					if(!empty($this->secondarymodule)){
						if($moduleLabel!=''){
							$headerLabel_tmp = $moduleLabel." ".getTranslatedString($mod_name[1],$mod_name[0]);
						} else {
							$headerLabel_tmp = getTranslatedString($mod_name[0]." ".$mod_name[1]);
						}
					} else {
						if($moduleLabel!=''){
							$headerLabel_tmp = getTranslatedString($mod_name[1],$mod_name[0]);
						} else {
							$headerLabel_tmp = getTranslatedString($mod_name[0]." ".$mod_name[1]);
						}
					}
					if($headerLabel == $headerLabel_tmp) $headerLabel = getTranslatedString($headerLabel_tmp);
					else $headerLabel = $headerLabel_tmp;
					/*STRING TRANSLATION ends */
					$header .= "<td class='rptCellLabel'>".$headerLabel."</td>";

					// Performance Optimization: If direct output is required
					if($directOutput) {
						echo $header;
						$header = '';
					}
					// END
				}

				// Performance Optimization: If direct output is required
				if($directOutput) {
					echo '</tr><tr>';
				}
				// END

				$noofrows = $adb->num_rows($result);
				$custom_field_values = $adb->fetch_array($result);
				$groupslist = $this->getGroupingList($this->reportid);

				$column_definitions = $adb->getFieldsDefinition($result);

				do
				{
					$arraylists = Array();
					if(count($groupslist) == 1)
					{
						$newvalue = $custom_field_values[0];
					}elseif(count($groupslist) == 2)
					{
						$newvalue = $custom_field_values[0];
						$snewvalue = $custom_field_values[1];
					}elseif(count($groupslist) == 3)
					{
						$newvalue = $custom_field_values[0];
						$snewvalue = $custom_field_values[1];
						$tnewvalue = $custom_field_values[2];
					}
					if($newvalue == "") $newvalue = "-";

					if($snewvalue == "") $snewvalue = "-";

					if($tnewvalue == "") $tnewvalue = "-";

					$valtemplate .= "<tr>";

					// Performance Optimization
					if($directOutput) {
						echo $valtemplate;
						$valtemplate = '';
					}
					// END

					for ($i=0; $i<$y; $i++)
					{
						$fld = $adb->field_name($result, $i);
						$fld_type = $column_definitions[$i]->type;
						$fieldvalue = getReportFieldValue($this, $picklistarray, $fld,
								$custom_field_values, $i);

					//check for Roll based pick list
						$temp_val= $fld->name;

						if($fieldvalue == "" )
						{
							$fieldvalue = "-";
						}
						else if($fld->name == $this->primarymodule.'_LBL_ACTION' && $fieldvalue != '-')
						{
							$fieldvalue = "<a href='index.php?module={$this->primarymodule}&action=DetailView&record={$fieldvalue}' target='_blank'>".getTranslatedString('LBL_VIEW_DETAILS')."</a>";
						}

						if(($lastvalue == $fieldvalue) && $this->reporttype == "summary")
						{
							if($this->reporttype == "summary")
							{
								$valtemplate .= "<td class='rptEmptyGrp'>&nbsp;</td>";
							}else
							{
								$valtemplate .= "<td class='rptData'>".$fieldvalue."</td>";
							}
						}else if(($secondvalue === $fieldvalue) && $this->reporttype == "summary")
						{
							if($lastvalue === $newvalue)
							{
								$valtemplate .= "<td class='rptEmptyGrp'>&nbsp;</td>";
							}else
							{
								$valtemplate .= "<td class='rptGrpHead'>".$fieldvalue."</td>";
							}
						}
						else if(($thirdvalue === $fieldvalue) && $this->reporttype == "summary")
						{
							if($secondvalue === $snewvalue)
							{
								$valtemplate .= "<td class='rptEmptyGrp'>&nbsp;</td>";
							}else
							{
								$valtemplate .= "<td class='rptGrpHead'>".$fieldvalue."</td>";
							}
						}
						else
						{
							if($this->reporttype == "tabular")
							{
								$valtemplate .= "<td class='rptData'>".$fieldvalue."</td>";
							}else
							{
								$valtemplate .= "<td class='rptGrpHead'>".$fieldvalue."</td>";
							}
						}

						// Performance Optimization: If direct output is required
						if($directOutput) {
							echo $valtemplate;
							$valtemplate = '';
						}
						// END
					}

					$valtemplate .= "</tr>";

					// Performance Optimization: If direct output is required
					if($directOutput) {
						echo $valtemplate;
						$valtemplate = '';
					}
					// END

					$lastvalue = $newvalue;
					$secondvalue = $snewvalue;
					$thirdvalue = $tnewvalue;
					$arr_val[] = $arraylists;
					set_time_limit($php_max_execution_time);
				}while($custom_field_values = $adb->fetch_array($result));

				// Performance Optimization: Provide feedback on export option if required
				// NOTE: We should make sure to pull at-least 1 row more than max-limit for this to work.
				if ($noofrows > self::$HTMLVIEW_MAX_ROWS) {
					// Performance Optimization: Output directly
					if ($directOutput) {
						echo '</tr></table><br><table width="100%" cellpading="0" cellspacing="0"><tr>';
						echo sprintf('<td colspan="%s" align="right"><span class="genHeaderGray">%s</span></td>',
								$y, getTranslatedString('Only')." ".self::$HTMLVIEW_MAX_ROWS .
								"+ " . getTranslatedString('records found') . ". " . getTranslatedString('Export to') . " <a href=\"javascript:;\" onclick=\"goToURL(CrearEnlace('ReportsAjax&file=CreateCSV',{$this->reportid}));\"><img style='vertical-align:text-top' src='themes/images/csv-file.png'></a> /" .
								" <a href=\"javascript:;\" onclick=\"goToURL(CrearEnlace('CreateXL',{$this->reportid}));\"><img style='vertical-align:text-top' src='themes/images/xls-file.jpg'></a>"
								);

					} else {
						$valtemplate .= '</tr></table><br><table width="100%" cellpading="0" cellspacing="0"><tr>';
						$valtemplate .= sprintf('<td colspan="%s" align="right"><span class="genHeaderGray">%s</span></td>',
								$y, getTranslatedString('Only')." ".self::$HTMLVIEW_MAX_ROWS .
								" " . getTranslatedString('records found') . ". " . getTranslatedString('Export to') . " <a href=\"javascript:;\" onclick=\"goToURL(CrearEnlace('ReportsAjax&file=CreateCSV',{$this->reportid}));\"><img style='vertical-align:text-top' src='themes/images/csv-file.png'></a> /" .
								" <a href=\"javascript:;\" onclick=\"goToURL(CrearEnlace('CreateXL',{$this->reportid}));\"><img style='vertical-align:text-top' src='themes/images/xls-file.jpg'></a>"
								);
					}
				}


				// Performance Optimization
				if($directOutput) {

					$totalDisplayString = $noofrows;
					if ($noofrows > self::$HTMLVIEW_MAX_ROWS) {
						$totalDisplayString = self::$HTMLVIEW_MAX_ROWS . "+";
					}

					echo "</tr></table>";
					echo "<script type='text/javascript' id='__reportrun_directoutput_recordcount_script'>
						if($('_reportrun_total')) $('_reportrun_total').innerHTML='$totalDisplayString';</script>";
				} else {

					$sHTML ='<table cellpadding="5" cellspacing="0" align="center" class="rptTable">
					<tr>'.
					$header
					.'<!-- BEGIN values -->
					<tr>'.
					$valtemplate
					.'</tr>
					</table>';
				}
				//<<<<<<<<construct HTML>>>>>>>>>>>>
				$return_data[] = $sHTML;
				$return_data[] = $noofrows;
				$return_data[] = $sSQL;
				return $return_data;
			}
		}elseif($outputformat == "PDF")
		{

			$sSQL = $this->sGetSQLforReport($this->reportid,$filtersql,$outputformat,false,$startLimit,$endLimit);
			$result = $adb->query($sSQL);
			if($is_admin==false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1)
                $picklistarray = $this->getAccessPickListValues();

			if($result)
			{
				$y=$adb->num_fields($result);
				$noofrows = $adb->num_rows($result);
				$custom_field_values = $adb->fetch_array($result);
				$column_definitions = $adb->getFieldsDefinition($result);

				do
				{
					$arraylists = Array();
					for ($i=0; $i<$y; $i++)
					{
						$fld = $adb->field_name($result, $i);
						$fld_type = $column_definitions[$i]->type;
						list($module, $fieldLabel) = explode('_', $fld->name, 2);
						$fieldInfo = getFieldByReportLabel($module, $fieldLabel);
						$fieldType = null;
						if(!empty($fieldInfo)) {
							$field = WebserviceField::fromArray($adb, $fieldInfo);
							$fieldType = $field->getFieldDataType();
						}
						if(!empty($fieldInfo)) {
							$translatedLabel = getTranslatedString($field->getFieldLabelKey(),
									$module);
						} else {
							$translatedLabel = getTranslatedString($fieldLabel, $module);
						}
						/*STRING TRANSLATION starts */
						$moduleLabel ='';
						if(in_array($module,$modules_selected))
							$moduleLabel = getTranslatedString($module,$module);

						if(empty($translatedLabel)) {
								$translatedLabel = getTranslatedString(str_replace('_', " ",
									$fld->name));
						}
						$headerLabel = $translatedLabel;
						if(!empty($this->secondarymodule)) {
							if($moduleLabel != '') {
								$headerLabel = $moduleLabel." ". $translatedLabel;
							}
						}
						// Check for role based pick list
						$temp_val= $fld->name;
						$fieldvalue = getReportFieldValue($this, $picklistarray, $fld,
								$custom_field_values, $i);

						if($fld->name == $this->primarymodule.'_LBL_ACTION' && $fieldvalue != '-') {
							$fieldvalue = "<a href='index.php?module={$this->primarymodule}&view=Detail&record={$fieldvalue}' target='_blank'>".getTranslatedString('LBL_VIEW_DETAILS')."</a>";
						}

						$arraylists[$headerLabel] = $fieldvalue;
					}
					$arr_val[] = $arraylists;
					set_time_limit($php_max_execution_time);
				}while($custom_field_values = $adb->fetch_array($result));

				return $arr_val;
			}
		}elseif($outputformat == "TOTALXLS")
		{
				$escapedchars = Array('_SUM','_AVG','_MIN','_MAX');
				$totalpdf=array();
				$sSQL = $this->sGetSQLforReport($this->reportid,$filtersql,"COLUMNSTOTOTAL");
				if(isset($this->totallist))
				{
						if($sSQL != "")
						{
								$result = $adb->query($sSQL);
								$y=$adb->num_fields($result);
								$custom_field_values = $adb->fetch_array($result);

								foreach($this->totallist as $key=>$value)
								{
									$fieldlist = explode(":",$key);
									$mod_query = $adb->pquery("SELECT distinct(tabid) as tabid, uitype as uitype from vtiger_field where tablename = ? and columnname=?",array($fieldlist[1],$fieldlist[2]));
									if($adb->num_rows($mod_query)>0){
											$module_name = getTabModuleName($adb->query_result($mod_query,0,'tabid'));
											$fieldlabel = trim(str_replace($escapedchars," ",$fieldlist[3]));
											$fieldlabel = str_replace("_", " ", $fieldlabel);
											if($module_name){
												$field = getTranslatedString($module_name,$module_name)." ".getTranslatedString($fieldlabel,$module_name);
											} else {
												$field = getTranslatedString($fieldlabel);
											}
									}
									// Since there are duplicate entries for this table
									if($fieldlist[1] == 'vtiger_inventoryproductrel') {
										$module_name = $this->primarymodule;
									}
									$uitype_arr[str_replace($escapedchars," ",$module_name."_".$fieldlist[3])] = $adb->query_result($mod_query,0,"uitype");
									$totclmnflds[str_replace($escapedchars," ",$module_name."_".$fieldlist[3])] = $field;
								}
								for($i =0;$i<$y;$i++)
								{
										$fld = $adb->field_name($result, $i);
										$keyhdr[$fld->name] = $custom_field_values[$i];
								}

								$rowcount=0;
								foreach($totclmnflds as $key=>$value)
								{
										$col_header = trim(str_replace($modules," ",$value));
										$fld_name_1 = $this->primarymodule . "_" . trim($value);
										$fld_name_2 = $this->secondarymodule . "_" . trim($value);
										if($uitype_arr[$key] == 71 || $uitype_arr[$key] == 72 ||
											in_array($fld_name_1,$this->append_currency_symbol_to_value) || in_array($fld_name_2,$this->append_currency_symbol_to_value)) {
												$col_header .= " (".$app_strings['LBL_IN']." ".$current_user->currency_symbol.")";
												$convert_price = true;
										} else{
												$convert_price = false;
										}
										$value = trim($key);
										$arraykey = $value.'_SUM';
										if(isset($keyhdr[$arraykey]))
										{
											if($convert_price)
												$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
											else
												$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
												$totalpdf[$rowcount][$arraykey] = $conv_value;
										}else
										{
												$totalpdf[$rowcount][$arraykey] = '';
										}

										$arraykey = $value.'_AVG';
										if(isset($keyhdr[$arraykey]))
										{
											if($convert_price)
												$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
											else
												$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
											$totalpdf[$rowcount][$arraykey] = $conv_value;
										}else
										{
												$totalpdf[$rowcount][$arraykey] = '';
										}

										$arraykey = $value.'_MIN';
										if(isset($keyhdr[$arraykey]))
										{
											if($convert_price)
												$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
											else
												$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
											$totalpdf[$rowcount][$arraykey] = $conv_value;
										}else
										{
												$totalpdf[$rowcount][$arraykey] = '';
										}

										$arraykey = $value.'_MAX';
										if(isset($keyhdr[$arraykey]))
										{
											if($convert_price)
												$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
											else
												$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
											$totalpdf[$rowcount][$arraykey] = $conv_value;
										}else
										{
												$totalpdf[$rowcount][$arraykey] = '';
										}
										$rowcount++;
								}
						}
				}
				return $totalpdf;
		}elseif($outputformat == "TOTALHTML")
		{
			$escapedchars = Array('_SUM','_AVG','_MIN','_MAX');
			$sSQL = $this->sGetSQLforReport($this->reportid,$filtersql,"COLUMNSTOTOTAL");

			static $modulename_cache = array();

			if(isset($this->totallist))
			{
				if($sSQL != "")
				{
					$result = $adb->query($sSQL);
					$y=$adb->num_fields($result);
					$custom_field_values = $adb->fetch_array($result);
					$coltotalhtml .= "<table align='center' width='60%' cellpadding='3' cellspacing='0' border='0' class='rptTable'><tr><td class='rptCellLabel'>".$mod_strings[Totals]."</td><td class='rptCellLabel'>".$mod_strings[SUM]."</td><td class='rptCellLabel'>".$mod_strings[AVG]."</td><td class='rptCellLabel'>".$mod_strings[MIN]."</td><td class='rptCellLabel'>".$mod_strings[MAX]."</td></tr>";

					// Performation Optimization: If Direct output is desired
					if($directOutput) {
						echo $coltotalhtml;
						$coltotalhtml = '';
					}
					// END

					foreach($this->totallist as $key=>$value)
					{
						$fieldlist = explode(":",$key);

						$module_name = NULL;
						$cachekey = $fieldlist[1] . ":" . $fieldlist[2];
						if (!isset($modulename_cache[$cachekey])) {
							$mod_query = $adb->pquery("SELECT distinct(tabid) as tabid, uitype as uitype from vtiger_field where tablename = ? and columnname=?",array($fieldlist[1],$fieldlist[2]));
							if($adb->num_rows($mod_query)>0){
								$module_name = getTabModuleName($adb->query_result($mod_query,0,'tabid'));
								$modulename_cache[$cachekey] = $module_name;
							}
						} else {
							$module_name = $modulename_cache[$cachekey];
						}
						if ($module_name) {
							$fieldlabel = trim(str_replace($escapedchars," ",$fieldlist[3]));
							$fieldlabel = str_replace("_", " ", $fieldlabel);
							$field = getTranslatedString($module_name, $module_name)." ".getTranslatedString($fieldlabel,$module_name);
						} else {
							$field = getTranslatedString($fieldlabel);
						}

						$uitype_arr[str_replace($escapedchars," ",$module_name."_".$fieldlist[3])] = $adb->query_result($mod_query,0,"uitype");
						$totclmnflds[str_replace($escapedchars," ",$module_name."_".$fieldlist[3])] = $field;
					}
					for($i =0;$i<$y;$i++)
					{
						$fld = $adb->field_name($result, $i);
						$keyhdr[$fld->name] = $custom_field_values[$i];
					}

					foreach($totclmnflds as $key=>$value)
					{
						$coltotalhtml .= '<tr class="rptGrpHead" valign=top>';
						$col_header = trim(str_replace($modules," ",$value));
						$fld_name_1 = $this->primarymodule . "_" . trim($value);
						$fld_name_2 = $this->secondarymodule . "_" . trim($value);
						if($uitype_arr[$key]==71 || $uitype_arr[$key] == 72 ||
											in_array($fld_name_1,$this->append_currency_symbol_to_value) || in_array($fld_name_2,$this->append_currency_symbol_to_value)) {
							$col_header .= " (".$app_strings['LBL_IN']." ".$current_user->currency_symbol.")";
							$convert_price = true;
						} else{
							$convert_price = false;
						}
						$coltotalhtml .= '<td class="rptData">'. $col_header .'</td>';
						$value = trim($key);
						$arraykey = $value.'_SUM';
						if(isset($keyhdr[$arraykey]))
						{
							if($convert_price)
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
							else
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
							$coltotalhtml .= '<td class="rptTotal">'.$conv_value.'</td>';
						}else
						{
							$coltotalhtml .= '<td class="rptTotal">&nbsp;</td>';
						}

						$arraykey = $value.'_AVG';
						if(isset($keyhdr[$arraykey]))
						{
							if($convert_price)
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
							else
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
							$coltotalhtml .= '<td class="rptTotal">'.$conv_value.'</td>';
						}else
						{
							$coltotalhtml .= '<td class="rptTotal">&nbsp;</td>';
						}

						$arraykey = $value.'_MIN';
						if(isset($keyhdr[$arraykey]))
						{
							if($convert_price)
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
							else
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
							$coltotalhtml .= '<td class="rptTotal">'.$conv_value.'</td>';
						}else
						{
							$coltotalhtml .= '<td class="rptTotal">&nbsp;</td>';
						}

						$arraykey = $value.'_MAX';
						if(isset($keyhdr[$arraykey]))
						{
							if($convert_price)
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
							else
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
							$coltotalhtml .= '<td class="rptTotal">'.$conv_value.'</td>';
						}else
						{
							$coltotalhtml .= '<td class="rptTotal">&nbsp;</td>';
						}

						$coltotalhtml .= '<tr>';

						// Performation Optimization: If Direct output is desired
						if($directOutput) {
							echo $coltotalhtml;
							$coltotalhtml = '';
						}
						// END
					}

					$coltotalhtml .= "</table>";

					// Performation Optimization: If Direct output is desired
					if($directOutput) {
						echo $coltotalhtml;
						$coltotalhtml = '';
					}
					// END
				}
			}
			return $coltotalhtml;
		}elseif($outputformat == "PRINT")
		{
			$sSQL = $this->sGetSQLforReport($this->reportid,$filtersql);
			$result = $adb->query($sSQL);
			if($is_admin==false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1)
			$picklistarray = $this->getAccessPickListValues();

			if($result)
			{
				$y=$adb->num_fields($result);
				$arrayHeaders = Array();
				for ($x=0; $x<$y; $x++)
				{
					$fld = $adb->field_name($result, $x);
					if(in_array($this->getLstringforReportHeaders($fld->name), $arrayHeaders))
					{
						$headerLabel = str_replace("_"," ",$fld->name);
						$arrayHeaders[] = $headerLabel;
					}
					else
					{
						$headerLabel = str_replace($modules," ",$this->getLstringforReportHeaders($fld->name));
						$arrayHeaders[] = $headerLabel;
					}
					/*STRING TRANSLATION starts */
					$mod_name = split(' ',$headerLabel,2);
					$moduleLabel ='';
					if(in_array($mod_name[0],$modules_selected)){
						$moduleLabel = getTranslatedString($mod_name[0],$mod_name[0]);
					}

					if(!empty($this->secondarymodule)){
						if($moduleLabel!=''){
							$headerLabel_tmp = $moduleLabel." ".getTranslatedString($mod_name[1],$mod_name[0]);
						} else {
							$headerLabel_tmp = getTranslatedString($mod_name[0]." ".$mod_name[1]);
						}
					} else {
						if($moduleLabel!=''){
							$headerLabel_tmp = getTranslatedString($mod_name[1],$mod_name[0]);
						} else {
							$headerLabel_tmp = getTranslatedString($mod_name[0]." ".$mod_name[1]);
						}
					}
					if($headerLabel == $headerLabel_tmp) $headerLabel = getTranslatedString($headerLabel_tmp);
					else $headerLabel = $headerLabel_tmp;
					/*STRING TRANSLATION ends */
					$header .= "<th>".$headerLabel."</th>";
				}
				$noofrows = $adb->num_rows($result);
				$custom_field_values = $adb->fetch_array($result);
				$groupslist = $this->getGroupingList($this->reportid);

				$column_definitions = $adb->getFieldsDefinition($result);

				do
				{
					$arraylists = Array();
					if(count($groupslist) == 1)
					{
						$newvalue = $custom_field_values[0];
					}elseif(count($groupslist) == 2)
					{
						$newvalue = $custom_field_values[0];
						$snewvalue = $custom_field_values[1];
					}elseif(count($groupslist) == 3)
					{
						$newvalue = $custom_field_values[0];
                                                $snewvalue = $custom_field_values[1];
						$tnewvalue = $custom_field_values[2];
					}

					if($newvalue == "") $newvalue = "-";

					if($snewvalue == "") $snewvalue = "-";

					if($tnewvalue == "") $tnewvalue = "-";

					$valtemplate .= "<tr>";

					for ($i=0; $i<$y; $i++)
					{
						$fld = $adb->field_name($result, $i);
						$fld_type = $column_definitions[$i]->type;
						$fieldvalue = getReportFieldValue($this, $picklistarray, $fld,
								$custom_field_values, $i);
						if(($lastvalue == $fieldvalue) && $this->reporttype == "summary")
						{
							if($this->reporttype == "summary")
							{
								$valtemplate .= "<td style='border-top:1px dotted #FFFFFF;'>&nbsp;</td>";
							}else
							{
								$valtemplate .= "<td>".$fieldvalue."</td>";
							}
						}else if(($secondvalue == $fieldvalue) && $this->reporttype == "summary")
						{
							if($lastvalue == $newvalue)
							{
								$valtemplate .= "<td style='border-top:1px dotted #FFFFFF;'>&nbsp;</td>";
							}else
							{
								$valtemplate .= "<td>".$fieldvalue."</td>";
							}
						}
						else if(($thirdvalue == $fieldvalue) && $this->reporttype == "summary")
						{
							if($secondvalue == $snewvalue)
							{
								$valtemplate .= "<td style='border-top:1px dotted #FFFFFF;'>&nbsp;</td>";
							}else
							{
								$valtemplate .= "<td>".$fieldvalue."</td>";
							}
						}
						else
						{
							if($this->reporttype == "tabular")
							{
								$valtemplate .= "<td>".$fieldvalue."</td>";
							}else
							{
								$valtemplate .= "<td>".$fieldvalue."</td>";
							}
						}
					  }
					 $valtemplate .= "</tr>";
					 $lastvalue = $newvalue;
					 $secondvalue = $snewvalue;
					 $thirdvalue = $tnewvalue;
					 $arr_val[] = $arraylists;
					 set_time_limit($php_max_execution_time);
				}while($custom_field_values = $adb->fetch_array($result));

				$sHTML = '<tr>'.$header.'</tr>'.$valtemplate;
				$return_data[] = $sHTML;
				$return_data[] = $noofrows;
				return $return_data;
			}
		}elseif($outputformat == "PRINT_TOTAL")
		{
			$escapedchars = Array('_SUM','_AVG','_MIN','_MAX');
			$sSQL = $this->sGetSQLforReport($this->reportid,$filtersql,"COLUMNSTOTOTAL");
			if(isset($this->totallist))
			{
				if($sSQL != "")
				{
					$result = $adb->query($sSQL);
					$y=$adb->num_fields($result);
					$custom_field_values = $adb->fetch_array($result);

					$coltotalhtml .= "<br /><table align='center' width='60%' cellpadding='3' cellspacing='0' border='1' class='printReport'><tr><td class='rptCellLabel'>".$mod_strings['Totals']."</td><td><b>".$mod_strings['SUM']."</b></td><td><b>".$mod_strings['AVG']."</b></td><td><b>".$mod_strings['MIN']."</b></td><td><b>".$mod_strings['MAX']."</b></td></tr>";

					// Performation Optimization: If Direct output is desired
					if($directOutput) {
						echo $coltotalhtml;
						$coltotalhtml = '';
					}
					// END

					foreach($this->totallist as $key=>$value)
					{
						$fieldlist = explode(":",$key);
						$mod_query = $adb->pquery("SELECT distinct(tabid) as tabid, uitype as uitype from vtiger_field where tablename = ? and columnname=?",array($fieldlist[1],$fieldlist[2]));
						if($adb->num_rows($mod_query)>0){
							$module_name = getTabModuleName($adb->query_result($mod_query,0,'tabid'));
							$fieldlabel = trim(str_replace($escapedchars," ",$fieldlist[3]));
							$fieldlabel = str_replace("_", " ", $fieldlabel);
							if($module_name){
								$field = getTranslatedString($module_name, $module_name)." ".getTranslatedString($fieldlabel,$module_name);
							} else {
								$field = getTranslatedString($fieldlabel);
							}
						}
						$uitype_arr[str_replace($escapedchars," ",$module_name."_".$fieldlist[3])] = $adb->query_result($mod_query,0,"uitype");
						$totclmnflds[str_replace($escapedchars," ",$module_name."_".$fieldlist[3])] = $field;
					}

					for($i =0;$i<$y;$i++)
					{
						$fld = $adb->field_name($result, $i);
						$keyhdr[$fld->name] = $custom_field_values[$i];

					}
					foreach($totclmnflds as $key=>$value)
					{
						$coltotalhtml .= '<tr class="rptGrpHead">';
						$col_header = getTranslatedString(trim(str_replace($modules," ",$value)));
						$fld_name_1 = $this->primarymodule . "_" . trim($value);
						$fld_name_2 = $this->secondarymodule . "_" . trim($value);
						if($uitype_arr[$key]==71 || $uitype_arr[$key] == 72 ||
										in_array($fld_name_1,$this->append_currency_symbol_to_value) || in_array($fld_name_2,$this->append_currency_symbol_to_value)) {
							$col_header .= " (".$app_strings['LBL_IN']." ".$current_user->currency_symbol.")";
							$convert_price = true;
						} else{
							$convert_price = false;
						}
						$coltotalhtml .= '<td class="rptData">'. $col_header .'</td>';
						$value = trim($key);
						$arraykey = $value.'_SUM';
						if(isset($keyhdr[$arraykey]))
						{
							if($convert_price)
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
							else
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
							$coltotalhtml .= "<td class='rptTotal'>".$conv_value.'</td>';
						}else
						{
							$coltotalhtml .= "<td class='rptTotal'>&nbsp;</td>";
						}

						$arraykey = $value.'_AVG';
						if(isset($keyhdr[$arraykey]))
						{
							if($convert_price)
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
							else
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
							$coltotalhtml .= "<td class='rptTotal'>".$conv_value.'</td>';
						}else
						{
							$coltotalhtml .= "<td class='rptTotal'>&nbsp;</td>";
						}

						$arraykey = $value.'_MIN';
						if(isset($keyhdr[$arraykey]))
						{
							if($convert_price)
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
							else
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
							$coltotalhtml .= "<td class='rptTotal'>".$conv_value.'</td>';
						}else
						{
							$coltotalhtml .= "<td class='rptTotal'>&nbsp;</td>";
						}

						$arraykey = $value.'_MAX';
						if(isset($keyhdr[$arraykey]))
						{
							if($convert_price)
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey]);
							else
								$conv_value = CurrencyField::convertToUserFormat ($keyhdr[$arraykey], null, true);
							$coltotalhtml .= "<td class='rptTotal'>".$conv_value.'</td>';
						}else
						{
							$coltotalhtml .= "<td class='rptTotal'>&nbsp;</td>";
						}

						$coltotalhtml .= '</tr>';

						// Performation Optimization: If Direct output is desired
						if($directOutput) {
							echo $coltotalhtml;
							$coltotalhtml = '';
						}
						// END
					}

					$coltotalhtml .= "</table>";
					// Performation Optimization: If Direct output is desired
					if($directOutput) {
						echo $coltotalhtml;
						$coltotalhtml = '';
					}
					// END
				}
			}
			return $coltotalhtml;
		}
	}

	//<<<<<<<new>>>>>>>>>>
	function getColumnsTotal($reportid)
	{
		// Have we initialized it already?
		if($this->_columnstotallist !== false) {
			return $this->_columnstotallist;
		}

		global $adb;
		global $modules;
		global $log, $current_user;

		static $modulename_cache = array();

		$query = "select * from vtiger_reportmodules where reportmodulesid =?";
		$res = $adb->pquery($query , array($reportid));
		$modrow = $adb->fetch_array($res);
		$premod = $modrow["primarymodule"];
		$secmod = $modrow["secondarymodules"];
		$coltotalsql = "select vtiger_reportsummary.* from vtiger_report";
		$coltotalsql .= " inner join vtiger_reportsummary on vtiger_report.reportid = vtiger_reportsummary.reportsummaryid";
		$coltotalsql .= " where vtiger_report.reportid =?";

		$result = $adb->pquery($coltotalsql, array($reportid));

		while($coltotalrow = $adb->fetch_array($result))
		{
			$fieldcolname = $coltotalrow["columnname"];
			if($fieldcolname != "none")
			{
				$fieldlist = explode(":",$fieldcolname);
				$field_tablename = $fieldlist[1];
				$field_columnname = $fieldlist[2];

				$cachekey = $field_tablename . ":" . $field_columnname;
				if (!isset($modulename_cache[$cachekey])) {
					$mod_query = $adb->pquery("SELECT distinct(tabid) as tabid from vtiger_field where tablename = ? and columnname=?",array($fieldlist[1],$fieldlist[2]));
					if($adb->num_rows($mod_query)>0){
						$module_name = getTabName($adb->query_result($mod_query,0,'tabid'));
						$modulename_cache[$cachekey] = $module_name;
					}
				} else {
					$module_name = $modulename_cache[$cachekey];
				}

				$fieldlabel = trim($fieldlist[3]);
				if($field_tablename == 'vtiger_inventoryproductrel') {
					$field_columnalias = $premod."_".$fieldlist[3];
				} else {
					if($module_name){
						$field_columnalias = $module_name."_".$fieldlist[3];
					} else {
						$field_columnalias = $module_name."_".$fieldlist[3];
					}
				}

				//$field_columnalias = $fieldlist[3];
				$field_permitted = false;
				if(CheckColumnPermission($field_tablename,$field_columnname,$premod) != "false"){
					$field_permitted = true;
				} else {
					$mod = split(":",$secmod);
					foreach($mod as $key){
						if(CheckColumnPermission($field_tablename,$field_columnname,$key) != "false"){
							$field_permitted=true;
						}
					}
				}

				//Calculation fields of "Events" module should show in Calendar related report
				$secondaryModules = split(":", $secmod);
				if ($field_permitted === false && ($premod === 'Calendar' || in_array('Calendar', $secondaryModules)) && CheckColumnPermission($field_tablename, $field_columnname, "Events") != "false") {
					$field_permitted = true;
				}

				if($field_permitted == true)
				{
					$field = $field_tablename.".".$field_columnname;
					if($field_tablename == 'vtiger_products' && $field_columnname == 'unit_price') {
						// Query needs to be rebuild to get the value in user preferred currency. [innerProduct and actual_unit_price are table and column alias.]
						$field =  " innerProduct.actual_unit_price";
                        $this->queryPlanner->addTable("innerProduct");
					}
					if($field_tablename == 'vtiger_service' && $field_columnname == 'unit_price') {
						// Query needs to be rebuild to get the value in user preferred currency. [innerProduct and actual_unit_price are table and column alias.]
						$field =  " innerService.actual_unit_price";
						$this->queryPlanner->addTable("innerService");

					}
					if(($field_tablename == 'vtiger_invoice' || $field_tablename == 'vtiger_quotes' || $field_tablename == 'vtiger_purchaseorder' || $field_tablename == 'vtiger_salesorder')
							&& ($field_columnname == 'total' || $field_columnname == 'subtotal' || $field_columnname == 'discount_amount' || $field_columnname == 's_h_amount'
									|| $field_columnname == 'paid' || $field_columnname == 'balance' || $field_columnname == 'received')) {
						$field =  " $field_tablename.$field_columnname/$field_tablename.conversion_rate ";
					}

					if($field_tablename == 'vtiger_inventoryproductrel') {
						// Check added so that query planner can prepare query properly for inventory modules
						$this->lineItemFieldsInCalculation = true;
						$field = $field_tablename.$premod.'.'.$field_columnname;
						$itemTableName = 'vtiger_inventoryproductrel'.$premod;
						$this->queryPlanner->addTable($itemTableName);
						if($field_columnname == 'listprice') {
							$primaryModuleInstance = CRMEntity::getInstance($premod);
							$field = $field.'/'.$primaryModuleInstance->table_name.'.conversion_rate';
						} else if($field_columnname == 'discount_amount') {
							$field = ' CASE WHEN '.$itemTableName.'.discount_amount is not null THEN '.$itemTableName.'.discount_amount/'.$primaryModuleInstance->table_name.'.conversion_rate '.
								'WHEN '.$itemTableName.'.discount_percent IS NOT NULL THEN ('.$itemTableName.'.listprice*'.$itemTableName.'.quantity*'.$itemTableName.'.discount_percent/100/'.$primaryModuleInstance->table_name.'.conversion_rate) ELSE 0 END ';
						}
					}

					if($fieldlist[4] == 2)
					{
						$stdfilterlist[$fieldcolname] = "sum($field) '".$field_columnalias."'";
					}
					if($fieldlist[4] == 3)
					{
						//Fixed average calculation issue due to NULL values ie., when we use avg() function, NULL values will be ignored.to avoid this we use (sum/count) to find average.
						//$stdfilterlist[$fieldcolname] = "avg(".$fieldlist[1].".".$fieldlist[2].") '".$fieldlist[3]."'";
						$stdfilterlist[$fieldcolname] = "(sum($field)/count(*)) '".$field_columnalias."'";
					}
					if($fieldlist[4] == 4)
					{
						$stdfilterlist[$fieldcolname] = "min($field) '".$field_columnalias."'";
					}
					if($fieldlist[4] == 5)
					{
						$stdfilterlist[$fieldcolname] = "max($field) '".$field_columnalias."'";
					}

					$this->queryPlanner->addTable($field_tablename);
				}
			}
		}
		// Save the information
		$this->_columnstotallist = $stdfilterlist;

		$log->info("ReportRun :: Successfully returned getColumnsTotal".$reportid);
		return $stdfilterlist;
	}
	//<<<<<<new>>>>>>>>>


	/** function to get query for the columns to total for the given reportid
	 *  @ param $reportid : Type integer
	 *  This returns columnstoTotal query for the reportid
	 */

	function getColumnsToTotalColumns($reportid)
	{
		global $adb;
		global $modules;
		global $log;

		$sreportstdfiltersql = "select vtiger_reportsummary.* from vtiger_report";
		$sreportstdfiltersql .= " inner join vtiger_reportsummary on vtiger_report.reportid = vtiger_reportsummary.reportsummaryid";
		$sreportstdfiltersql .= " where vtiger_report.reportid =?";

		$result = $adb->pquery($sreportstdfiltersql, array($reportid));
		$noofrows = $adb->num_rows($result);

		for($i=0; $i<$noofrows; $i++)
		{
			$fieldcolname = $adb->query_result($result,$i,"columnname");

			if($fieldcolname != "none")
			{
				$fieldlist = explode(":",$fieldcolname);
				if($fieldlist[4] == 2)
				{
					$sSQLList[] = "sum(".$fieldlist[1].".".$fieldlist[2].") ".$fieldlist[3];
				}
				if($fieldlist[4] == 3)
				{
					$sSQLList[] = "avg(".$fieldlist[1].".".$fieldlist[2].") ".$fieldlist[3];
				}
				if($fieldlist[4] == 4)
				{
					$sSQLList[] = "min(".$fieldlist[1].".".$fieldlist[2].") ".$fieldlist[3];
				}
				if($fieldlist[4] == 5)
				{
					$sSQLList[] = "max(".$fieldlist[1].".".$fieldlist[2].") ".$fieldlist[3];
				}
			}
		}
		if(isset($sSQLList))
		{
			$sSQL = implode(",",$sSQLList);
		}
		$log->info("ReportRun :: Successfully returned getColumnsToTotalColumns".$reportid);
		return $sSQL;
	}
	/** Function to convert the Report Header Names into i18n
	 *  @param $fldname: Type Varchar
	 *  Returns Language Converted Header Strings
	 **/
	function getLstringforReportHeaders($fldname)
	{
		global $modules,$current_language,$current_user,$app_strings;
		$rep_header = ltrim($fldname);
		$rep_header = decode_html($rep_header);
		$labelInfo = explode('_', $rep_header);
		$rep_module = $labelInfo[0];
		if(is_array($this->labelMapping) && !empty($this->labelMapping[$rep_header])) {
			$rep_header = $this->labelMapping[$rep_header];
		} else {
			if($rep_module == 'LBL') {
				$rep_module = '';
			}
			array_shift($labelInfo);
			$fieldLabel = decode_html(implode("_",$labelInfo));
			$rep_header_temp = preg_replace("/\s+/","_",$fieldLabel);
			$rep_header = "$rep_module $fieldLabel";
		}
		$curr_symb = "";
		$fieldLabel = ltrim(str_replace($rep_module, '', $rep_header), '_');
		$fieldInfo = getFieldByReportLabel($rep_module, $fieldLabel);
		if($fieldInfo['uitype'] == '71') {
        	$curr_symb = " (".$app_strings['LBL_IN']." ".$current_user->currency_symbol.")";
		}
        $rep_header .=$curr_symb;

		return $rep_header;
	}

	/** Function to get picklist value array based on profile
	 *          *  returns permitted fields in array format
	 **/


	function getAccessPickListValues()
	{
		global $adb;
		global $current_user;
		$id = array(getTabid($this->primarymodule));
		if($this->secondarymodule != '')
			array_push($id,  getTabid($this->secondarymodule));

		$query = 'select fieldname,columnname,fieldid,fieldlabel,tabid,uitype from vtiger_field where tabid in('. generateQuestionMarks($id) .') and uitype in (15,33,55)'; //and columnname in (?)';
		$result = $adb->pquery($query, $id);//,$select_column));
		$roleid=$current_user->roleid;
		$subrole = getRoleSubordinates($roleid);
		if(count($subrole)> 0)
		{
			$roleids = $subrole;
			array_push($roleids, $roleid);
		}
		else
		{
			$roleids = $roleid;
		}

		$temp_status = Array();
		for($i=0;$i < $adb->num_rows($result);$i++)
		{
			$fieldname = $adb->query_result($result,$i,"fieldname");
			$fieldlabel = $adb->query_result($result,$i,"fieldlabel");
			$tabid = $adb->query_result($result,$i,"tabid");
			$uitype = $adb->query_result($result,$i,"uitype");

			$fieldlabel1 = str_replace(" ","_",$fieldlabel);
			$keyvalue = getTabModuleName($tabid)."_".$fieldlabel1;
			$fieldvalues = Array();
			if (count($roleids) > 1) {
				$mulsel="select distinct $fieldname from vtiger_$fieldname inner join vtiger_role2picklist on vtiger_role2picklist.picklistvalueid = vtiger_$fieldname.picklist_valueid where roleid in (\"". implode($roleids,"\",\"") ."\") and picklistid in (select picklistid from vtiger_$fieldname)"; // order by sortid asc - not requried
			} else {
				$mulsel="select distinct $fieldname from vtiger_$fieldname inner join vtiger_role2picklist on vtiger_role2picklist.picklistvalueid = vtiger_$fieldname.picklist_valueid where roleid ='".$roleid."' and picklistid in (select picklistid from vtiger_$fieldname)"; // order by sortid asc - not requried
			}
			if($fieldname != 'firstname')
				$mulselresult = $adb->query($mulsel);
			for($j=0;$j < $adb->num_rows($mulselresult);$j++)
			{
				$fldvalue = $adb->query_result($mulselresult,$j,$fieldname);
				if(in_array($fldvalue,$fieldvalues)) continue;
				$fieldvalues[] = $fldvalue;
			}
			$field_count = count($fieldvalues);
			if( $uitype == 15 && $field_count > 0 && ($fieldname == 'taskstatus' || $fieldname == 'eventstatus'))
			{
				$temp_count =count($temp_status[$keyvalue]);
				if($temp_count > 0)
				{
					for($t=0;$t < $field_count;$t++)
					{
						$temp_status[$keyvalue][($temp_count+$t)] = $fieldvalues[$t];
					}
					$fieldvalues = $temp_status[$keyvalue];
				}
				else
					$temp_status[$keyvalue] = $fieldvalues;
			}

			if($uitype == 33)
				$fieldlists[1][$keyvalue] = $fieldvalues;
			else if($uitype == 55 && $fieldname == 'salutationtype')
				$fieldlists[$keyvalue] = $fieldvalues;
	        else if($uitype == 15)
		        $fieldlists[$keyvalue] = $fieldvalues;
		}
		return $fieldlists;
	}

	function getReportPDF($filterlist=false) {
		require_once 'libraries/tcpdf/tcpdf.php';

		$arr_val = $this->GenerateReport("PDF",$filterlist);

		if(isset($arr_val)) {
			foreach($arr_val as $wkey=>$warray_value) {
				foreach($warray_value as $whd=>$wvalue) {
					if(strlen($wvalue) < strlen($whd)) {
						$w_inner_array[] = strlen($whd);
					} else {
						$w_inner_array[] = strlen($wvalue);
					}
				}
				$warr_val[] = $w_inner_array;
				unset($w_inner_array);
			}

			foreach($warr_val[0] as $fkey=>$fvalue) {
				foreach($warr_val as $wkey=>$wvalue) {
					$f_inner_array[] = $warr_val[$wkey][$fkey];
				}
				sort($f_inner_array,1);
				$farr_val[] = $f_inner_array;
				unset($f_inner_array);
			}

			foreach($farr_val as $skkey=>$skvalue) {
				if($skvalue[count($arr_val)-1] == 1) {
					$col_width[] = ($skvalue[count($arr_val)-1] * 50);
				} else {
					$col_width[] = ($skvalue[count($arr_val)-1] * 10) + 10 ;
				}
			}
			$count = 0;
			foreach($arr_val[0] as $key=>$value) {
				$headerHTML .= '<td width="'.$col_width[$count].'" bgcolor="#DDDDDD"><b>'.$this->getLstringforReportHeaders($key).'</b></td>';
				$count = $count + 1;
			}

			foreach($arr_val as $key=>$array_value) {
				$valueHTML = "";
				$count = 0;
				foreach($array_value as $hd=>$value) {
					$valueHTML .= '<td width="'.$col_width[$count].'">'.$value.'</td>';
					$count = $count + 1;
				}
				$dataHTML .= '<tr>'.$valueHTML.'</tr>';
			}

		}

		$totalpdf = $this->GenerateReport("PRINT_TOTAL",$filterlist);
		$html = '<table border="0.5"><tr>'.$headerHTML.'</tr>'.$dataHTML.'<tr><td>'.$totalpdf.'</td></tr>'.'</table>';
		$columnlength = array_sum($col_width);
		if($columnlength > 14400) {
			die("<br><br><center>".$app_strings['LBL_PDF']." <a href='javascript:window.history.back()'>".$app_strings['LBL_GO_BACK'].".</a></center>");
		}
		if($columnlength <= 420 ) {
			$pdf = new TCPDF('P','mm','A5',true);

		} elseif($columnlength >= 421 && $columnlength <= 1120) {
			$pdf = new TCPDF('L','mm','A3',true);

		}elseif($columnlength >=1121 && $columnlength <= 1600) {
			$pdf = new TCPDF('L','mm','A2',true);

		}elseif($columnlength >=1601 && $columnlength <= 2200) {
			$pdf = new TCPDF('L','mm','A1',true);
		}
		elseif($columnlength >=2201 && $columnlength <= 3370) {
			$pdf = new TCPDF('L','mm','A0',true);
		}
		elseif($columnlength >=3371 && $columnlength <= 4690) {
			$pdf = new TCPDF('L','mm','2A0',true);
		}
		elseif($columnlength >=4691 && $columnlength <= 6490) {
			$pdf = new TCPDF('L','mm','4A0',true);
		}
		else {
			$columnhight = count($arr_val)*15;
			$format = array($columnhight,$columnlength);
			$pdf = new TCPDF('L','mm',$format,true);
		}
		$pdf->SetMargins(10, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		$pdf->setLanguageArray($l);
		$pdf->AddPage();

		$pdf->SetFillColor(224,235,255);
		$pdf->SetTextColor(0);
		$pdf->SetFont('FreeSerif','B',14);
		$pdf->Cell(($pdf->columnlength*50),10,getTranslatedString($oReport->reportname),0,0,'C',0);
		//$pdf->writeHTML($oReport->reportname);
		$pdf->Ln();

		$pdf->SetFont('FreeSerif','',10);

		$pdf->writeHTML($html);

		return $pdf;
	}

	function writeReportToExcelFile($fileName, $filterlist='') {

		global $currentModule, $current_language;
		$mod_strings = return_module_language($current_language, $currentModule);

		require_once("libraries/PHPExcel/PHPExcel.php");

		$workbook = new PHPExcel();
		$worksheet = $workbook->setActiveSheetIndex(0);

		$arr_val = $this->GenerateReport("PDF",$filterlist);
		$totalxls = $this->GenerateReport("TOTALXLS",$filterlist);

		$header_styles = array(
			'fill' => array( 'type' => PHPExcel_Style_Fill::FILL_SOLID, 'color' => array('rgb'=>'E1E0F7') ),
			//'font' => array( 'bold' => true )
		);

		if(isset($arr_val)) {
			$count = 0;
			$rowcount = 1;
            //copy the first value details
            $arrayFirstRowValues = $arr_val[0];
			array_pop($arrayFirstRowValues);			// removed action link in details
			foreach($arrayFirstRowValues as $key=>$value) {
				$worksheet->setCellValueExplicitByColumnAndRow($count, $rowcount, $key, true);
				$worksheet->getStyleByColumnAndRow($count, $rowcount)->applyFromArray($header_styles);

				// NOTE Performance overhead: http://stackoverflow.com/questions/9965476/phpexcel-column-size-issues
				//$worksheet->getColumnDimensionByColumn($count)->setAutoSize(true);

				$count = $count + 1;
			}

			$rowcount++;
			foreach($arr_val as $key=>$array_value) {
				$count = 0;
				array_pop($array_value);	// removed action link in details
				foreach($array_value as $hdr=>$value) {
					if($hdr == 'ACTION') continue;
					$value = decode_html($value);
					// TODO Determine data-type based on field-type.
					// String type helps having numbers prefixed with 0 intact.
					$worksheet->setCellValueExplicitByColumnAndRow($count, $rowcount, $value, PHPExcel_Cell_DataType::TYPE_STRING);
					$count = $count + 1;
				}
				$rowcount++;
			}

			// Summary Total
			$rowcount++;
			$count=0;
			if(is_array($totalxls[0])) {
				foreach($totalxls[0] as $key=>$value) {
					$chdr=substr($key,-3,3);
					$translated_str = in_array($chdr ,array_keys($mod_strings))?$mod_strings[$chdr]:$key;
					$worksheet->setCellValueExplicitByColumnAndRow($count, $rowcount, $translated_str);

					$worksheet->getStyleByColumnAndRow($count, $rowcount)->applyFromArray($header_styles);

					$count = $count + 1;
				}
			}

			$rowcount++;
			foreach($totalxls as $key=>$array_value) {
				$count = 0;
				foreach($array_value as $hdr=>$value) {
					$value = decode_html($value);
					$worksheet->setCellValueExplicitByColumnAndRow($count, $key+$rowcount, $value);
					$count = $count + 1;
				}
			}
		}

		$workbookWriter = PHPExcel_IOFactory::createWriter($workbook, 'Excel5');
		$workbookWriter->save($fileName);
	}

	function writeReportToCSVFile($fileName, $filterlist='') {

		global $currentModule, $current_language;
		$mod_strings = return_module_language($current_language, $currentModule);

		$arr_val = $this->GenerateReport("PDF",$filterlist);

		$fp = fopen($fileName, 'w+');

		if(isset($arr_val)) {
			$csv_values = array();
			// Header
			$csv_values = array_keys($arr_val[0]);
			array_pop($csv_values);			//removed header in csv file
			fputcsv($fp, $csv_values);
			foreach($arr_val as $key=>$array_value) {
				array_pop($array_value);	//removed action link
				$csv_values = array_map('decode_html', array_values($array_value));
				fputcsv($fp, $csv_values);
			}
		}
		fclose($fp);
	}

    function getGroupByTimeList($reportId){
        global $adb;
        $groupByTimeQuery = "SELECT * FROM vtiger_reportgroupbycolumn WHERE reportid=?";
        $groupByTimeRes = $adb->pquery($groupByTimeQuery,array($reportId));
        $num_rows = $adb->num_rows($groupByTimeRes);
        for($i=0;$i<$num_rows;$i++){
            $sortColName = $adb->query_result($groupByTimeRes, $i,'sortcolname');
            list($tablename,$colname,$module_field,$fieldname,$single) = split(':',$sortColName);
            $groupField = $module_field;
            $groupCriteria = $adb->query_result($groupByTimeRes, $i,'dategroupbycriteria');
            if(in_array($groupCriteria,array_keys($this->groupByTimeParent))){
                $parentCriteria = $this->groupByTimeParent[$groupCriteria];
                foreach($parentCriteria as $criteria){
                  $groupByCondition[]=$this->GetTimeCriteriaCondition($criteria, $groupField);
                }
            }
            $groupByCondition[] = $this->GetTimeCriteriaCondition($groupCriteria, $groupField);
			$this->queryPlanner->addTable($tablename);
        }
        return $groupByCondition;
    }

    function GetTimeCriteriaCondition($criteria,$dateField){
        $condition = "";
        if(strtolower($criteria)=='year'){
            $condition = "DATE_FORMAT($dateField, '%Y' )";
        }
        else if (strtolower($criteria)=='month'){
            $condition = "CEIL(DATE_FORMAT($dateField,'%m')%13)";
        }
        else if(strtolower($criteria)=='quarter'){
            $condition = "CEIL(DATE_FORMAT($dateField,'%m')/3)";
        }
        return $condition;
    }

    function GetFirstSortByField($reportid)
    {
        global $adb;
        $groupByField ="";
        $sortFieldQuery = "SELECT * FROM vtiger_reportsortcol
                            LEFT JOIN vtiger_reportgroupbycolumn ON (vtiger_reportsortcol.sortcolid = vtiger_reportgroupbycolumn.sortid and vtiger_reportsortcol.reportid = vtiger_reportgroupbycolumn.reportid)
                            WHERE columnname!='none' and vtiger_reportsortcol.reportid=? ORDER By sortcolid";
        $sortFieldResult= $adb->pquery($sortFieldQuery,array($reportid));
		$inventoryModules = getInventoryModules();
        if($adb->num_rows($sortFieldResult)>0){
            $fieldcolname = $adb->query_result($sortFieldResult,0,'columnname');
            list($tablename,$colname,$module_field,$fieldname,$typeOfData) = explode(":",$fieldcolname);
			list($modulename,$fieldlabel) = explode('_', $module_field, 2);
            $groupByField = $module_field;
            if($typeOfData == "D"){
                $groupCriteria = $adb->query_result($sortFieldResult,0,'dategroupbycriteria');
                if(strtolower($groupCriteria)!='none'){
                    if(in_array($groupCriteria,array_keys($this->groupByTimeParent))){
                        $parentCriteria = $this->groupByTimeParent[$groupCriteria];
                        foreach($parentCriteria as $criteria){
                          $groupByCondition[]=$this->GetTimeCriteriaCondition($criteria, $groupByField);
                        }
                    }
                    $groupByCondition[] = $this->GetTimeCriteriaCondition($groupCriteria, $groupByField);
                    $groupByField = implode(", ",$groupByCondition);
                }

            } elseif(CheckFieldPermission($fieldname,$modulename) != 'true') {
				if (!(in_array($modulename, $inventoryModules) && $fieldname == 'serviceid')) {
					$groupByField = $tablename.".".$colname;
				}
			}
        }
        return $groupByField;
    }

	function getReferenceFieldColumnList($moduleName, $fieldInfo) {
		$adb = PearDatabase::getInstance();

		$columnsSqlList = array();

		$fieldInstance = WebserviceField::fromArray($adb, $fieldInfo);
		$referenceModuleList = $fieldInstance->getReferenceList();
		$reportSecondaryModules = explode(':', $this->secondarymodule);

		if($moduleName != $this->primarymodule && in_array($this->primarymodule, $referenceModuleList)) {
			$entityTableFieldNames = getEntityFieldNames($this->primarymodule);
			$entityTableName = $entityTableFieldNames['tablename'];
			$entityFieldNames = $entityTableFieldNames['fieldname'];

			$columnList = array();
			if(is_array($entityFieldNames)) {
				foreach ($entityFieldNames as $entityColumnName) {
					$columnList["$entityColumnName"] = "$entityTableName.$entityColumnName";
				}
			} else {
				$columnList[] = "$entityTableName.$entityFieldNames";
			}
			if(count($columnList) > 1) {
				$columnSql = getSqlForNameInDisplayFormat($columnList, $this->primarymodule);
			} else {
				$columnSql = implode('', $columnList);
			}
			$columnsSqlList[] = $columnSql;

		} else {
			foreach($referenceModuleList as $referenceModule) {
				$entityTableFieldNames = getEntityFieldNames($referenceModule);
				$entityTableName = $entityTableFieldNames['tablename'];
				$entityFieldNames = $entityTableFieldNames['fieldname'];

				$referenceTableName = '';
				$dependentTableName = '';

				if($moduleName == 'HelpDesk' && $referenceModule == 'Accounts') {
					$referenceTableName = 'vtiger_accountRelHelpDesk';
				} elseif ($moduleName == 'HelpDesk' && $referenceModule == 'Contacts') {
					$referenceTableName = 'vtiger_contactdetailsRelHelpDesk';
				} elseif ($moduleName == 'HelpDesk' && $referenceModule == 'Products') {
					$referenceTableName = 'vtiger_productsRel';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'Accounts') {
					$referenceTableName = 'vtiger_accountRelCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'Contacts') {
					$referenceTableName = 'vtiger_contactdetailsCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'Leads') {
					$referenceTableName = 'vtiger_leaddetailsRelCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'Potentials') {
					$referenceTableName = 'vtiger_potentialRelCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'Invoice') {
					$referenceTableName = 'vtiger_invoiceRelCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'Quotes') {
					$referenceTableName = 'vtiger_quotesRelCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'PurchaseOrder') {
					$referenceTableName = 'vtiger_purchaseorderRelCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'SalesOrder') {
					$referenceTableName = 'vtiger_salesorderRelCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'HelpDesk') {
					$referenceTableName = 'vtiger_troubleticketsRelCalendar';
				} elseif ($moduleName == 'Calendar' && $referenceModule == 'Campaigns') {
					$referenceTableName = 'vtiger_campaignRelCalendar';
				} elseif ($moduleName == 'Contacts' && $referenceModule == 'Accounts') {
					$referenceTableName = 'vtiger_accountContacts';
				} elseif ($moduleName == 'Contacts' && $referenceModule == 'Contacts') {
					$referenceTableName = 'vtiger_contactdetailsContacts';
				} elseif ($moduleName == 'Accounts' && $referenceModule == 'Accounts') {
					$referenceTableName = 'vtiger_accountAccounts';
				} elseif ($moduleName == 'Campaigns' && $referenceModule == 'Products') {
					$referenceTableName = 'vtiger_productsCampaigns';
				} elseif ($moduleName == 'Faq' && $referenceModule == 'Products') {
					$referenceTableName = 'vtiger_productsFaq';
				} elseif ($moduleName == 'Invoice' && $referenceModule == 'SalesOrder') {
					$referenceTableName = 'vtiger_salesorderInvoice';
				} elseif ($moduleName == 'Invoice' && $referenceModule == 'Contacts') {
					$referenceTableName = 'vtiger_contactdetailsInvoice';
				} elseif ($moduleName == 'Invoice' && $referenceModule == 'Accounts') {
					$referenceTableName = 'vtiger_accountInvoice';
				} elseif ($moduleName == 'Potentials' && $referenceModule == 'Campaigns') {
					$referenceTableName = 'vtiger_campaignPotentials';
				} elseif ($moduleName == 'Products' && $referenceModule == 'Vendors') {
					$referenceTableName = 'vtiger_vendorRelProducts';
				} elseif ($moduleName == 'PurchaseOrder' && $referenceModule == 'Contacts') {
					$referenceTableName = 'vtiger_contactdetailsPurchaseOrder';
				} elseif ($moduleName == 'PurchaseOrder' && $referenceModule == 'Vendors') {
					$referenceTableName = 'vtiger_vendorRelPurchaseOrder';
				} elseif ($moduleName == 'Quotes' && $referenceModule == 'Potentials') {
					$referenceTableName = 'vtiger_potentialRelQuotes';
				} elseif ($moduleName == 'Quotes' && $referenceModule == 'Accounts') {
					$referenceTableName = 'vtiger_accountQuotes';
				} elseif ($moduleName == 'Quotes' && $referenceModule == 'Contacts') {
					$referenceTableName = 'vtiger_contactdetailsQuotes';
				} elseif ($moduleName == 'SalesOrder' && $referenceModule == 'Potentials') {
					$referenceTableName = 'vtiger_potentialRelSalesOrder';
				} elseif ($moduleName == 'SalesOrder' && $referenceModule == 'Accounts') {
					$referenceTableName = 'vtiger_accountSalesOrder';
				} elseif ($moduleName == 'SalesOrder' && $referenceModule == 'Contacts') {
					$referenceTableName = 'vtiger_contactdetailsSalesOrder';
				} elseif ($moduleName == 'SalesOrder' && $referenceModule == 'Quotes') {
					$referenceTableName = 'vtiger_quotesSalesOrder';
				} elseif ($moduleName == 'Potentials' && $referenceModule == 'Contacts') {
					$referenceTableName = 'vtiger_contactdetailsPotentials';
				} elseif ($moduleName == 'Potentials' && $referenceModule == 'Accounts') {
					$referenceTableName = 'vtiger_accountPotentials';
				} elseif (in_array($referenceModule, $reportSecondaryModules)) {
					$referenceTableName = "{$entityTableName}Rel$referenceModule";
					$dependentTableName = "vtiger_crmentityRel{$referenceModule}{$fieldInstance->getFieldId()}";
				} elseif (in_array($moduleName, $reportSecondaryModules)) {
					$referenceTableName = "{$entityTableName}Rel$moduleName";
					$dependentTableName = "vtiger_crmentityRel{$moduleName}{$fieldInstance->getFieldId()}";
				} else {
					$referenceTableName = "{$entityTableName}Rel{$moduleName}{$fieldInstance->getFieldId()}";
					$dependentTableName = "vtiger_crmentityRel{$moduleName}{$fieldInstance->getFieldId()}";
				}

				$this->queryPlanner->addTable($referenceTableName);

				if(isset($dependentTableName)){
				    $this->queryPlanner->addTable($dependentTableName);
				}
				$columnList = array();
				if(is_array($entityFieldNames)) {
					foreach ($entityFieldNames as $entityColumnName) {
						$columnList["$entityColumnName"] = "$referenceTableName.$entityColumnName";
					}
				} else {
					$columnList[] = "$referenceTableName.$entityFieldNames";
				}
				if(count($columnList) > 1) {
					$columnSql = getSqlForNameInDisplayFormat($columnList, $referenceModule);
				} else {
					$columnSql = implode('', $columnList);
				}
				if ($referenceModule == 'DocumentFolders' && $fieldInstance->getFieldName() == 'folderid') {
					$columnSql = 'vtiger_attachmentsfolder.foldername';
					$this->queryPlanner->addTable("vtiger_attachmentsfolder");
				}
				if ($referenceModule == 'Currency' && $fieldInstance->getFieldName() == 'currency_id') {
					$columnSql = "vtiger_currency_info$moduleName.currency_name";
					$this->queryPlanner->addTable("vtiger_currency_info$moduleName");
				    }
				$columnsSqlList[] = $columnSql;
			}
		}
		return $columnsSqlList;
	}
}
?>
