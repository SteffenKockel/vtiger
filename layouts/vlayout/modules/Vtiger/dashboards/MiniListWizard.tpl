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
{if $WIZARD_STEP eq 'step1'}
	<div id="minilistWizardContainer" class='modelContainer'>
		<div class="modal-header contentsBackground">
            <button data-dismiss="modal" class="close" title="{vtranslate('LBL_CLOSE')}">&times;</button>
			<h3 id="massEditHeader">{vtranslate('LBL_MINI_LIST', $MODULE)} {vtranslate($MODULE, $MODULE)}</h3>
		</div>
		<form class="form-horizontal" method="post" action="javascript:;">
			<input type="hidden" name="module" value="{$MODULE}" />
			<input type="hidden" name="action" value="MassSave" />

			<table class="table table-bordered">
				<tbody>
					<tr>
						<td class="fieldLabel alignMiddle">{'LBL_SELECT_MODULE'|vtranslate}</td>
						<td class="fieldValue">
							<select class="span4" name="module">
								<option></option>
								{foreach from=$MODULES item=MODULE_MODEL key=MODULE_NAME}
								<option value="{$MODULE_NAME}">{vtranslate($MODULE_NAME, $MODULE_NAME)}</option>
								{/foreach}
							</select>
						</td>
					</tr>
					<tr>
						<td class="fieldLabel alignMiddle">{'LBL_FILTER'|vtranslate}</td>
						<td class="fieldValue">
							<select class="span4" name="filterid">
								<option></option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="fieldLabel alignMiddle">{'LBL_EDIT_FIELDS'|vtranslate}</td>
						<td class="fieldValue">
							<select class="span4" name="fields" size="2" multiple="true">
								<option></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
			{include file='ModalFooter.tpl'|@vtemplate_path:$MODULE}
		</form>
	</div>
{elseif $WIZARD_STEP eq 'step2'}
	<option></option>
	{foreach from=$ALLFILTERS item=FILTERS key=FILTERGROUP}
		<optgroup label="{$FILTERGROUP}">
			{foreach from=$FILTERS item=FILTER key=FILTERNAME}
				<option value="{$FILTER->getId()}">{$FILTER->get('viewname')}</option>
			{/foreach}
		</optgroup>
	{/foreach}
{elseif $WIZARD_STEP eq 'step3'}
	<option></option>
	{foreach from=$LIST_VIEW_CONTROLLER->getListViewHeaderFields() item=FIELD key=FIELD_NAME}
		<option value="{$FIELD_NAME}">{vtranslate($FIELD->getFieldLabelKey(),$SELECTED_MODULE)}</option>
	{/foreach}
{/if}
{/strip}