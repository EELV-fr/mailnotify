<?php

	

	$cloud_root = dirname(dirname(dirname(dirname(__FILE__))));

	include_once $cloud_root.'/lib/base.php';
	include 'mailing.php';
	OC_MailNotify_Mailing::do_notification_queue();
	
//

//TODO owncloud's cron jobs methode
/*
static OC_BackgroundJob_QueuedTask::add	(	 	$app,
 	$klass,
 	$method,
 	$parameters 
)		
static
queues a task

Parameters
$app	app name
$klass	class name
$method	method name
$parameters	all useful data as text
Returns
id of task
 * 
 * 
 */
