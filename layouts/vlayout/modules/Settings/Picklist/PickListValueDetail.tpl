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
<ul class="nav nav-tabs massEditTabs" style="margin-bottom: 0;border-bottom: 0">
	<li class="active"><a href="#allValuesLayout" data-toggle="tab"><strong>{vtranslate('LBL_ALL_VALUES',$QUALIFIED_MODULE)}</strong></a></li>
	{if $SELECTED_PICKLIST_FIELDMODEL->isRoleBased()}<li id="assignedToRoleTab"><a href="#AssignedToRoleLayout" data-toggle="tab"><strong>{vtranslate('LBL_VALUES_ASSIGNED_TO_A_ROLE',$QUALIFIED_MODULE)}</strong></a></li>{/if}
</ul>
<div class="tab-content layoutContent padding20 themeTableColor overflowVisible">
	<div class="tab-pane active" id="allValuesLayout">	
		<div class="row-fluid">
			<div class="span5 marginLeftZero textOverflowEllipsis">
				<table id="pickListValuesTable" class="table table-bordered table-condensed table-striped" style="table-layout: fixed">
					<thead>
						<tr class="listViewHeaders"><th>{vtranslate($SELECTED_PICKLIST_FIELDMODEL->get('label'),$SELECTED_MODULE_NAME)}&nbsp;{vtranslate('LBL_ITEMS',$QUALIFIED_MODULE)}</th></tr>
					</thead>
					<tbody>
					<input type="hidden" id="dragImagePath" value="{vimage_path('drag.png')}" />
					{assign var=PICKLIST_VALUES value=$SELECTED_PICKLISTFIELD_ALL_VALUES}
					{foreach key=PICKLIST_KEY item=PICKLIST_VALUE from=$PICKLIST_VALUES}
						<tr class="pickListValue cursorPointer" data-key="{Vtiger_Util_Helper::toSafeHTML($PICKLIST_VALUE)}">
							<td class="textOverflowEllipsis"><img class="alignMiddle" src="{vimage_path('drag.png')}"/>&nbsp;&nbsp;{vtranslate($PICKLIST_VALUE,$SELECTED_MODULE_NAME)}</td>
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
			<div class="span2 row-fluid">
				{if $SELECTED_PICKLIST_FIELDMODEL->isEditable()}
					{if $SELECTED_PICKLIST_FIELDMODEL->isRoleBased()}
						<button class="btn span10 marginLeftZero" id="assignValue">{vtranslate('LBL_ASSIGN_VALUE',$QUALIFIED_MODULE)}</button><br><br>
					{/if}	
					<button class="btn span10 marginLeftZero" id="addItem">{vtranslate('LBL_ADD_VALUE',$QUALIFIED_MODULE)}</button><br><br>
					<button class="btn span10 marginLeftZero" id="renameItem">{vtranslate('LBL_RENAME_VALUE',$QUALIFIED_MODULE)}</button><br><br>
					<button class="btn btn-danger span10 marginLeftZero"  id="deleteItem">{vtranslate('LBL_DELETE_VALUE',$QUALIFIED_MODULE)}</button><br><br>
				{/if}
				<button class="btn btn-success span10 marginLeftZero" disabled=""  id="saveSequence">{vtranslate('LBL_SAVE_ORDER',$QUALIFIED_MODULE)}</button><br><br>
			</div>
			<div class="span4">
				<br><br><br>
				<div><i class="icon-info-sign"></i>&nbsp;<span>{vtranslate('LBL_DRAG_ITEMS_TO_RESPOSITION',$QUALIFIED_MODULE)}</span></div>
				<br><div>&nbsp;&nbsp;{vtranslate('LBL_SELECT_AN_ITEM_TO_RENAME_OR_DELETE',$QUALIFIED_MODULE)}</div> 
				<br><div>&nbsp;&nbsp;{vtranslate('LBL_TO_DELETE_MULTIPLE_HOLD_CONTROL_KEY',$QUALIFIED_MODULE)}</div>
			</div>	
		</div>		
		<div id="createViewContents" class="hide">
			{include file="CreateView.tpl"|@vtemplate_path:$QUALIFIED_MODULE}
		</div>
	</div>
	{if $SELECTED_PICKLIST_FIELDMODEL->isRoleBased()}
		<div class="tab-pane" id="AssignedToRoleLayout">
			<div class="row-fluid">
				<div class="span2" style="margin-top: 5px">{vtranslate('LBL_ROLE_NAME',$QUALIFIED_MODULE)}</div>
				<div class="span7">
					<select id="rolesList" class="select2" name="rolesSelected" style="min-width: 220px" data-placeholder="{vtranslate('LBL_CHOOSE_ROLES',$QUALIFIED_MODULE)}">
						{foreach from=$ROLES_LIST item=ROLE}
							<option value="{$ROLE->get('roleid')}">{$ROLE->get('rolename')}</option>
						{/foreach}
					</select>	
				</div>
			</div>
			<div id="pickListValeByRoleContainer">
			</div>	
		</div>
	{/if}
</div>	
{/strip}