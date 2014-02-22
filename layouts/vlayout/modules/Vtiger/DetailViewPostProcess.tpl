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
	{assign var="MODULE_NAME" value=$MODULE_MODEL->get('name')}

					</div>
				</form>
			</div>
			<div class="related span2 marginLeftZero">
				<div class="">
					<ul class="nav nav-stacked nav-pills">
						{foreach item=RELATED_LINK from=$DETAILVIEW_LINKS['DETAILVIEWTAB']}
						<li class="{if $RELATED_LINK->getLabel()==$SELECTED_TAB_LABEL}active{/if}" data-url="{$RELATED_LINK->getUrl()}&tab_label={$RELATED_LINK->getLabel()}" data-label-key="{$RELATED_LINK->getLabel()}" data-link-key="{$RELATED_LINK->get('linkKey')}" >
							<a href="javascript:void(0);" class="textOverflowEllipsis" style="width:auto" title="{vtranslate($RELATED_LINK->getLabel(),{$MODULE_NAME})}"><strong>{vtranslate($RELATED_LINK->getLabel(),{$MODULE_NAME})}</strong></a>
						</li>
						{/foreach}
						{foreach item=RELATED_LINK from=$DETAILVIEW_LINKS['DETAILVIEWRELATED']}
						<li class="{if $RELATED_LINK->getLabel()==$SELECTED_TAB_LABEL}active{/if}" data-url="{$RELATED_LINK->getUrl()}&tab_label={$RELATED_LINK->getLabel()}" data-label-key="{$RELATED_LINK->getLabel()}" >
							{* Assuming most of the related link label would be module name - we perform dual translation *}
							{assign var="DETAILVIEWRELATEDLINKLBL" value= vtranslate($RELATED_LINK->getLabel(), $RELATED_LINK->getLabel())}
							<a href="javascript:void(0);" class="textOverflowEllipsis" style="width:auto" title="{vtranslate($RELATED_LINK->getLabel(),{$MODULE_NAME})}"><strong>{$DETAILVIEWRELATEDLINKLBL}</strong></a>
						</li>
						{/foreach}
					</ul>
				</div>
			</div>
		</div>
	</div>
	</div>
</div>
</div>
{/strip}