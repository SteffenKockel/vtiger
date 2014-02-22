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
{assign var="tax_count" value=1}
{foreach item=tax key=count from=$TAXCLASS_DETAILS}
	{if $tax.check_value eq 1}
		{assign var=check_value value="checked"}
		{assign var=show_value value="visible"}
	{else}
		{assign var=check_value value=""}
		{assign var=show_value value="hidden"}
	{/if}
	{if $tax_count gt 1}
	<td class="fieldLabel">
		<label class="muted pull-right marginRight10px">
	{/if}
			<span class="taxLabel alignBottom">{vtranslate($tax.taxlabel, $MODULE)}<span class="paddingLeft10px">(%)</span></span>
			<input type="checkbox" name="{$tax.check_name}" id="{$tax.check_name}" class="taxes" data-tax-name={$tax.taxname} {$check_value}>
		</label>
	</td>
	<td class="fieldValue">
		<input type="text" class="detailedViewTextBox {if $show_value eq "hidden"} hide {else} show {/if}" name="{$tax.taxname}" value="{$tax.percentage}" data-validation-engine="validate[funcCall[Vtiger_PositiveNumber_Validator_Js.invokeValidation]]" />
	</td>
	{assign var="tax_count" value=$tax_count+1}
	{if $COUNTER eq 2}
		</tr><tr>
		{assign var="COUNTER" value=1}
	{else}
		{assign var="COUNTER" value=$COUNTER+1}
	{/if}
{/foreach}
