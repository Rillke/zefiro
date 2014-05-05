<?php
// last known update: 2014-02-04

require_once 'zefiro/ini.php';
require_once 'zefiro/lib/opendb_mysql.php';
require_once 'zefiro/lib/url.php';

$dbi->requireUserPermission ('system');

// url parameters
$item_id = getUrlParameter('helptext_id',0);
$user_confirm = getUrlParameter('confirm',NULL);

// database connection
openDB ($db_host,$db_user,$db_pass,$db_name);

if ($item_query = mysql_query ('SELECT * FROM z_textblocks WHERE textblock_id='.$item_id)) {
	$item = mysql_fetch_object($item_query);
	switch ($user_confirm) {
		
		case 'yes':
			// deletion confirmed
			mysql_query ('DELETE FROM z_textblocks WHERE textblock_id='.$item_id);
			header('Location: z_list_helptexts');
			break;
		
		case 'no':
			// deletion cancelled
			header('Location: z_list_helptexts');
			break;
		
		default:
			break;
	}
}

$dbi->addBreadcrumb (Z_ADMIN,'z_menu_admin');
$dbi->addBreadcrumb (Z_TEXTBLOCKS,'z_list_helptexts');

$layout
	->set('title',Z_REMOVE_TEXTBLOCK)
	->set('content',
		buildElement('p',Z_REMOVE_TEXTBLOCK.': "<em>'.$item->name.'</em>". '.Z_ARE_YOU_SURE).
		buildElement('div','buttons',
			createButton (Z_NO_CANCEL,'z_remove_helptext?helptext_id='.$item_id.'&confirm=no','icon no').
			createButton (Z_YES_CONTINUE,'z_remove_helptext?helptext_id='.$item_id.'&confirm=yes','icon yes')
		)
	)
	->cast();

?>