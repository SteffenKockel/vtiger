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
<div class="SendEmailFormStep2" id="composeEmailContainer">
	<div style='padding:10px 0;'>
		<h3>{vtranslate('LBL_COMPOSE_EMAIL', $MODULE)}</h3>
		<hr style='margin:5px 0;width:100%'>
	</div>
	<form class="form-horizontal" id="massEmailForm" method="post" action="index.php" enctype="multipart/form-data" name="massEmailForm">
		<input type="hidden" name="selected_ids" value='{ZEND_JSON::encode($SELECTED_IDS)}' />
		<input type="hidden" name="excluded_ids" value='{ZEND_JSON::encode($EXCLUDED_IDS)}' />
		<input type="hidden" name="viewname" value="{$VIEWNAME}" />
		<input type="hidden" name="module" value="{$MODULE}"/>
		<input type="hidden" name="mode" value="massSave" />
		<input type="hidden" name="toemailinfo" value='{ZEND_JSON::encode($TOMAIL_INFO)}' />
		<input type="hidden" name="view" value="MassSaveAjax" />
		<input type="hidden"  name="to" value='{ZEND_JSON::encode($TO)}' />
		<input type="hidden" id="flag" name="flag" value="" />
		<input type="hidden" id="maxUploadSize" value="{$MAX_UPLOAD_SIZE}" />
		<input type="hidden" id="documentIds" name="documentids" value="" />
		<input type="hidden" name="emailMode" value="{$EMAIL_MODE}" />
        {if !empty($PARENT_EMAIL_ID)}
            <input type="hidden" name="parent_id" value="{$PARENT_EMAIL_ID}" />
			<input type="hidden" name="parent_record_id" value="{$PARENT_RECORD}" />
        {/if}
        {if !empty($RECORDID)}
            <input type="hidden" name="record" value="{$RECORDID}" />
        {/if}
        <input type="hidden" name="search_key" value= "{$SEARCH_KEY}" />
        <input type="hidden" name="operator" value="{$OPERATOR}" />
        <input type="hidden" name="search_value" value="{$ALPHABET_VALUE}" />
		<div class="row-fluid padding-bottom1per toEmailField">
			<span class="span8 row-fluid">
				<span class="span2">{vtranslate('LBL_TO',$MODULE)}<span class="redColor">*</span></span>
				{if !empty($TO)}
					{assign var=TO_EMAILS value=","|implode:$TO}
				{/if}
				<input data-validation-engine='validate[required]' class="span9 sourceField" type="text" value="{$TO_EMAILS}" readonly />
			</span>
			<span class="span4">
				<span class="row-fluid">
					<span class="span10">
						<div class="input-prepend">
							<span class="pull-right">
								<span class="add-on cursorPointer" name="clearToEmailField"><i class="icon-remove-sign" title="{vtranslate('LBL_CLEAR', $MODULE)}"></i></span>
								<select class="chzn-select emailModulesList" style="width:150px;">
									<optgroup>
										{foreach item=MODULE_NAME from=$RELATED_MODULES}
											<option value="{$MODULE_NAME}">{vtranslate($MODULE_NAME,$MODULE_NAME)}</option>
										{/foreach}
									</optgroup>
								</select>
							</span>
						</div>
					</span>
					<span class="input-append span2 margin0px">
							<span class="add-on selectEmail cursorPointer"><i class="icon-search" title="{vtranslate('LBL_SELECT', $MODULE)}"></i></span>
					</span>
				</span>
			</span>
		</div>
		<div class="row-fluid padding-bottom1per {if empty($CC)}hide {/if}" id="ccContainer">
			<span class="span8 row-fluid">
				<span class="span2">{vtranslate('LBL_CC',$MODULE)}</span>
				<input class="span9"  data-validation-engine="validate[funcCall[Vtiger_MultiEmails_Validator_Js.invokeValidation]]" type="text" name="cc" value="{if !empty($CC)}{$CC}{/if}"/>
			</span>
			<span class="span4"></span>
		</div>
		<div class="row-fluid padding-bottom1per {if empty($BCC)}hide {/if}" id="bccContainer">
			<span class="span8 row-fluid">
				<span class="span2">{vtranslate('LBL_BCC',$MODULE)}</span>
				<input class="span9" data-validation-engine="validate[funcCall[Vtiger_MultiEmails_Validator_Js.invokeValidation]]" type="text" name="bcc" value="{if !empty($BCC)}{$BCC}{/if}"/>
			</span>
			<span class="span4"></span>
		</div>
		<div class="row-fluid {if (!empty($CC)) and (!empty($BCC))} hide {/if}">
			<span class="span8 row-fluid">
				<span class="span2">&nbsp;</span>
				<span class="span9">
					<a class="cursorPointer {if (!empty($CC))}hide{/if}" id="ccLink">{vtranslate('LBL_ADD_CC', $MODULE)}</a>&nbsp;&nbsp;
					<a class="cursorPointer {if (!empty($BCC))}hide{/if}" id="bccLink">{vtranslate('LBL_ADD_BCC', $MODULE)}</a>
				</span>
			</span>
			<span class="span4"></span>
		</div>
		<div class="row-fluid padding-bottom1per">
			<span class="span8 row-fluid">
				<span class="span2">{vtranslate('LBL_SUBJECT',$MODULE)}<span class="redColor">*</span></span>
				<input data-validation-engine='validate[required]' class="span9" type="text" name="subject" value="{$SUBJECT}" id="subject"/>
			</span>
			<span class="span4"></span>
		</div>
		<div class="row-fluid padding-bottom1per">
			<span class="span8 row-fluid">
				<span class="span2">{vtranslate('LBL_ATTACHMENT',$MODULE)}</span>
				<span class="span9">
					<div class="row-fluid">
						<input type="file" id="multiFile" name="file[]"/>&nbsp;
						<button type="button" class="btn btn-small" id="browseCrm" data-url="{$DOCUMENTS_URL}" title="{vtranslate('LBL_BROWSE_CRM',$MODULE)}">{vtranslate('LBL_BROWSE_CRM',$MODULE)}</button>
					</div>
					<div id="attachments" class="row-fluid">
						{foreach item=ATTACHMENT from=$ATTACHMENTS}
							{if ('docid'|array_key_exists:$ATTACHMENT)}
								{assign var=DOCUMENT_ID value=$ATTACHMENT['docid']}
								{assign var=FILE_TYPE value="document"}
							{else}
								{assign var=FILE_TYPE value="file"}
							{/if}
							<div class="MultiFile-label customAttachment" data-file-id="{$ATTACHMENT['fileid']}" data-file-type="{$FILE_TYPE}"  data-file-size="{$ATTACHMENT['size']}" {if $FILE_TYPE eq "document"} data-document-id="{$DOCUMENT_ID}"{/if}>
                                {if $ATTACHMENT['nondeletable'] neq true}
                                    <a name="removeAttachment" class="cursorPointer">x </a>
                                {/if}
								<span>{$ATTACHMENT['attachment']}</span>
							</div>
						{/foreach}
					</div>
				</span>
			</span>
			<span class="span4"></span>
		</div>
		<div class="padding-bottom1per row-fluid">
			<div class="span8">
				<div class="btn-toolbar">
					<span class="btn-group">
						<button class="floatNone btn btn-success" id="sendEmail" type="submit" title="{vtranslate('LBL_SEND',$MODULE)}"><strong>{vtranslate('LBL_SEND',$MODULE)}</strong></button>&nbsp;&nbsp;
					</span>
					<span class="btn-group">
						<button type="submit" class="floatNone btn" id="saveDraft" title="{vtranslate('LBL_SAVE_AS_DRAFT',$MODULE)}"><strong>{vtranslate('LBL_SAVE_AS_DRAFT',$MODULE)}</strong></button>
					</span>
					<span class="btn-group">
						{if !empty($PARENT_EMAIL_ID)}
							<button type="button" class="floatNone btn" id="gotoPreview" title="{vtranslate('LBL_GO_TO_PREVIEW',$MODULE)}"><strong>{vtranslate('LBL_GO_TO_PREVIEW',$MODULE)}</strong></button>
						{/if}
					</span>
					<span  name="progressIndicator" style="height:30px;">&nbsp;</span>
				</div>
			</div>
			{if $MODULE_IS_ACTIVE}
				<div class="span4">
					<span class="btn-toolbar pull-right">
						<button type="button" class="btn" id="selectEmailTemplate" data-url="{$EMAIL_TEMPLATE_URL}" title="{vtranslate('LBL_SELECT_EMAIL_TEMPLATE',$MODULE)}"><strong>{vtranslate('LBL_SELECT_EMAIL_TEMPLATE',$MODULE)}</strong></button>
					</span>
				</div>
			{/if}
		</div>
        {if $RELATED_LOAD eq true}
            <input type="hidden" name="related_load" value={$RELATED_LOAD} />
        {/if}
		<textarea id="description" name="description">{$DESCRIPTION}</textarea>
		<input type="hidden" name="attachments" value='{ZEND_JSON::encode($ATTACHMENTS)}' />
	</form>
</div>

{include file='JSResources.tpl'|@vtemplate_path}
{/strip}
