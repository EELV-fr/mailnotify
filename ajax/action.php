<?php
OCP\JSON::callCheck();
$action = '';
$action_gid = '';
if(isset($_POST['action']) && isset($_POST['action_gid']) ){
	$action = $_POST['action'];
	$action_gid = $_POST['action_gid'];

	if($action == 'getuser'){
		echo OCP\User::getUser();
		exit();
	}
	if($action == 'isDisabled' and $action_gid != ''){
		$user = OCP\User::getUser();
		echo OC_MailNotify_Mailing::db_is_disabled_for_group($user, $action_gid);
		exit();
	}

	if($action == 'remove' and $action_gid != ''){
		$user = OCP\User::getUser();
		echo OC_MailNotify_Mailing::db_remove_user_setting($user, $action_gid);
		exit();
	}
	if($action == 'add' and $action_gid != ''){
		$user = OCP\User::getUser();
		echo OC_MailNotify_Mailing::db_add_user_setting($user, $action_gid);
		exit();		
	}
	if($action == 'isGroup' and $action_gid != ''){		
		echo OC_Group::groupExists($action_gid);
		exit();	
	}
}
echo '0';
exit();