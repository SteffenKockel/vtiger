{*<!--
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/
-->*}
{strip}
<div class="relatedTabModulesList">
{if empty($RELATED_MODULES)}
	<div class="emptyRelatedTabs">
		<div class="recordDetails">
			<p class="textAlignCenter">{vtranslate('LBL_NO_RELATED_INFORMATION',$QUALIFIED_MODULE)}</p>
		</div>
	</div>
{else}
	<div class="relatedListContainer">
		<div class="row-fluid">
			<div class="span2">
				<strong>
					{vtranslate('LBL_ARRANGE_RELATED_LIST', $QUALIFIED_MODULE)}
				</strong>
			</div>
			<div class="span10 row-fluid">
				<span class="span5">
				<ul class="relatedModulesList" style="list-style: none;">
				{foreach item=MODULE_MODEL from=$RELATED_MODULES}
					{if $MODULE_MODEL->isActive()}
					<li class="relatedModule module_{$MODULE_MODEL->getId()} border1px contentsBackground" style="width: 200px; padding: 5px;" data-relation-id="{$MODULE_MODEL->getId()}">
						<a><img src="{vimage_path('drag.png')}" title="{vtranslate('LBL_DRAG',$QUALIFIED_MODULE)}"/></a>&nbsp;&nbsp;
						<span class="moduleLabel">{vtranslate($MODULE_MODEL->get('label'), $MODULE_MODEL->getRelationModuleName())}</span>
						<button class="close" data-dismiss="modal" title="{vtranslate('LBL_CLOSE')}">x</button>
					</li>
					{/if}
				{/foreach}
				</ul>
				</span>
				<span class="span7"><i class="icon-info-sign alignMiddle"></i>&nbsp;{vtranslate('LBL_RELATED_LIST_INFO', $QUALIFIED_MODULE)}.<br><br>
					<i class="icon-info-sign alignMiddle"></i>&nbsp;{vtranslate('LBL_REMOVE_INFO', $QUALIFIED_MODULE)}.<br><br>
					<i class="icon-info-sign alignMiddle"></i>&nbsp;{vtranslate('LBL_ADD_MODULE_INFO', $QUALIFIED_MODULE)}
				</span>
			</div>
		</div>
		<div class="row-fluid">
			<div class="span2">
				<strong>
					{vtranslate('LBL_SELECT_MODULE_TO_ADD', $QUALIFIED_MODULE)}
				</strong>
			</div>
			<div class="span10">
				{assign var=ModulesList value=[]}
				{assign var=removedModuleIds value=array()}
				<ul style="list-style: none;">
					<li>
					<span class="row-fluid"><select class="select2 span3" multiple name="addToList" placeholder="{vtranslate('LBL_SELECT_MODULE', $QUALIFIED_MODULE)}">
					{foreach item=MODULE_MODEL from=$RELATED_MODULES}
						{$ModulesList[$MODULE_MODEL->getId()] = vtranslate($MODULE_MODEL->get('label'), $MODULE_MODEL->getRelationModuleName())}
						{if !$MODULE_MODEL->isActive()}
						{array_push($removedModuleIds, $MODULE_MODEL->getId())}
						<option value="{$MODULE_MODEL->getId()}">
							{vtranslate($MODULE_MODEL->get('label'), $MODULE_MODEL->getRelationModuleName())}
						</option>
						{/if}
					{/foreach}
					</select></span>
					</li>
				</ul>
				<input type="hidden" class="ModulesListArray" value='{ZEND_JSON::encode($ModulesList)}' />
				<input type="hidden" class="RemovedModulesListArray" value='{ZEND_JSON::encode($removedModuleIds)}' />
			</div>
		</div>
		<li class="moduleCopy hide border1px contentsBackground" style="width: 200px; padding: 5px;">
			<a><img src="{vimage_path('drag.png')}" title="{vtranslate('LBL_DRAG',$QUALIFIED_MODULE)}"/></a>&nbsp;&nbsp;
			<span class="moduleLabel"></span>
			<button class="close" data-dismiss="modal" title="{vtranslate('LBL_CLOSE')}">x</button>
		</li>
		<div class="row-fluid">
			<span class="pull-right">
				<button class="btn btn-success saveRelatedList" type="button" disabled="disabled"><strong>{vtranslate('LBL_SAVE', $QUALIFIED_MODULE)}</strong></button>
			</span>
		</div>
	</div>
{/if}
</div>
{/strip}