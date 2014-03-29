<?php
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
*
 ********************************************************************************/
global $theme;
$theme_path="themes/".$theme."/";
$image_path=$theme_path."images/";
require_once('modules/CustomView/CustomView.php');
require_once("config.php");
require_once('modules/Reports/Reports.php');
require_once('include/logging.php');
require_once("modules/Reports/ReportRun.php");
require_once('include/utils/utils.php');
require_once('Smarty_setup.php');

global $adb,$mod_strings,$app_strings;

$reportid = vtlib_purify($_REQUEST["record"]);
$folderid = vtlib_purify($_REQUEST["folderid"]);

$sql = "select * from vtiger_report where reportid=?";
$res = $adb->pquery($sql, array($reportid));

$numOfRows = $adb->num_rows($res);

if($numOfRows > 0) {

	$Report_ID = $adb->query_result($res,0,'reportid');
	if(empty($folderid)) {
		$folderid = $adb->query_result($res,0,'folderid');
	}
	$reporttype = $adb->query_result($res,0,'reporttype');

	$showCharts = false;
	if($reporttype == 'summary'){
		$showCharts = true;
	}

	global $primarymodule,$secondarymodule,$orderbylistsql,$orderbylistcolumns,$ogReport;
	//added to fix the ticket #5117
	global $current_user;
	require('user_privileges/user_privileges_'.$current_user->id.'.php');

	$ogReport = new Reports($reportid);
	$primarymodule = $ogReport->primodule;
	$restrictedmodules = array();
	if($ogReport->secmodule!='')
		$rep_modules = split(":",$ogReport->secmodule);
	else
		$rep_modules = array();

	array_push($rep_modules,$primarymodule);
	$modules_permitted = true;
	$modules_export_permitted = true;
	foreach($rep_modules as $mod){
		if(isPermitted($mod,'index')!= "yes" || vtlib_isModuleActive($mod)==false){
			$modules_permitted = false;
			$restrictedmodules[] = $mod;
		}
		if(isPermitted("$mod",'Export','')!='yes')
			$modules_export_permitted = false;
	}

	if(isPermitted($primarymodule,'index') == "yes" && $modules_permitted == true) {
		$oReportRun = ReportRun::getInstance($reportid);
		$filtersql = $oReportRun->RunTimeAdvFilter($advft_criteria,$advft_criteria_groups);

		$smarty = new vtigerCRM_Smarty;
		$smarty->assign("MOD", $mod_strings);
		$smarty->assign("APP", $app_strings);
		$smarty->assign("IMAGE_PATH", $image_path);
		$smarty->assign("REPORTID", $reportid);
		
		if($showCharts == true){
			require_once 'modules/Reports/CustomReportUtils.php';
			require_once 'include/ChartUtils.php';

			$groupBy = $oReportRun->getGroupingList($reportid);
			if(!empty($groupBy)){
				foreach ($groupBy as $key => $value) {
					//$groupByConditon = explode(" ",$value);
					//$groupByNew = explode("'",$groupByConditon[0]);
					list($tablename,$colname,$module_field,$fieldname,$single) = split(":",$key);
					list($module,$field)= split("_",$module_field);
					$fieldDetails = $key;
					break;
				}
				//$groupByField = $oReportRun->GetFirstSortByField($reportid);
				$queryReports = CustomReportUtils::getCustomReportsQuery($Report_ID,$filtersql);
				$queryResult = $adb->pquery($queryReports,array());
				//ChartUtils::generateChartDataFromReports($queryResult, strtolower($groupByNew[1]));
                if($adb->num_rows($queryResult)){
					$pieChart = ChartUtils::getReportPieChart($queryResult, strtolower($module_field),$fieldDetails,$reportid);
					$barChart = ChartUtils::getReportBarChart($queryResult, strtolower($module_field),$fieldDetails,$reportid);
					$smarty->assign("PIECHART",$pieChart);
					$smarty->assign("BARCHART",$barChart);
				}
				$smarty->assign('HASGROUPBY', true);
			} else {
			    $smarty->assign('HASGROUPBY', false);
			}
			$smarty->display('ReportChartRun.tpl');
		}
	}
}
// To abort any extra output emits.
exit;
?>
