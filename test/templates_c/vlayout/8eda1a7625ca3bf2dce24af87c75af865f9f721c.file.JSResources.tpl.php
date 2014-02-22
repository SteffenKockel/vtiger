<?php /* Smarty version Smarty-3.1.7, created on 2014-02-15 17:42:38
         compiled from "/var/www/6.0.0/6.0.0/includes/runtime/../../layouts/vlayout/modules/Vtiger/JSResources.tpl" */ ?>
<?php /*%%SmartyHeaderCode:75756917752ffa70e465d87-70425229%%*/if(!defined('SMARTY_DIR')) exit('no direct access allowed');
$_valid = $_smarty_tpl->decodeProperties(array (
  'file_dependency' => 
  array (
    '8eda1a7625ca3bf2dce24af87c75af865f9f721c' => 
    array (
      0 => '/var/www/6.0.0/6.0.0/includes/runtime/../../layouts/vlayout/modules/Vtiger/JSResources.tpl',
      1 => 1383547495,
      2 => 'file',
    ),
  ),
  'nocache_hash' => '75756917752ffa70e465d87-70425229',
  'function' => 
  array (
  ),
  'variables' => 
  array (
    'SCRIPTS' => 0,
    'jsModel' => 0,
    'VTIGER_VERSION' => 0,
  ),
  'has_nocache_code' => false,
  'version' => 'Smarty-3.1.7',
  'unifunc' => 'content_52ffa70e47a5c',
),false); /*/%%SmartyHeaderCode%%*/?>
<?php if ($_valid && !is_callable('content_52ffa70e47a5c')) {function content_52ffa70e47a5c($_smarty_tpl) {?>



	<script type="text/javascript" src="libraries/html5shim/html5.js"></script>

	<script type="text/javascript" src="libraries/jquery/jquery.blockUI.js"></script>
	<script type="text/javascript" src="libraries/jquery/chosen/chosen.jquery.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/select2/select2.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/jquery-ui/js/jquery-ui-1.8.16.custom.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/jquery.class.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/defunkt-jquery-pjax/jquery.pjax.js"></script>
	<script type="text/javascript" src="libraries/jquery/jstorage.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/autosize/jquery.autosize-min.js"></script>

	<script type="text/javascript" src="libraries/jquery/rochal-jQuery-slimScroll/slimScroll.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/pnotify/jquery.pnotify.min.js"></script>
	<script type="text/javascript" src="libraries/jquery/jquery.hoverIntent.minified.js"></script>

	<script type="text/javascript" src="libraries/bootstrap/js/bootstrap-alert.js"></script>
	<script type="text/javascript" src="libraries/bootstrap/js/bootstrap-tooltip.js"></script>
	<script type="text/javascript" src="libraries/bootstrap/js/bootstrap-tab.js"></script>
	<script type="text/javascript" src="libraries/bootstrap/js/bootstrap-collapse.js"></script>
	<script type="text/javascript" src="libraries/bootstrap/js/bootstrap-modal.js"></script>
	<script type="text/javascript" src="libraries/bootstrap/js/bootstrap-dropdown.js"></script>
	<script type="text/javascript" src="libraries/bootstrap/js/bootstrap-popover.js"></script>
	<script type="text/javascript" src="libraries/bootstrap/js/bootbox.min.js"></script>
	<script type="text/javascript" src="resources/jquery.additions.js"></script>
	<script type="text/javascript" src="resources/app.js"></script>
	<script type="text/javascript" src="resources/helper.js"></script>
	<script type="text/javascript" src="resources/Connector.js"></script>
	<script type="text/javascript" src="resources/ProgressIndicator.js" ></script>
	<script type="text/javascript" src="libraries/jquery/posabsolute-jQuery-Validation-Engine/js/jquery.validationEngine.js" ></script>
	<script type="text/javascript" src="libraries/guidersjs/guiders-1.2.6.js"></script>
	<script type="text/javascript" src="libraries/jquery/datepicker/js/datepicker.js"></script>
	<script type="text/javascript" src="libraries/jquery/dangrossman-bootstrap-daterangepicker/date.js"></script>
	<script type="text/javascript" src="libraries/jquery/jquery.ba-outside-events.min.js"></script>

	<?php  $_smarty_tpl->tpl_vars['jsModel'] = new Smarty_Variable; $_smarty_tpl->tpl_vars['jsModel']->_loop = false;
 $_smarty_tpl->tpl_vars['index'] = new Smarty_Variable;
 $_from = $_smarty_tpl->tpl_vars['SCRIPTS']->value; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array');}
foreach ($_from as $_smarty_tpl->tpl_vars['jsModel']->key => $_smarty_tpl->tpl_vars['jsModel']->value){
$_smarty_tpl->tpl_vars['jsModel']->_loop = true;
 $_smarty_tpl->tpl_vars['index']->value = $_smarty_tpl->tpl_vars['jsModel']->key;
?>
		<script type="<?php echo $_smarty_tpl->tpl_vars['jsModel']->value->getType();?>
" src="<?php echo $_smarty_tpl->tpl_vars['jsModel']->value->getSrc();?>
?&v=<?php echo $_smarty_tpl->tpl_vars['VTIGER_VERSION']->value;?>
"></script>
	<?php } ?>

	<!-- Added in the end since it should be after less file loaded -->
	<script type="text/javascript" src="libraries/bootstrap/js/less.min.js"></script><?php }} ?>