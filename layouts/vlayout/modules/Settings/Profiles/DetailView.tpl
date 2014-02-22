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
<div class="container-fluid">
	<br>
	<h3>{vtranslate('LBL_PROFILE_VIEW', $QUALIFIED_MODULE)}</h3>
	<button class="btn pull-right" type="button" onclick='window.location.href="{$RECORD_MODEL->getEditViewUrl()}"'>{vtranslate('LBL_EDIT',$QUALIFIED_MODULE)}</button>
	<div class="clearfix"></div>
	<hr>
	<div class="profileDetailView">

		<div class="row-fluid">
			<div class="row-fluid">
				<label class="fieldLabel span2 muted"><span class="redColor">*</span>{vtranslate('LBL_PROFILE_NAME', $QUALIFIED_MODULE)}: </label>
				<span class="fieldValue span6" name="profilename" id="profilename" value="{$RECORD_MODEL->getName()}"><strong>{$RECORD_MODEL->getName()}</strong></span>
			</div><br>
            <div class="row-fluid">
				<label class="fieldLabel span2 muted">{vtranslate('LBL_DESCRIPTION', $QUALIFIED_MODULE)}:</strong></label>
				<span class="fieldValue span8" name="description" id="description"><strong>{$RECORD_MODEL->getDescription()}</strong></span>
			</div><br>
            {assign var="ENABLE_IMAGE_PATH" value="{vimage_path('Enable.png')}"}
            {assign var="DISABLE_IMAGE_PATH" value="{vimage_path('Disable.png')}"}
            <div class="summaryWidgetContainer">
                <div>
                    <img class="alignMiddle" src="{if $RECORD_MODEL->hasGlobalReadPermission()}{$ENABLE_IMAGE_PATH}{else}{$DISABLE_IMAGE_PATH}{/if}" />
                    &nbsp;{vtranslate('LBL_VIEW_ALL',$QUALIFIED_MODULE)}
                    <span style="margin-left:25px">
                        <i class="icon-info-sign"></i>
                        <span style="margin-left:2px">{vtranslate('LBL_VIEW_ALL_DESC',$QUALIFIED_MODULE)}</span>
                    </span>
                </div>
                <div  style="margin-top: 5px;">
                   <img class="alignMiddle" src="{if $RECORD_MODEL->hasGlobalWritePermission()}{$ENABLE_IMAGE_PATH}{else}{$DISABLE_IMAGE_PATH}{/if}" />
                   &nbsp;{vtranslate('LBL_EDIT_ALL',$QUALIFIED_MODULE)}
                   <span style="margin-left:30px">
                        <i class="icon-info-sign"></i>
                        <span style="margin-left:2px">{vtranslate('LBL_EDIT_ALL_DESC',$QUALIFIED_MODULE)}</span>
                    </span>
                </div>
            </div>
			<div class="row-fluid">
				<table class="table table-striped table-bordered">
					<thead>

						<tr>
							<th width="27%" style="border-left: 1px solid #DDD !important;">
								{vtranslate('LBL_MODULES', $QUALIFIED_MODULE)}
							</th>
							<th width="11%" style="border-left: 1px solid #DDD !important;">
								<span class="horizontalAlignCenter">

									&nbsp;{'LBL_VIEW_PRVILIGE'|vtranslate:$QUALIFIED_MODULE}
								</span>
							</th>
							<th width="12%" style="border-left: 1px solid #DDD !important;">
								<span class="horizontalAlignCenter" >

									&nbsp;{'LBL_EDIT_PRVILIGE'|vtranslate:$QUALIFIED_MODULE}
								</span>
							</th>
							<th width="11%" style="border-left: 1px solid #DDD !important;">
								<span class="horizontalAlignCenter" >{'LBL_DELETE_PRVILIGE'|vtranslate:$QUALIFIED_MODULE}</span>
							</th>
							<th width="39%" style="border-left: 1px solid #DDD !important;" nowrap="nowrap">{'LBL_FIELD_AND_TOOL_PRVILIGES'|vtranslate:$QUALIFIED_MODULE}</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$RECORD_MODEL->getModulePermissions() key=TABID item=PROFILE_MODULE}
							{assign var=IS_RESTRICTED_MODULE value=$RECORD_MODEL->isRestrictedModule($PROFILE_MODULE->getName())}
							<tr>
								<td>
									<img src="{if $RECORD_MODEL->hasModulePermission($PROFILE_MODULE)}{$ENABLE_IMAGE_PATH}{else}{$DISABLE_IMAGE_PATH}{/if}" class="alignMiddle" />&nbsp;{$PROFILE_MODULE->get('label')|vtranslate:$PROFILE_MODULE->getName()}
								</td>
								{assign var="BASIC_ACTION_ORDER" value=array(2,0,1)}
								{foreach from=$BASIC_ACTION_ORDER item=ACTION_ID}
									<td style="border-left: 1px solid #DDD !important;">
										{assign var="ACTION_MODEL" value=$ALL_BASIC_ACTIONS[$ACTION_ID]}
										{if !$IS_RESTRICTED_MODULE && $ACTION_MODEL->isModuleEnabled($PROFILE_MODULE)}
											<img style="margin-left: 40%" class="alignMiddle" src="{if $RECORD_MODEL->hasModuleActionPermission($PROFILE_MODULE, $ACTION_MODEL)}{$ENABLE_IMAGE_PATH}{else}{$DISABLE_IMAGE_PATH}{/if}" />
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
											<label class="themeTextColor font-x-large pull-left"><strong>{vtranslate('LBL_FIELDS',$QUALIFIED_MODULE)}</strong></label>
											<div class="pull-right">
												<span class="mini-slider-control ui-slider" data-value="0">
													<a style="margin-top: 4px;" class="ui-slider-handle"></a>
												</span>
												<span style="margin-left:15px;">{vtranslate('LBL_INIVISIBLE',$QUALIFIED_MODULE)}</span>&nbsp;
												<span class="mini-slider-control ui-slider" data-value="1">
													<a style="margin-top: 4px;" class="ui-slider-handle"></a>
												</span>
												<span style="margin-left:15px;">{vtranslate('LBL_READ_ONLY',$QUALIFIED_MODULE)}</span>&nbsp;
												<span class="mini-slider-control ui-slider" data-value="2">
													<a style="margin-top: 4px;" class="ui-slider-handle"></a>
												</span>
												<span style="margin-left:15px;">{vtranslate('LBL_WRITE',$QUALIFIED_MODULE)}</span>&nbsp;
											</div>
											<div class="clearfix"></div>
										</div>
										<table class="table table-bordered table-striped">
										{assign var=COUNTER value=0}
										{foreach from=$PROFILE_MODULE->getFields() key=FIELD_NAME item=FIELD_MODEL name="fields"}
											{if $FIELD_MODEL->isActiveField()}
											{assign var="FIELD_ID" value=$FIELD_MODEL->getId()}
											{if $COUNTER % 3 == 0}
												<tr>
											{/if}
											<td>
												{assign var="DATA_VALUE" value=$RECORD_MODEL->getModuleFieldPermissionValue($PROFILE_MODULE, $FIELD_MODEL)}
												{if $DATA_VALUE eq 0}
													<span class="mini-slider-control ui-slider" data-value="0">
														<a style="margin-top: 4px;" class="ui-slider-handle"></a>
													</span>
												{elseif $DATA_VALUE eq 1}
													<span class="mini-slider-control ui-slider" data-value="1">
														<a style="margin-top: 4px;" class="ui-slider-handle"></a>
													</span>
												{else}
													<span class="mini-slider-control ui-slider" data-value="2">
														<a style="margin-top: 4px;" class="ui-slider-handle"></a>
													</span>
												{/if}
												<span style="margin-left: 15px">
												{if $FIELD_MODEL->isMandatory()}<span class="redColor">*</span>{/if} {vtranslate($FIELD_MODEL->get('label'), $PROFILE_MODULE->getName())}
												</span>
											</td>
											{if $smarty.foreach.fields.last OR ($COUNTER+1) % 3 == 0}
												</tr>
											{/if}
											{assign var=COUNTER value=$COUNTER+1}
											{/if}
									{/foreach}
									</table>
									</div>
									</ul>
								{/if}
								</div>
							</td>
						</tr>
						<tr class="hide">
							<td colspan="6" class="row-fluid" style="padding-left: 5%;padding-right: 5%">
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
									{assign var=ACTION_ID value=$ACTION_MODEL->get('actionid')}
									<td {if $smarty.foreach.actions.last && (($smarty.foreach.actions.index+1) % 3 neq 0)}
										{assign var="index" value=($smarty.foreach.actions.index+1) % 3}
										{assign var="colspan" value=4-$index}
										colspan="{$colspan}"
									{/if}><img class="alignMiddle" src="{if $RECORD_MODEL->hasModuleActionPermission($PROFILE_MODULE, $ACTION_ID)}{$ENABLE_IMAGE_PATH}{else}{$DISABLE_IMAGE_PATH}{/if}" />&nbsp;&nbsp;{$ACTION_MODEL->getName()}</td>
									{if $smarty.foreach.actions.last OR ($smarty.foreach.actions.index+1) % 3 == 0}
										</div>
									{/if}
								{/foreach}
								</table>
								</div>
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
			</div>
		</div>
	</div>
</div>
{/strip}