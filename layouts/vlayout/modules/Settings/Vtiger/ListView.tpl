{*<!--
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
*
 ********************************************************************************/
-->*}
{strip}
<div>
	<div class="listViewTopMenuDiv">{include file='ListViewHeader.tpl'|@vtemplate_path:$QUALIFIED_MODULE}</div>
	<div class="listViewContentDiv" id="listViewContents"><br>{include file='ListViewContents.tpl'|@vtemplate_path:$QUALIFIED_MODULE}</div>
</div>
{/strip}