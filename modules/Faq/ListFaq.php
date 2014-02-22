<?php
/*********************************************************************************
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

/**	function used to get the top 5 recent FAQs from Listview query
 *	@return array $values - array with the title, header and entries like  Array('Title'=>$title,'Header'=>$listview_header,'Entries'=>$listview_entries) where as listview_header and listview_entries are arrays of header and entity values which are returned from function getListViewHeader and getListViewEntries
 */
function getMyFaq($maxval,$calCnt)
{
	require_once("data/Tracker.php");
	require_once('modules/Faq/Faq.php');
	require_once('include/logging.php');
	require_once('include/ListView/ListView.php');
	require_once('include/utils/utils.php');
	require_once('modules/CustomView/CustomView.php');

	global $current_language,$current_user,$list_max_entries_per_page,$theme,$adb;
	$current_module_strings = return_module_language($current_language, 'Faq');

	$log = LoggerManager::getLogger('faq_list');

	$url_string = '';
	$sorder = '';
	$oCustomView = new CustomView("Faq");
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
	$focus = new Faq();

	$theme_path="themes/".$theme."/";
	$image_path=$theme_path."images/";

	//Retreive the list from Database
	//<<<<<<<<<customview>>>>>>>>>
	$date_var = date('Y-m-d');

	$where = ' and vtiger_crmentity.smownerid='.$current_user->id.' and  vtiger_faq.status <> \'Obsolete\' ORDER BY vtiger_crmentity.createdtime DESC';
	$query = getListQuery("Faq",$where);

	//<<<<<<<<customview>>>>>>>>>
	
	$query .= " LIMIT 0," . $adb->sql_escape_string($maxval);
	
	if($calCnt == 'calculateCnt') {
		$list_result_rows = $adb->query(mkCountQuery($query));
		return $adb->query_result($list_result_rows, 0, 'count');
	}

	$list_result = $adb->query($query);

	//Retreiving the no of rows
	$noofrows = $adb->num_rows($list_result);

	//Retreiving the start value from request
	if(isset($_REQUEST['start']) && $_REQUEST['start'] != '')
	{
		$start = vtlib_purify($_REQUEST['start']);
	}
	else
	{

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


	//Retreive the List View Table Header
	$title=array('myFaqs.gif',$current_module_strings['LBL_MY_FAQ'],'home_myfaq');
	$listview_header = getListViewHeader($focus,"Faq",$url_string,$sorder,$order_by,"HomePage",$oCustomView);
	$header = Array($listview_header[1],$listview_header[3]);

	$listview_entries = getListViewEntries($focus,"Faq",$list_result,$navigation_array,"HomePage","","EditView","Delete",$oCustomView);
	foreach($listview_entries as $crmid=>$valuearray)
	{
		$entries[$crmid] = Array($valuearray[1],$valuearray[3]);	
	}
	
	$search_qry = "&query=true&Fields0=vtiger_faq.status&Condition0=isn&Srch_value0=Obsolete&searchtype=advance&search_cnt=1&matchtype=any";
	
	$values=Array('ModuleName'=>'Faq','Title'=>$title,'Header'=>$header,'Entries'=>$entries,'search_qry'=>$search_qry);
	if ( ($display_empty_home_blocks && $noofrows == 0 ) || ($noofrows>0) )	
		return $values;
}
?>