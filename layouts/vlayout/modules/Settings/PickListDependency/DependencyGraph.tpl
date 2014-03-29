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
    <div class="accordion">
        <span><i class="icon-info-sign alignMiddle"></i>&nbsp;{vtranslate('LBL_CONFIGURE_DEPENDENCY_INFO', $QUALIFIED_MODULE)}&nbsp;&nbsp;</span>
        <a class="cursorPointer accordion-heading accordion-toggle" data-toggle="collapse" data-target="#dependencyHelp">{vtranslate('LBL_MORE', $QUALIFIED_MODULE)}..</a>
        <div id="dependencyHelp" class="accordion-body collapse">
            <ul><br><li>{vtranslate('LBL_CONFIGURE_DEPENDENCY_HELP_1', $QUALIFIED_MODULE)}</li><br>
                <li>{vtranslate('LBL_CONFIGURE_DEPENDENCY_HELP_2', $QUALIFIED_MODULE)}</li><br>
                <li>{vtranslate('LBL_CONFIGURE_DEPENDENCY_HELP_3', $QUALIFIED_MODULE)}&nbsp;
                    <span class="selectedCell" style="padding: 4px;">{vtranslate('Selected Values', $QUALIFIED_MODULE)}</span></li>
            </ul>
        </div>
    </div>
    <div class="row-fluid padding-bottom1per">
        <span class="btn-toolbar">
            <button class="btn sourceValues" type="button"><strong>{vtranslate('LBL_SELECT_SOURCE_VALUES', $QUALIFIED_MODULE)}</strong></button>
        </span>
    </div>
    {assign var=SELECTED_MODULE value=$RECORD_MODEL->get('sourceModule')}
    {assign var=SOURCE_FIELD value=$RECORD_MODEL->get('sourcefield')}
    {assign var=MAPPED_SOURCE_PICKLIST_VALUES value=array()}
    {assign var=MAPPED_TARGET_PICKLIST_VALUES value=[]}
    {foreach item=MAPPING from=$MAPPED_VALUES}
        {assign var=value value=array_push($MAPPED_SOURCE_PICKLIST_VALUES, $MAPPING['sourcevalue'])}
        {$MAPPED_TARGET_PICKLIST_VALUES[$MAPPING['sourcevalue']] = $MAPPING['targetvalues']}
    {/foreach}
    <input type="hidden" class="allSourceValues" value='{Vtiger_Util_Helper::toSafeHTML(ZEND_JSON::encode($SOURCE_PICKLIST_VALUES))}' />

    <div class="row-fluid" style="text-align:center">
        <div class="span2">
            <table class="table-bordered table-condensed themeTableColor" width="98%">
                <thead>
                    <tr class="blockHeader"><th>{$RECORD_MODEL->getSourceFieldLabel()}</th></tr>
                </thead>
                <tbody>
                    {foreach item=TARGET_VALUE from=$TARGET_PICKLIST_VALUES name=targetValuesLoop}
                        <tr>
                            {if $smarty.foreach.targetValuesLoop.index eq 0}
                                <td style="border: 1px solid white" class="tableHeading">
                                    {$RECORD_MODEL->getTargetFieldLabel()}</td></tr>
                                {else}
                        <td style="border: 1px solid white">&nbsp;&nbsp;</td></tr>
                    {/if}
                {/foreach}
                </tbody>
            </table>
        </div>
        <div class="span10 marginLeftZero dependencyMapping">
            <table class="table-bordered table-condensed themeTableColor pickListDependencyTable">
                <thead><tr class="blockHeader">
                        {foreach item=SOURCE_PICKLIST_VALUE from=$SOURCE_PICKLIST_VALUES}
                            <th data-source-value="{Vtiger_Util_Helper::toSafeHTML($SOURCE_PICKLIST_VALUE)}" style="border: none;
                            {if !empty($MAPPED_VALUES) && !in_array($SOURCE_PICKLIST_VALUE, array_map('decode_html', $MAPPED_SOURCE_PICKLIST_VALUES))}display: none;{/if}">
                            {vtranslate($SOURCE_PICKLIST_VALUE, $SELECTED_MODULE)}</th>
                    {/foreach}</tr>
            </thead>
            <tbody>
                {foreach key=TARGET_INDEX item=TARGET_VALUE from=$TARGET_PICKLIST_VALUES name=targetValuesLoop}
                    <tr>
                        {foreach item=SOURCE_PICKLIST_VALUE from=$SOURCE_PICKLIST_VALUES}
                            {assign var=targetValues value=$MAPPED_TARGET_PICKLIST_VALUES[Vtiger_Util_Helper::toSafeHTML($SOURCE_PICKLIST_VALUE)]}

                            {assign var=SOURCE_INDEX value=$smarty.foreach.mappingIndex.index}
                            {assign var=IS_SELECTED value=false}

                            {if empty($targetValues) || in_array($TARGET_VALUE, array_map('decode_html',$targetValues))}
                                {assign var=IS_SELECTED value=true}
                            {/if}
                            <td	data-source-value='{Vtiger_Util_Helper::toSafeHTML($SOURCE_PICKLIST_VALUE)}' data-target-value='{Vtiger_Util_Helper::toSafeHTML($TARGET_VALUE)}'
                                class="{if $IS_SELECTED}selectedCell {else}unselectedCell {/if} targetValue picklistValueMapping cursorPointer"
                            {if !empty($MAPPED_VALUES) && !in_array($SOURCE_PICKLIST_VALUE, array_map('decode_html', $MAPPED_SOURCE_PICKLIST_VALUES))}style="display: none;" {/if}>
                            {if $IS_SELECTED}
                                <i class="icon-ok pull-left"></i>
                            {/if}
                            {vtranslate($TARGET_VALUE, $SELECTED_MODULE)}
                        </td>
                    {/foreach}
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
</div>
<div class="modal sourcePicklistValuesModal modalCloneCopy hide">
    <div class="modal-header contentsBackground">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h3>{vtranslate('LBL_SELECT_SOURCE_PICKLIST_VALUES', $QUALIFIED_MODULE)}</h3>
    </div>
    <div class="modal-body">
        <table class="row-fluid" cellspacing="0" cellpadding="5">
            <tr>
                {foreach key=SOURCE_INDEX item=SOURCE_VALUE from=$SOURCE_PICKLIST_VALUES name=sourceValuesLoop}
                    {if $smarty.foreach.sourceValuesLoop.index % 3 == 0}
                    </tr><tr>
                    {/if}
                    <td>
                        <div class="control-group">
                            <div class="controls row-fluid">
                                <label class="checkbox"><input type="checkbox" class="sourceValue {Vtiger_Util_Helper::toSafeHTML($SOURCE_VALUE)}"
                                                               data-source-value="{Vtiger_Util_Helper::toSafeHTML($SOURCE_VALUE)}" value="{Vtiger_Util_Helper::toSafeHTML($SOURCE_VALUE)}" 
                                    {if empty($MAPPED_VALUES) || in_array($SOURCE_VALUE, array_map('decode_html', $MAPPED_SOURCE_PICKLIST_VALUES))} checked {/if}/>
                                &nbsp;{vtranslate($SOURCE_VALUE, $SELECTED_MODULE)}</label>
                        </div>
                    </div>
                </td>
            {/foreach}
        </tr>
    </table>
</div>
{include file='ModalFooter.tpl'|@vtemplate_path:'Vtiger'}
</div>
<div class="row-fluid padding1per">
    <span class="btn-toolbar">
        <button class="btn btn-success" type="submit"><strong>{vtranslate('LBL_SAVE', $QUALIFIED_MODULE)}</strong></button>
        <a type="reset" class="cancelLink cancelDependency" title="{vtranslate('LBL_CANCEL', $QUALIFIED_MODULE)}">{vtranslate('LBL_CANCEL', $QUALIFIED_MODULE)}</a>
    </span>
</div>
{/strip}
