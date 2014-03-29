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
<input type="hidden" id="pageStartRange" value="{$PAGING_MODEL->getRecordStartRange()}" />
<input type="hidden" id="pageEndRange" value="{$PAGING_MODEL->getRecordEndRange()}" />
<input type="hidden" id="previousPageExist" value="{$PAGING_MODEL->isPrevPageExists()}" />
<input type="hidden" id="nextPageExist" value="{$PAGING_MODEL->isNextPageExists()}" />
<input type="hidden" id="numberOfEntries" value= "{$LISTVIEW_ENTIRES_COUNT}" />
<input type="hidden" id="totalCount" value="{$LISTVIEW_COUNT}" />
<input type='hidden' id='pageNumber' value="{$PAGE_NUMBER}" >
<input type='hidden' id='pageLimit' value="{$PAGING_MODEL->getPageLimit()}">
<input type="hidden" id="noOfEntries" value="{$LISTVIEW_ENTIRES_COUNT}">

<div id="selectAllMsgDiv" class="alert-block msgDiv">
	<strong><a id="selectAllMsg">{vtranslate('LBL_SELECT_ALL',$MODULE)}&nbsp;{vtranslate($MODULE ,$MODULE)}&nbsp;(<span id="totalRecordsCount"></span>)</a></strong>
</div>
<div id="deSelectAllMsgDiv" class="alert-block msgDiv">
	<strong><a id="deSelectAllMsg">{vtranslate('LBL_DESELECT_ALL_RECORDS',$MODULE)}</a></strong>
</div>

<div class="contents-topscroll">
	<div class="topscroll-div">
		&nbsp;
	 </div>
</div>
<div class="listViewEntriesDiv contents-bottomscroll">
	<div class="bottomscroll-div">
	<input type="hidden" value="{$ORDER_BY}" id="orderBy">
	<input type="hidden" value="{$SORT_ORDER}" id="sortOrder">
	<p class="listViewLoadingMsg hide">{vtranslate('LBL_LOADING_LISTVIEW_CONTENTS', $MODULE)}........</p>
	{assign var=WIDTHTYPE value=$CURRENT_USER_MODEL->get('rowheight')}
	<table class="table table-bordered listViewEntriesTable">
		<thead>
			<tr class="listViewHeaders">
				<th class="{$WIDTHTYPE}"><input type="checkbox" id="listViewEntriesMainCheckBox"></th>
				{foreach key=LISTVIEW_HEADER_KEY item=LISTVIEW_HEADER from=$LISTVIEW_HEADERS}
					<th nowrap {if $LISTVIEW_HEADER@last} colspan="2" {/if} class="{$WIDTHTYPE}">
						<a href="javascript:void(0);" class="listViewHeaderValues" data-nextsortorderval="{if $COLUMN_NAME eq $LISTVIEW_HEADER_KEY}{$NEXT_SORT_ORDER}{else}ASC{/if}" data-columnname="{$LISTVIEW_HEADER_KEY}">{vtranslate($LISTVIEW_HEADERS[$LISTVIEW_HEADER_KEY],$MODULE)}
						&nbsp;&nbsp;{if $COLUMN_NAME eq $LISTVIEW_HEADER_KEY}<img class="{$SORT_IMAGE} icon-white">{/if}</a>
					</th>
				{/foreach}
			</tr>
		</thead>
		{foreach item=LISTVIEW_ENTRY from=$LISTVIEW_ENTRIES name=listview}
		<tr class="listViewEntries" data-id={$LISTVIEW_ENTRY->getId()} data-recordUrl='{$LISTVIEW_ENTRY->getDetailViewUrl()}' id="{$MODULE}_listView_row_{$smarty.foreach.listview.index+1}">
			<td class="{$WIDTHTYPE}"><input type="checkbox" value="{$LISTVIEW_ENTRY->getId()}" class="listViewEntriesCheckBox"></td>
			{foreach key=LISTVIEW_HEADER_KEY item=LISTVIEW_HEADER from=$LISTVIEW_HEADERS}
				<td nowrap class="{$WIDTHTYPE}">
					<a href="{$LISTVIEW_ENTRY->getDetailViewUrl()}">{vtranslate($LISTVIEW_ENTRY->get($LISTVIEW_HEADER_KEY), $MODULE)}</a>
					{if $LISTVIEW_HEADER@last}
						</td><td nowrap class="{$WIDTHTYPE}">
						<div class="pull-right actions">
							<span class="actionImages">
								<a href='{$LISTVIEW_ENTRY->getEditViewUrl()}'><i title="{vtranslate('LBL_EDIT', $MODULE)}" class="icon-pencil alignMiddle"></i></a>&nbsp;
								{if $LISTVIEW_ENTRY->isDefault() eq false}
									<a class="deleteRecordButton"><i title="{vtranslate('LBL_DELETE', $MODULE)}" class="icon-trash alignMiddle"></i></a>
								{/if}
							</span>
						</div>
						</td>
					{/if}
				</td>
			{/foreach}
		</tr>
		{/foreach}
	</table>

<!--added this div for Temporarily -->
{if $LISTVIEW_ENTIRES_COUNT eq '0'}
	<table class="emptyRecordsDiv">
		<tbody>
			<tr>
				<td>
					{assign var=SINGLE_MODULE value="SINGLE_$MODULE"}
					{vtranslate('LBL_NO')} {vtranslate($MODULE, $MODULE)} {vtranslate('LBL_FOUND')}. {vtranslate('LBL_CREATE')} <a href="{$MODULE_MODEL->getCreateRecordUrl()}&folderid={$VIEWNAME}">{vtranslate($SINGLE_MODULE, $MODULE)}</a>
				</td>
			</tr>
		</tbody>
	</table>
{/if}
</div>
</div>
{/strip}