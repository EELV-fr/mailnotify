<?php

	

	$cloud_root = dirname(dirname(dirname(dirname(__FILE__))));

	include_once $cloud_root.'/lib/base.php';
	include 'mailing.php';

	OC_MailNotify_Mailing::db_notify_group_members();

	

?>