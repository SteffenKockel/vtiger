#################################################################################################################
#																												#
#												Pack de langue Fran�ais											#
#						    						Pour vtigerCRM												#
#        					   					  Version 5.1.0 V1.4											#
#																												#
#																												#
#					Merci aux membres de la communaut� French-vtiger pour leurs contributions					#
#							Et notamment � Xavier de Sylaxe, Serge, Fran�ois et Benoit							#
#																												#
#										  																		#
#################################################################################################################

Pour installer ce pack, rendez-vous dans le gestioonnaire de modules (module manager)
Cliquez sur module personnalis� (custom module)
Puis sur Importer Cr�ation (Import)
S�lectionnez le fichier zip
Validez
D�connectez vous puis choisissez Francais dans la liste avant de vous reconnecter

Si vous souhaitez mettre le fran�ais en langue par d�fault, modifiez le fichier config.inc.php qui se trouve
� la racine de votre installation de vtiger
Il faut modifier la ligne default_language en_us par fr_fr

#########################
#     Attention : 		#
#########################

Dans le module Documents, la liste des actions � droite n'apparait plus apr�s un passage en fran�ais
Pour corriger ce probl�me, vous devez modifier dans le fichier : /modules/Documents/DetailView.php
La ligne :
if($block_entries['File Name']['value'] != '' || isset($block_entries['File Name']['value']))
Par
if($block_entries[getTranslatedString('File Name','Documents')]['value'] !='' || 
	isset($block_entries[getTranslatedString('File Name','Documents')]['value'])) 

Dans le module Rapports
La liste de choix pour les filtres contenant entre autre �gal �, sup�rieur �, ...
Pour remplacer le None par aucun, il faut modifier une ligne cod�e en dur
Dans /modules/Reports/reports.js
	Line 60: 		selObj.options[0] = new Option ('None', '');
	Line 84: 		selObj.options[0] = new Option ('None', '');
	Line 748: 		selObj.options[0] = new Option ('None', '');
	Line 774: 		selObj.options[0] = new Option ('None', '');
A remplacer par :
	Line 60: 		selObj.options[0] = new Option ('Aucun', '');
	Line 84: 		selObj.options[0] = new Option ('Aucun', '');
	Line 748: 		selObj.options[0] = new Option ('Aucun', '');
	Line 774: 		selObj.options[0] = new Option ('Aucun', '');