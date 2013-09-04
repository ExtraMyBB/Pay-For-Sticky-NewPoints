<?php
/*
 * -PLUGIN-----------------------------------
 * 	Name 		: Pay For Sticky
 * 	Version 	: 1.3
 
 * -TEAM-------------------------------------
 * 	Developers	: Baltzatu, Mihu
 
 * -LICENSE----------------------------------
 *	Copyright (C) 2013  ExtraMyBB.com. All rights reserved.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.

 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if( ! defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/*
 * Returns some infos about plugin.
 */
function newpoints_payforsticky_info()
{
	return array(
		"name" => "Pay For Sticky",
		"description" => "Allows users to pay for make some threads sticky from certain forums.",
		"website" => "http://extramybb.com",
		"author" => "ExtraMyBB DevTech",
		"authorsite" => "http://extramybb.com",
		"version" => "1.3",
		"compatibility" => "1*"
	);
}

/*
 * Installation requires some database and template changes.
 */
function newpoints_payforsticky_install()
{
	global $db;
    
	$db->write_query("CREATE TABLE `" . TABLE_PREFIX . "newpoints_payforsticky` (
        `pid` int(12) UNSIGNED NOT NULL auto_increment,
	    `tid` bigint(30) unsigned NOT NULL default '0',
	    `date` bigint(30) unsigned NOT NULL default '0',
        `expire` bigint(30) unsigned NOT NULL default '0',
        PRIMARY KEY  (`pid`)) ENGINE=MyISAM
    ");
   
	// Needed for using "find_replace_templatesets" function
   	require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
	
	// Create a new template
	$template = array(
		"title" => "payforsticky_button",
		"template" => '<span style="float: right;"><a href="showthread.php?action=payforsticky&tid={$thread[\\\'tid\\\']}&my_post_key={$mybb->post_code}"><img src="images/payforsticky.png" title="Pay for sticky"></a></span>',
		"sid" => -1
	);
	$db->insert_query("templates", $template);

	// Insert some strings into MyBB default templates
    find_replace_templatesets('showthread', "#" . preg_quote('{$ratethread}') . "#i", '{\$ratethread}{\$payforsticky}');
    find_replace_templatesets('newthread', "#" . preg_quote('{$disablesmilies}') . "#i", '{\$disablesmilies}{\$pfscheckbox}');

	// Create a new task
    $task = array (
        "title" => "Pay For Sticky",
        "description" => "This task work everyday and it is a part of Pay For Sticky plugin for NewPoints.",  
        "file" => "newpoints_payforsticky_task",
        "minute" => "15",
        "hour" => "*",
        "day" => "*",
        "month" => "*",
        "weekday" => "*",
        "nextrun" => 0,
        "lastrun" => 0,
        "enabled" => 0,
        "logging" => 1,
        "locked" => 0,
    );
	$db->insert_query('tasks', $task);
}

/*
 * Checks plugin installation status.
 */
function newpoints_payforsticky_is_installed()
{
	global $db;
    
	return ($db->table_exists('newpoints_payforsticky')) ? TRUE : FALSE;
}

/*
 * Uninstall process requires a rollback of installation steps.
 */
function newpoints_payforsticky_uninstall()
{
	global $db;

	// Remove all tables created
	if($db->table_exists('newpoints_payforsticky')) {
		$db->drop_table('newpoints_payforsticky');
	}
    
	// Needed for using "find_replace_templatesets" function
    require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
    
	// Remove all templates insert by us
	newpoints_remove_templates("'payforsticky_button'");
    
	// Remove all strings added by us from Template System
    find_replace_templatesets("showthread", "#" . preg_quote('{$payforsticky}') . "#i", '', 0);
    find_replace_templatesets('newthread', "#" . preg_quote('{$pfscheckbox}') . "#i", '', 0);
	
	// Remove all log entries related with "Pay For Sticky"
	newpoints_remove_log(array('payforsticky'));
	
	$db->delete_query('tasks', 'file = "newpoints_payforsticky_task"');
}

/*
 * Activate step for plugin.
 */
function newpoints_payforsticky_activate()
{
	global $db, $mybb;
    
	// Enable task to run periodically
    $db->update_query('tasks', array('enabled' => 1), "file = 'newpoints_payforsticky_task'");
	
	// Create new settings to configure this plugin
	newpoints_add_setting(
		'newpoints_payforsticky_forums', 
		'newpoints_payforsticky', 
		'Forums', 
		'Forum id\'s of the forums which are affected by this plugin. Please separate two or more forums using comma.', 
		'text', 
		'', 
		1
	);
	newpoints_add_setting(
		'newpoints_payforsticky_groups',
		'newpoints_payforsticky',
		'Groups',
		'Groups of users who cannot pay for stick a thread. Please separate two or more ids using comma.', 'text', '', 
		2
	);
    newpoints_add_setting(
		'newpoints_payforsticky_fee',
		'newpoints_payforsticky',
		'Fee',
		'Amount of money users need to pay to mark a thread as sticky. (Default : 10)', 
		'text', 
		'10', 
		3
	);
    newpoints_add_setting(
		'newpoints_payforsticky_time', 
		'newpoints_payforsticky', 
		'Amount of days', 
		'Enter the amount of days threads buyed remain sticky. Set to 0 if unlimited. (Default : 30)', 
		'text', 
		'30', 
		4
	);
    newpoints_add_setting(
		'newpoints_payforsticky_own', 
		'newpoints_payforsticky', 
		'Buy only own threads?', 
		'A user can buy only his own threads? (Default : Yes)', 
		'yesno', 
		1, // 1 = Yes, 0 = No
		5);

	// Rebuild is required for appearing all settings added above
    rebuild_settings();
}

/*
 * Deactivate step for plugin.
 */
function newpoints_payforsticky_deactivate()
{
	global $db, $mybb;
    
	// Disable plugin task
    $db->update_query(
		'tasks', 
		array('enabled' => 0), 
		"file = 'newpoints_payforsticky_task'"
	);
	
	// Remove all settings added by plugin
	newpoints_remove_settings("
		'newpoints_payforsticky_forums',
		'newpoints_payforsticky_groups',
		'newpoints_payforsticky_time',
		'newpoints_payforsticky_fee',
		'newpoints_payforsticky_own'
	");
    
	// Refresh settings
	rebuild_settings();
}

$plugins->add_hook('showthread_start', 'newpoints_payforsticky_button');
/*
 * Show "Pay for sticky" button.
 */
function newpoints_payforsticky_button()
{
	global $mybb, $thread, $fid, $templates, $payforsticky;
    
	// @ before function call will silence any php errors raised by "explode" function
	$forums = @explode(",", $mybb->settings['newpoints_payforsticky_forums']);
    $groups = @explode(",", $mybb->settings['newpoints_payforsticky_groups']);
    $owner = ($mybb->settings['newpoints_payforsticky_own'] == 0) ? TRUE : FALSE;
	$payforsticky = '';
	if (in_array($fid, $forums) && ! $thread['sticky'] && 
        ! in_array($mybb->user['usergroup'], $groups) && 
		($owner || $mybb->user['uid'] == $thread['uid'])) {
	   eval("\$payforsticky = \"" . $templates->get('payforsticky_button') . "\";");
	}
}

$plugins->add_hook('newthread_start', 'newpoints_payforsticky_checkbox');
/*
 * Add a new option into newthread form.
 */
function newpoints_payforsticky_checkbox()
{
    global $fid, $mybb, $lang, $pfscheckbox;
    
	// @ before function call will silence any php errors raised by "explode" function
    $forums = @explode(",", $mybb->settings['newpoints_payforsticky_forums']);
	$pfscheckbox = '';
    if (in_array($fid, $forums) && 
		! newpoints_check_permissions($mybb->settings['newpoints_payforsticky_groups']))
	{
		// Lazy language load
        newpoints_lang_load('newpoints_payforsticky');
    
        $text = $lang->sprintf(
			$lang->newpoints_payforsticky_checkbox, 
            newpoints_format_points((float)$mybb->settings['newpoints_payforsticky_fee'])
		);
        $pfscheckbox = '<br/><label><input type="checkbox" class="checkbox" name="payforsticky" value="1" tabindex="7"/> ' . $text .'</label>';
    }
}

$plugins->add_hook('newthread_do_newthread_start', 'newpoints_payforsticky_docheck_start');
/*
 * Will be the new thread created marked as sticky?
 */
function newpoints_payforsticky_docheck_start()
{
    global $db, $mybb, $forum, $lang, $thread;
    
	// @ before function call will silence any php errors raised by "explode" function
    $forums = @explode(",", $mybb->settings['newpoints_payforsticky_forums']);
	$thread['stickypaid'] = FALSE;
	if (in_array($forum['fid'], $forums) && 
			! newpoints_check_permissions($mybb->settings['newpoints_payforsticky_groups']) && 
			(int)$mybb->input['payforsticky'] == 1 && 
			$mybb->input['modoptions']['stickthread'] != 1) 
    {
		// Not enough points?
        if ((float)($mybb->user['newpoints']) < (float)($mybb->settings['newpoints_payforsticky_fee'])) {
            error($lang->sprintf(
				$lang->newpoints_payforsticky_no_points, 
                newpoints_format_points((float)$mybb->settings['newpoints_payforsticky_fee']))
			);
        } else {
            $thread['stickypaid'] = TRUE;
        }
	}
}

$plugins->add_hook('newthread_do_newthread_end', 'newpoints_payforsticky_docheck_end');
/*
 * Makes thread sticky immediately after creation.
 */
function newpoints_payforsticky_docheck_end()
{
    global $mybb, $db, $lang, $tid, $thread;

	// Stick thread only if it is necessary...
    if (isset($thread['stickypaid']) && $thread['stickypaid'] ===  TRUE)
    {
        require_once MYBB_ROOT . "/inc/class_moderation.php";
        
        newpoints_lang_load('newpoints_payforsticky');
        
		// Take points from user
        newpoints_addpoints(
			$mybb->user['uid'], 
			-((float)$mybb->settings['newpoints_payforsticky_fee'])
		);
        
		// Make sticky for a period of time
        $insert_array = array(
            'tid' => $tid,
            'date' => TIME_NOW,
            'expire' => TIME_NOW + 86400 * ((int)$mybb->settings['newpoints_payforsticky_time'])
        );
        $db->insert_query(
			'newpoints_payforsticky', 
			$insert_array
		);
        
		// Do thread sticky immediately...
        $moderation = new Moderation();
        $moderation->stick_threads($tid);

		// Create a new log entry
        newpoints_log(
			'payforsticky', 
			$lang->sprintf($lang->newpoints_payforsticky_log, 
            $tid, $mybb->user['uid'])
		);
    }
}


$plugins->add_hook('showthread_start', 'newpoints_payforsticky_showthread');
/*
 * Buy sticky option for a new thread.
 */
function newpoints_payforsticky_showthread()
{
	global $mybb, $db, $lang, $thread;
    
	// Run function only for some actions
    if ($mybb->input['action'] != 'payforsticky' && 
            $mybb->input['action'] != 'do_payforsticky') {
        return;
    }
  
	// Form sent by human?
    verify_post_check($mybb->input['my_post_key']);
    
	// Load plugin language
    newpoints_lang_load('newpoints_payforsticky');
    
	// Necessary for using "Moderation" class
    require_once MYBB_ROOT . "/inc/class_moderation.php";
    
	// Check group & forum rules defined by administrator
    $forums = @explode(",", $mybb->settings['newpoints_payforsticky_forums']);
    $groups = @explode(",", $mybb->settings['newpoints_payforsticky_groups']);
    $owner = ($mybb->settings['newpoints_payforsticky_own'] == 0) ? TRUE : FALSE;
	if ($thread && ! $thread['sticky'] && in_array($thread['fid'], $forums) && 
		! newpoints_check_permissions($mybb->settings['newpoints_payforsticky_groups']) && 
		($owner || $mybb->user['uid'] == $thread['uid'])) 
    {
        if ($mybb->request_method == "post")
		{		
			// Takes points from user
			newpoints_addpoints(
				$mybb->user['uid'], 
				-((float)$mybb->settings['newpoints_payforsticky_fee'])
			);
            
			// Make sticky for a period of time
			$insert_array = array(
				'tid' => $thread['tid'],
				'date' => TIME_NOW,
                'expire' => TIME_NOW + 86400 * ((int)$mybb->settings['newpoints_payforsticky_time'])
			);
			$db->insert_query(
				'newpoints_payforsticky', 
				$insert_array
			);
            
			// Do thread sticky immediately...
            $moderation = new Moderation();
            $moderation->stick_threads($thread['tid']);
			
			// Create a new log entry
			newpoints_log(
				'payforsticky', 
				$lang->sprintf(
					$lang->newpoints_payforsticky_log, 
					$thread['tid'], 
					$mybb->user['uid']
				)
			);
            
			// Redirect user
            redirect('showthread.php?tid=' . $thread['tid'], $lang->newpoints_payforsticky_success);
		} else 
		{
			// Not enough points?
			if ((float)$mybb->user['newpoints'] < (float)$mybb->settings['newpoints_payforsticky_fee']) {
				error(
					$lang->sprintf(
						$lang->newpoints_payforsticky_no_points, 
						newpoints_format_points((float)$mybb->settings['newpoints_payforsticky_fee'])
					)
				);
			} else {
                $time = (int)$mybb->settings['newpoints_payforsticky_time'];
                if ($time == 0) {
                    $time = $lang->newpoints_payforsticky_unlimited;
                }
			
				$page = $lang->sprintf(
					$lang->newpoints_payforsticky_notice, 
                    newpoints_format_points((float)$mybb->settings['newpoints_payforsticky_fee']), 
                    $time
				);
                
				// Confirm payment action!
                $tid = $thread['tid'];
                $page .= "<br/><form action=\"showthread.php?tid={$tid}&my_post_key={$mybb->post_code}\" method=\"post\">";
				$page .= "<input type=\"hidden\" name=\"action\" value=\"do_payforsticky\">\n";			
				$page .= "<center><input type=\"submit\" value=\"{$lang->newpoints_payforsticky_confirm}\"></center>\n";
				$page .= "</form>";
			
				error($page, $lang->newpoints_payforsticky_verify_payment);
			}
		}
	} else {
		// An unknown error occurred!
        error($lang->newpoints_payforsticky_invalid);
    }
}

?>