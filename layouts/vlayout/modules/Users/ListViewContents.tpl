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
<input type="hidden" id="listViewEntriesCount" value="{$LISTVIEW_ENTIRES_COUNT}" />
<input type="hidden" id="pageStartRange" value="{$PAGING_MODEL->getRecordStartRange()}" />
<input type="hidden" id="pageEndRange" value="{$PAGING_MODEL->getRecordEndRange()}" />
<input type="hidden" id="previousPageExist" value="{$PAGING_MODEL->isPrevPageExists()}" />
<input type="hidden" id="nextPageExist" value="{$PAGING_MODEL->isNextPageExists()}" />
<input type="hidden" id="pageNumberValue" value= "{$PAGE_NUMBER}"/>
<input type="hidden" id="pageLimitValue" value= "{$PAGING_MODEL->getPageLimit()}" />
<input type="hidden" id="numberOfEntries" value= "{$LISTVIEW_ENTIRES_COUNT}" />
<input type="hidden" id="alphabetSearchKey" value= "{$MODULE_MODEL->getAlphabetSearchField()}" />
<input type="hidden" id="Operator" value="{$OPERATOR}" />
<input type="hidden" id="alphabetValue" value="{$ALPHABET_VALUE}" />
<input type="hidden" id="totalCount" value="{$LISTVIEW_COUNT}" />
<input type='hidden' value="{$PAGE_NUMBER}" id='pageNumber'>
<input type='hidden' value="{$PAGING_MODEL->getPageLimit()}" id='pageLimit'>
<input type="hidden" value="{$LISTVIEW_ENTIRES_COUNT}" id="noOfEntries">

{assign var = ALPHABETS_LABEL value = vtranslate('LBL_ALPHABETS', 'Vtiger')}
{assign var = ALPHABETS value = ','|explode:$ALPHABETS_LABEL}

<div class="alphabetSorting">
	<table width="100%" class="table-bordered" style="border: 1px solid #ddd;table-layout: fixed">
		<tbody>
			<tr>
			{foreach item=ALPHABET from=$ALPHABETS}
				<td class="alphabetSearch textAlignCenter cursorPointer {if $ALPHABET_VALUE eq $ALPHABET} highlightBackgroundColor {/if}" style="padding : 0px !important"><a id="{$ALPHABET}" href="#">{$ALPHABET}</a></td>
			{/foreach}
			</tr>
		</tbody>
	</table>
</div>
<div class="listViewEntriesDiv" style='overflow-x:auto;'>
	<input type="hidden" value="{$ORDER_BY}" id="orderBy">
	<input type="hidden" value="{$SORT_ORDER}" id="sortOrder">
	<span class="listViewLoadingImageBlock hide modal" id="loadingListViewModal">
		<img class="listViewLoadingImage" src="{vimage_path('loading.gif')}" alt="no-image" title="{vtranslate('LBL_LOADING', $MODULE)}"/>
		<p class="listViewLoadingMsg">{vtranslate('LBL_LOADING_LISTVIEW_CONTENTS', $MODULE)}........</p>
	</span>
	{assign var=WIDTHTYPE value=$USER_MODEL->get('rowheight')}
	<table class="table table-bordered listViewEntriesTable">
		<thead>
			<tr class="listViewHeaders">
				{foreach item=LISTVIEW_HEADER from=$LISTVIEW_HEADERS}
					{if $LISTVIEW_HEADER->getName() eq 'first_name'}
						<th nowrap class="{$WIDTHTYPE}">
							<a href="javascript:void(0);" class="listViewHeaderValues" data-nextsortorderval="{if $COLUMN_NAME eq $LISTVIEW_HEADER->get('column')}{$NEXT_SORT_ORDER}{else}ASC{/if}" data-columnname="{$LISTVIEW_HEADER->get('column')}">{vtranslate('LBL_USER_LIST_DETAILS', $MODULE)}
							&nbsp;&nbsp;{if $COLUMN_NAME eq $LISTVIEW_HEADER->get('column')}<img class="{$SORT_IMAGE} icon-white">{/if}</a>
						</th>
					{elseif $LISTVIEW_HEADER->getName() neq 'last_name' and $LISTVIEW_HEADER->getName() neq 'email1'}
						<th nowrap class="{$WIDTHTYPE}"><a href="javascript:void(0);" class="listViewHeaderValues" data-nextsortorderval="{if $COLUMN_NAME eq $LISTVIEW_HEADER->get('column')}{$NEXT_SORT_ORDER}{else}ASC{/if}" data-columnname="{$LISTVIEW_HEADER->get('column')}">{vtranslate($LISTVIEW_HEADER->get('label'), $MODULE)}
							&nbsp;&nbsp;{if $COLUMN_NAME eq $LISTVIEW_HEADER->get('column')}<img class="{$SORT_IMAGE} icon-white">{/if}</a>
						</th>
					{/if}
				{/foreach}
			</tr>
		</thead>
		{foreach item=LISTVIEW_ENTRY from=$LISTVIEW_ENTRIES name=listview}
		<tr class="listViewEntries" data-id='{$LISTVIEW_ENTRY->getId()}' data-recordUrl='{$LISTVIEW_ENTRY->getDetailViewUrl()}' id="{$MODULE}_listView_row_{$smarty.foreach.listview.index+1}">
			{foreach item=LISTVIEW_HEADER from=$LISTVIEW_HEADERS}
			{assign var=LISTVIEW_HEADERNAME value=$LISTVIEW_HEADER->get('name')}
			<input type="hidden" name="deleteActionUrl" value="{$LISTVIEW_ENTRY->getDeleteUrl()}">
				{if $LISTVIEW_HEADER->getName() eq 'first_name'}
					<td class="listViewEntryValue {$WIDTHTYPE}">
					<div class='row-fluid'>
						<div class='span6'>
							{assign var=IMAGE_DETAILS value=$LISTVIEW_ENTRY->getImageDetails()}
							{foreach item=IMAGE_INFO from=$IMAGE_DETAILS}
								<div class='span2'>
									{if !empty($IMAGE_INFO.path) && !empty({$IMAGE_INFO.orgname})}
										<img src="{$IMAGE_INFO.path}_{$IMAGE_INFO.orgname}">
									{/if}
								</div>
							{/foreach}
							{if $IMAGE_DETAILS[0]['id'] eq null}
								<div class='span2'>
									<img src="{vimage_path('DefaultUserIcon.png')}">
								</div>
							{/if}
						</div>
						<div class='span6'>
							<div>
								<a href="{$LISTVIEW_ENTRY->getDetailViewUrl()}">{$LISTVIEW_ENTRY->get($LISTVIEW_HEADERNAME)} {$LISTVIEW_ENTRY->get('last_name')}</a>
							</div>
							<div>
								{$LISTVIEW_ENTRY->get('email1')}
							</div>
						</div>
					</div>
					</td>
				{elseif $LISTVIEW_HEADER->getName() neq 'last_name' and $LISTVIEW_HEADER->getName() neq 'email1'}
					<td class="{$WIDTHTYPE}" nowrap>{$LISTVIEW_ENTRY->get($LISTVIEW_HEADERNAME)}
						{if !$LISTVIEW_HEADER@last}</td>{/if}
				{/if}
				{if $LISTVIEW_HEADER@last}
					<div class="pull-right actions">
						<span class="actionImages">
							{if $IS_MODULE_EDITABLE && $LISTVIEW_ENTRY->get('status') eq 'Active'}
								<a id="{$MODULE}_LISTVIEW_ROW_{$LISTVIEW_ENTRY->getId()}_EDIT" href='{$LISTVIEW_ENTRY->getEditViewUrl()}'><i title="{vtranslate('LBL_EDIT', $MODULE)}" class="icon-pencil alignMiddle"></i></a>&nbsp;
							{/if}
							{if $IS_MODULE_DELETABLE && $LISTVIEW_ENTRY->getId() != $USER_MODEL->getId() && $LISTVIEW_ENTRY->get('status') eq 'Active'}
								<a id="{$MODULE}_LISTVIEW_ROW_{$LISTVIEW_ENTRY->getId()}_DELETE" class="deleteRecordButton"><i title="{vtranslate('LBL_DELETE', $MODULE)}" class="icon-trash alignMiddle"></i></a>
							{/if}
						</span>
					</div>
				{/if}
			{/foreach}
		</tr>
		{/foreach}
	</table>

	{if $LISTVIEW_ENTIRES_COUNT eq '0'}
		<table class="emptyRecordsDiv">
			<tbody>
				<tr>
					<td>
						{assign var=SINGLE_MODULE value="SINGLE_$MODULE"}
						{vtranslate('LBL_NO')} {vtranslate($MODULE, $MODULE)} {vtranslate('LBL_FOUND')}.<!--{if $IS_MODULE_EDITABLE} {vtranslate('LBL_CREATE')} <a href="{$MODULE_MODEL->getCreateRecordUrl()}">{vtranslate($SINGLE_MODULE, $MODULE)}</a>-->{/if}
					</td>
				</tr>
			</tbody>
		</table>
	{/if}

</div>
{/strip}