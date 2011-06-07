<?php

require_once('include/datetime.php');
require_once('include/event.php');

function events_post(&$a) {

	if(! local_user())
		return;

	$event_id = ((x($_POST,'event_id')) ? intval($_POST['event_id']) : 0);
	$uid      = local_user();
	$startyear = intval($_POST['startyear']);
	$startmonth = intval($_POST['startmonth']);
	$startday = intval($_POST['startday']);
	$starthour = intval($_POST['starthour']);
	$startminute = intval($_POST['startminute']);

	$finishyear = intval($_POST['finishyear']);
	$finishmonth = intval($_POST['finishmonth']);
	$finishday = intval($_POST['finishday']);
	$finishhour = intval($_POST['finishhour']);
	$finishminute = intval($_POST['finishminute']);

	$adjust   = intval($_POST['adjust']);


	$start    = sprintf('%d-%d-%d %d:%d:0',$startyear,$startmonth,$startday,$starthour,$startminute);
	$finish    = sprintf('%d-%d-%d %d:%d:0',$finishyear,$finishmonth,$finishday,$finishhour,$finishminute);

	if($adjust) {
		$start = datetime_convert(date_default_timezone_get(),'UTC',$start);
		$finish = datetime_convert(date_default_timezone_get(),'UTC',$finish);
	}
	else {
		$start = datetime_convert('UTC','UTC',$start);
		$finish = datetime_convert('UTC','UTC',$finish);
	}


	$desc     = escape_tags($_POST['desc']);
	$location = escape_tags($_POST['location']);
	$type     = 'event';

	$str_group_allow   = perms2str($_POST['group_allow']);
	$str_contact_allow = perms2str($_POST['contact_allow']);
	$str_group_deny    = perms2str($_POST['group_deny']);
	$str_contact_deny  = perms2str($_POST['contact_deny']);

dbg(1);
	if($event_id) {
		$r = q("UPDATE `event` SET
			`edited` = '%s',
			`start` = '%s',
			`finish` = '%s',
			`desc` = '%s',
			`location` = '%s',
			`type` = '%s',
			`adjust` = %d,
			`allow_cid` = '%s',
			`allow_gid` = '%s',
			`deny_cid` = '%s',
			`deny_gid` = '%s'
			WHERE `id` = %d AND `uid` = %d LIMIT 1",

			dbesc(datetime_convert()),
			dbesc($start),
			dbesc($finish),
			dbesc($desc),
			dbesc($location),
			dbesc($type),
			intval($adjust),
			dbesc($str_contact_allow),
			dbesc($str_group_allow),
			dbesc($str_contact_deny),
			dbesc($str_group_deny),
			intval($event_id),
			intval($local_user())
		);

	}
	else {

		$uri = item_new_uri($a->get_hostname(),local_user());

		$r = q("INSERT INTO `event` ( `uid`,`uri`,`created`,`edited`,`start`,`finish`,`desc`,`location`,`type`,
			`adjust`,`allow_cid`,`allow_gid`,`deny_cid`,`deny_gid`)
			VALUES ( %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s' ) ",
			intval(local_user()),
			dbesc($uri),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			dbesc($start),
			dbesc($finish),
			dbesc($desc),
			dbesc($location),
			dbesc($type),
			intval($adjust),
			dbesc($str_contact_allow),
			dbesc($str_group_allow),
			dbesc($str_contact_deny),
			dbesc($str_group_deny)

		);
	}

}



function events_content(&$a) {

	if(! local_user()) {
		notice( t('Permission denied.') . EOL);
		return;
	}

	$o .= '<h2>' . t('Events') . '</h2>';

	$mode = 'view';
	$y = 0;
	$m = 0;

	if($a->argc > 1) {
		if($a->argc > 2 && $a->argv[1] == 'event') {
			$mode = 'edit';
			$event_id = intval($a->argv[2]);
		}
		if($a->argv[1] === 'new') {
			$mode = 'new';
			$event_id = 0;
		}
		if($a->argc > 2 && intval($a->argv[1]) && intval($a->argv[2])) {
			$mode = 'view';
			$y = intval($a->argv[1]);
			$m = intval($a->argv[2]);
		}
	}

	if($mode == 'view') {
	    $thisyear = datetime_convert('UTC',date_default_timezone_get(),'now','Y');
    	$thismonth = datetime_convert('UTC',date_default_timezone_get(),'now','m');
		if(! $y)
			$y = intval($thisyear);
		if(! $m)
			$m = intval($thismonth);

		$nextyear = $y;
		$nextmonth = $m + 1;
		if($nextmonth > 12) {
				$nextmonth = 1;
			$nextyear ++;
		}

		$prevyear = $y;
		if($m > 1)
			$prevmonth = $m - 1;
		else {
			$prevmonth = 12;
			$prevyear --;
		}
			

		$o .= '<a href="' . $a->get_baseurl() . '/events/' . $prevyear . '/' . $prevmonth . '" class="prevcal">' . t('&lt;&lt; Previous') . '</a> | <a href="' . $a->get_baseurl() . '/events/' . $nextyear . '/' . $nextmonth . '" class="nextcal">' . t('Next &gt;&gt;') . '</a>'; 
		$o .= cal($y,$m,false, ' eventcal');

		$dim = get_dim($y,$m);
		$start = sprintf('%d-%d-%d %d:%d:%d',$y,$m,1,0,0,0);
		$finish = sprintf('%d-%d-%d %d:%d:%d',$y,$m,$dim,23,59,59);


		$r = q("SELECT * FROM `event` WHERE `start` >= '%s' AND `finish` <= '%s' AND `uid` = %d ",
			dbesc($start),
			dbesc($finish),
			intval(local_user())
		);

		if(count($r))
			foreach($r as $rr) 
				$o .= format_event_html($rr);

		return $o;
	}

	if($mode === 'edit' || $mode === 'new') {
		$htpl = get_markup_template('profed_head.tpl');
		$a->page['htmlhead'] .= replace_macros($htpl,array('$baseurl' => $a->get_baseurl()));

		$tpl = get_markup_template('event_form.tpl');

		$year = datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y');
		$month = datetime_convert('UTC', date_default_timezone_get(), 'now', 'm');
		$day = datetime_convert('UTC', date_default_timezone_get(), 'now', 'd');

		$o .= replace_macros($tpl,array(
			'$post' => $a->get_baseurl() . '/events',
			'$e_text' => t('Event details'),
			'$s_text' => t('Start: year-month-day hour:minute'),
			'$s_dsel' => datesel('start',$year+5,$year,false,$year,$month,$day),
			'$s_tsel' => timesel('start',0,0),
			'$f_text' => t('Finish: year-month-day hour:minute'),
			'$f_dsel' => datesel('finish',$year+5,$year,false,$year,$month,$day),
			'$f_tsel' => timesel('finish',0,0),
			'$a_text' => t('Adjust for viewer timezone'),
			'$a_checked' => '',
			'$d_text' => t('Description:'),
			'$d_orig' => '',
			'$l_text' => t('Location:'),
			'$l_orig' => '',
			'$submit' => t('Submit')

		));

		return $o;
	}
}