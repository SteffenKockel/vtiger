<?php
/*+********************************************************************************
 * The contents of this file are subject to the SugarCRM Public License Version 1.1.2
 * ("License"); You may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.sugarcrm.com/SPL
 * Software distributed under the License is distributed on an  "AS IS"  basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 * The Original Code is:  SugarCRM Open Source
 * The Initial Developer of the Original Code is SugarCRM, Inc.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.;
 * All Rights Reserved.
 * Contributor(s): ______________________________________.
 ********************************************************************************/
function getTopInvoice($maxval,$calCnt)
{
	require_once("data/Tracker.php");
	require_once('modules/Invoice/Invoice.php');
	require_once('include/logging.php');
	require_once('include/ListView/ListView.php');
	require_once('include/utils/utils.php');
	require_once('modules/CustomView/CustomView.php');

	global $app_strings,$current_language,$current_user,$adb,$list_max_entries_per_page,$theme;
	$current_module_strings = return_module_language($current_language, 'Invoice');

	$log = LoggerManager::getLogger('invoice_list');

	$url_string = '';
	$sorder = '';
	$oCustomView = new CustomView("Invoice");
	$customviewcombo_html = $oCustomView->getCustomViewCombo();
	if(isset($_REQUEST['viewname']) == false || $_REQUEST['viewname']=='')
	{
		if($oCustomView->setdefaultviewid != "")
		{
			$viewid = $oCustomView->setdefaultviewid;
		}else
		{
			$viewid = "0";
		}
	}
	
	$theme_path="themes/".$theme."/";
	$image_path=$theme_path."images/";

	//Retreive the list from Database
	//<<<<<<<<<customview>>>>>>>>>
	$date_var = date('Y-m-d');
	$currentModule = 'Invoice';
	$viewId = getCvIdOfAll($currentModule);
	$queryGenerator = new QueryGenerator($currentModule, $current_user);
	$queryGenerator->initForCustomViewById($viewId);
	$meta = $queryGenerator->getMeta($currentModule);
	$accessibleFieldNameList = array_keys($meta->getModuleFields());
	$customViewFields = $queryGenerator->getCustomViewFields();
	$fields = $queryGenerator->getFields();
	$newFields = array_diff($fields, $customViewFields);
	$widgetFieldsList = array('subject','salesorder_id','account_id','contact_id','invoicestatus',
		'total');
	$widgetFieldsList = array_intersect($accessibleFieldNameList, $widgetFieldsList);
	$widgetSelectedFields = array_chunk(array_intersect($customViewFields, $widgetFieldsList), 2);
	//select the first chunk of two fields
	$widgetSelectedFields = $widgetSelectedFields[0];
	if(count($widgetSelectedFields) < 2) {
		$widgetSelectedFields = array_chunk(array_merge($widgetSelectedFields, $accessibleFieldNameList), 2);
		//select the first chunk of two fields
		$widgetSelectedFields = $widgetSelectedFields[0];
	}
	$newFields = array_merge($newFields, $widgetSelectedFields);
	$queryGenerator->setFields($newFields);
	$_REQUEST = getTopInvoiceSearch($_REQUEST, array(
		'assigned_user_id'=>$current_user->column_fields['user_name']));
	$queryGenerator->addUserSearchConditions($_REQUEST);
	$search_qry = '&query=true'.getSearchURL($_REQUEST);
	$query = $queryGenerator->getQuery();

	//<<<<<<<<customview>>>>>>>>>

	$query .= " LIMIT " . $adb->sql_escape_string($maxval);
	
	if($calCnt == 'calculateCnt') {
		$list_result_rows = $adb->query(mkCountQuery($query));
		return $adb->query_result($list_result_rows, 0, 'count');
	}
	
	$list_result = $adb->query($query);

	//Retreiving the no of rows
	$noofrows = $adb->num_rows($list_result);

	//Retreiving the start value from request
	if(isset($_REQUEST['start']) && $_REQUEST['start'] != '') {
		$start = vtlib_purify($_REQUEST['start']);
	} else {
		$start = 1;
	}

	//Retreive the Navigation array
	$navigation_array = getNavigationValues($start, $noofrows, $list_max_entries_per_page);

	if ($navigation_array['start'] == 1)
	{
		if($noofrows != 0)
			$start_rec = $navigation_array['start'];
		else
			$start_rec = 0;
		if($noofrows > $list_max_entries_per_page)
		{
			$end_rec = $navigation_array['start'] + $list_max_entries_per_page - 1;
		}
		else
		{
			$end_rec = $noofrows;
		}

	}
	else
	{
		if($navigation_array['next'] > $list_max_entries_per_page)
		{
			$start_rec = $navigation_array['next'] - $list_max_entries_per_page;
			$end_rec = $navigation_array['next'] - 1;
		}
		else
		{
			$start_rec = $navigation_array['prev'] + $list_max_entries_per_page;
			$end_rec = $noofrows;
		}
	}

	$focus = new Invoice();

	$title=array('myTopInvoices.gif',$current_module_strings['LBL_MY_TOP_INVOICE'],'home_mytopinv');
	//Retreive the List View Table Header
	$controller = new ListViewController($adb, $current_user, $queryGenerator);
	$controller->setHeaderSorting(false);
	$header = $controller->getListViewHeader($focus,$currentModule,$url_string,$sorder,
			$order_by, true);

	$entries = $controller->getListViewEntries($focus,$currentModule,$list_result,
	$navigation_array, true);	

	$values=Array('ModuleName'=>'Invoice','Title'=>$title,'Header'=>$header,'Entries'=>$entries,'search_qry'=>$search_qry);

	if ( ($display_empty_home_blocks && $noofrows == 0 ) || ($noofrows>0) )
		return $values;
}

function getTopInvoiceSearch($output, $input) {
	$output['query'] = 'true';
	$output['Fields0'] = 'invoicestatus';
	$output['Condition0'] = 'n';
	$output['Srch_value0'] = 'Paid';
	$output['Fields1'] = 'assigned_user_id';
	$output['Condition1'] = 'e';
	$output['Srch_value1'] = $input['assigned_user_id'];
	$output['searchtype'] = 'advance';
	$output['search_cnt'] = '2';
	$output['matchtype'] = 'all';
	return $output;
}

?>
