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
	{foreach key=index item=jsModel from=$SCRIPTS}
		<script type="{$jsModel->getType()}" src="{$jsModel->getSrc()}"></script>
	{/foreach}
	<div class="row-fluid padding1per">
		<span class="span3"><h4>{vtranslate('LBL_COPY_PRIVILEGES_FROM',"Settings:Roles")}</h4></span>
		<span class="span5">
			<select class="select2" id="directProfilePriviligesSelect" style="min-width : 200px" data-placeholder="{vtranslate('LBL_CHOOSE_PROFILES',$QUALIFIED_MODULE)}">
				<option></option>
				{foreach from=$ALL_PROFILES item=PROFILE}
					{if $PROFILE->isDirectlyRelated() eq false}
						<option value="{$PROFILE->getId()}" {if $RECORD_ID eq $PROFILE->getId()} selected="" {/if} >{$PROFILE->getName()}</option>
					{/if}	
				{/foreach}
			</select>
		</span>
	</div>
    <div class="summaryWidgetContainer">
        <label class="checkbox">
            <input type="hidden" name="viewall" value="0" />
            <input type="checkbox" name="viewall" {if $RECORD_MODEL->hasGlobalReadPermission()}checked="true"{/if} /> 
            {vtranslate('LBL_VIEW_ALL',$QUALIFIED_MODULE)}
            <span style="margin-left:25px">
                <i class="icon-info-sign"></i>
                <span style="margin-left:2px">{vtranslate('LBL_VIEW_ALL_DESC',$QUALIFIED_MODULE)}</span>
            </span>
        </label>
        <label class="checkbox">
            <input type="hidden" name="editall" value="0" />
            <input type="checkbox" name="editall" {if $RECORD_MODEL->hasGlobalWritePermission()}checked="true"{/if} />
            {vtranslate('LBL_EDIT_ALL',$QUALIFIED_MODULE)}
            <span style="margin-left:30px">
                <i class="icon-info-sign"></i>
                <span style="margin-left:2px">{vtranslate('LBL_EDIT_ALL_DESC',$QUALIFIED_MODULE)}</span>
            </span>
        </label>
    </div>
	<table class="table table-striped table-bordered profilesEditView">
		<thead>
			<tr class="blockHeader">
				<th width="30%" style="border-left: 1px solid #DDD !important;">
					<input checked="true" class="alignTop" type="checkbox" id="mainModulesCheckBox" />&nbsp;
					{vtranslate('LBL_MODULES', $QUALIFIED_MODULE)}
				</th>
				<th width="14%" style="border-left: 1px solid #DDD !important;">
					<input type="checkbox" {if !$RECORD_ID} class="alignTop" checked="true" {/if} id="mainAction4CheckBox" />&nbsp;
					{'LBL_VIEW_PRVILIGE'|vtranslate:$QUALIFIED_MODULE}
				</th>
				<th width="14%" style="border-left: 1px solid #DDD !important;">
					<input {if !$RECORD_ID} class="alignTop"  checked="true" {/if} type="checkbox" id="mainAction1CheckBox" />&nbsp;
					{'LBL_EDIT_PRVILIGE'|vtranslate:$QUALIFIED_MODULE}
				</th>
				<th width="14%" style="border-left: 1px solid #DDD !important;">
					<input checked="true" class="alignTop" type="checkbox" id="mainAction2CheckBox" />&nbsp;
					{'LBL_DELETE_PRVILIGE'|vtranslate:$QUALIFIED_MODULE}
				</th>
				<th width="28%" style="border-left: 1px solid #DDD !important;" nowrap="nowrap">{'LBL_FIELD_AND_TOOL_PRVILIGES'|vtranslate:$QUALIFIED_MODULE}</th>
			</tr>
		</thead>
		<tbody>
			{assign var=PROFILE_MODULES value=$RECORD_MODEL->getModulePermissions()}
			{foreach from=$PROFILE_MODULES key=TABID item=PROFILE_MODULE}
				{assign var=MODULE_NAME value=$PROFILE_MODULE->getName()}
				{if $MODULE_NAME neq 'Events'}
				{assign var=IS_RESTRICTED_MODULE value=$RECORD_MODEL->isRestrictedModule($PROFILE_MODULE->getName())}
				<tr>
					<td>
						<input class="modulesCheckBox alignTop" type="checkbox" name="permissions[{$TABID}][is_permitted]" data-value="{$TABID}" data-module-state="" {if $RECORD_MODEL->hasModulePermission($PROFILE_MODULE)}checked="true"{else} data-module-unchecked="true" {/if}> {$PROFILE_MODULE->get('label')|vtranslate:$PROFILE_MODULE->getName()}
					</td>
					{assign var="BASIC_ACTION_ORDER" value=array(2,0,1)}
					{foreach from=$BASIC_ACTION_ORDER item=ORDERID}
						<td style="border-left: 1px solid #DDD !important;">
							{assign var="ACTION_MODEL" value=$ALL_BASIC_ACTIONS[$ORDERID]}
							{assign var=ACTION_ID value=$ACTION_MODEL->get('actionid')}
							{if !$IS_RESTRICTED_MODULE && $ACTION_MODEL->isModuleEnabled($PROFILE_MODULE)}
								<input style="margin-left: 45% !important" class="action{$ACTION_ID}CheckBox" type="checkbox" name="permissions[{$TABID}][actions][{$ACTION_ID}]" data-action-state="{$ACTION_MODEL->getName()}" {if empty($RECORD_ID)} checked="true" {/if} {if $RECORD_ID} {if $RECORD_MODEL->hasModuleActionPermission($PROFILE_MODULE, $ACTION_MODEL)}checked="true"{else} data-action{$ACTION_ID}-unchecked="true"{/if} {/if}></td>
							{/if}
						</td>
					{/foreach}
					<td style="border-left: 1px solid #DDD !important;">
						{if $PROFILE_MODULE->getFields()}
							<div class="row-fluid">
								<span class="span4">&nbsp;</span>
								<span class="span4"><button type="button" data-handlerfor="fields" data-togglehandler="{$TABID}-fields" class="btn btn-mini" style="padding-right: 20px; padding-left: 20px;">
										<i class="icon-chevron-down"></i>
									</button></span>
							</div>
						{/if}
					</td>
				</tr>
				<tr class="hide">
					<td colspan="6" class="row-fluid" style="padding-left: 5%;padding-right: 5%">
						<div class="row-fluid hide" data-togglecontent="{$TABID}-fields">
							{if $PROFILE_MODULE->getFields()}
								<div class="span12">
									<label class="themeTextColor font-x-large pull-left"><strong>{vtranslate('LBL_FIELDS',$QUALIFIED_MODULE)}{if $MODULE_NAME eq 'Calendar'} {vtranslate('LBL_OF', $MODULE_NAME)} {vtranslate('LBL_TASKS', $MODULE_NAME)}{/if}</strong></label>
									<div class="pull-right">
										<span class="mini-slider-control ui-slider" data-value="0">
											<a style="margin-top: 5px" class="ui-slider-handle"></a>
										</span>
										<span style="margin-left:15px;">{vtranslate('LBL_INIVISIBLE',$QUALIFIED_MODULE)}</span>&nbsp;&nbsp;
										<span class="mini-slider-control ui-slider" data-value="1">
											<a style="margin-top: 5px" class="ui-slider-handle"></a>
										</span>
										<span style="margin-left:15px;">{vtranslate('LBL_READ_ONLY',$QUALIFIED_MODULE)}</span>&nbsp;&nbsp;
										<span class="mini-slider-control ui-slider" data-value="2">
											<a style="margin-top: 5px" class="ui-slider-handle"></a>
										</span>
										<span style="margin-left:15px;">{vtranslate('LBL_WRITE',$QUALIFIED_MODULE)}</span>
									</div>
									<div class="clearfix"></div>
								</div>
								<table class="table table-bordered table-striped">
									{foreach from=$PROFILE_MODULE->getFields() key=FIELD_NAME item=FIELD_MODEL name="fields"}
										{assign var="FIELD_ID" value=$FIELD_MODEL->getId()}
										{if $smarty.foreach.fields.index % 3 == 0}
											<tr>
											{/if}
											<td style="border-left: 1px solid #DDD !important;">
												{assign var="FIELD_LOCKED" value=$RECORD_MODEL->isModuleFieldLocked($PROFILE_MODULE, $FIELD_MODEL)}
												<input type="hidden" name="permissions[{$TABID}][fields][{$FIELD_ID}]" data-range-input="{$FIELD_ID}" value="{$RECORD_MODEL->getModuleFieldPermissionValue($PROFILE_MODULE, $FIELD_MODEL)}" readonly="true">
												<div class="mini-slider-control editViewMiniSlider pull-left" data-locked="{$FIELD_LOCKED}" data-range="{$FIELD_ID}" data-value="{$RECORD_MODEL->getModuleFieldPermissionValue($PROFILE_MODULE, $FIELD_MODEL)}"></div>
												<div class="pull-left">
												{if $FIELD_MODEL->isMandatory()}<span class="redColor">*</span>{/if} {vtranslate($FIELD_MODEL->get('label'), $MODULE_NAME)}
											</div>
										</td>
										{if $smarty.foreach.fields.last OR ($smarty.foreach.fields.index+1) % 3 == 0}
										</tr>
									{/if}
								{/foreach}
							</table>
							{if $MODULE_NAME eq 'Calendar'}
								{assign var=EVENT_MODULE value=$PROFILE_MODULES[16]}
								<label class="themeTextColor font-x-large pull-left"><strong>{vtranslate('LBL_FIELDS', $QUALIFIED_MODULE)} {vtranslate('LBL_OF', $EVENT_MODULE->getName())} {vtranslate('LBL_EVENTS', $EVENT_MODULE->getName())}</strong></label>
								<table class="table table-bordered table-striped">
									{foreach from=$EVENT_MODULE->getFields() key=FIELD_NAME item=FIELD_MODEL name="fields"}
										{assign var="FIELD_ID" value=$FIELD_MODEL->getId()}
										{if $smarty.foreach.fields.index % 3 == 0}
										<tr>
										{/if}
										<td style="border-left: 1px solid #DDD !important;">
											{assign var="FIELD_LOCKED" value=$RECORD_MODEL->isModuleFieldLocked($EVENT_MODULE, $FIELD_MODEL)}
											<input type="hidden" name="permissions[16][fields][{$FIELD_ID}]" data-range-input="{$FIELD_ID}" value="{$RECORD_MODEL->getModuleFieldPermissionValue($EVENT_MODULE, $FIELD_MODEL)}" readonly="true">
											<div class="mini-slider-control editViewMiniSlider pull-left" data-locked="{$FIELD_LOCKED}" data-range="{$FIELD_ID}" data-value="{$RECORD_MODEL->getModuleFieldPermissionValue($EVENT_MODULE, $FIELD_MODEL)}"></div>
											<div class="pull-left">
												{if $FIELD_MODEL->isMandatory()}<span class="redColor">*</span>{/if} {vtranslate($FIELD_MODEL->get('label'), $MODULE_NAME)}
											</div>
										</td>
										{if $smarty.foreach.fields.last OR ($smarty.foreach.fields.index+1) % 3 == 0}
										</tr>
										{/if}
									{/foreach}
								</table>
							{/if}
						</div>
						</ul>
					{/if}
					</div>
				</td>
			</tr>
			<tr class="hide">
				<td colspan="6" class="row-fluid" style="padding-left: 5%;padding-right: 5%;background-image: none !important;">
					<div class="row-fluid hide" data-togglecontent="{$TABID}-fields">
						<div class="span12"><label class="themeTextColor font-x-large pull-left"><strong>{vtranslate('LBL_TOOLS',$QUALIFIED_MODULE)}</strong></label></div>
						<table class="table table-bordered table-striped">
							{assign var=UTILITY_ACTION_COUNT value=0}
							{assign var="ALL_UTILITY_ACTIONS_ARRAY" value=array()}
							{foreach from=$ALL_UTILITY_ACTIONS item=ACTION_MODEL}
								{if $ACTION_MODEL->isModuleEnabled($PROFILE_MODULE)}
									{assign var="testArray" array_push($ALL_UTILITY_ACTIONS_ARRAY,$ACTION_MODEL)}
								{/if}
							{/foreach}	
							{foreach from=$ALL_UTILITY_ACTIONS_ARRAY item=ACTION_MODEL name="actions"}
								{if $smarty.foreach.actions.index % 3 == 0}
									<tr>
									{/if}
									{assign var=ACTIONID value=$ACTION_MODEL->get('actionid')}
									<td {if $smarty.foreach.actions.last && (($smarty.foreach.actions.index+1) % 3 neq 0)}
										{assign var="index" value=($smarty.foreach.actions.index+1) % 3}
										{assign var="colspan" value=4-$index}
										colspan="{$colspan}"
										{else}
											style="border-right: 1px solid #DDD !important;" 	
											{/if}>
												<input type="checkbox" class="alignTop" {if empty($RECORD_ID)} checked="true" {/if} name="permissions[{$TABID}][actions][{$ACTIONID}]" {if $RECORD_MODEL->hasModuleActionPermission($PROFILE_MODULE, $ACTIONID)}checked="true"{/if}> {$ACTION_MODEL->getName()}</td>
												{if $smarty.foreach.actions.last OR ($smarty.foreach.actions.index+1) % 3 == 0}
												</div>
											{/if}
											{/foreach}
										</table>
									</div>
								</td>
							</tr>
							{/if}
						{/foreach}
					</tbody>
				</table>
			{/strip}
