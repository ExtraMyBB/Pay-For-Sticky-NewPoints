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

/*
 * Checks for expired threads and make them unsticky.
 */
function task_newpoints_payforsticky_task($task) 
{
	global $db, $mybb;
	
	// Searching for expired sticky threads
	$query = $db->simple_select(
		'newpoints_payforsticky',				// Table
		'pid, tid', 							// Columns selected
		'expire > 0 AND expire < ' . TIME_NOW, 	// Where clause
        array(									// Sort entries
			'order_by' => 'date', 
			'order_dir' => 'ASC', 
			'limit' => 25
		)
	);
	
	// Ids will be used to make threads unsticky.
    $ids = array();
    $pids = array();
    $i = 0;
    while ($row = $db->fetch_array($query)) {
        $pids[] = $row['pid'];
        $ids[] = $row['tid'];
        $i++;
    }
    
	// Is there something to be deleted?
    if ($i > 0) {
        $db->delete_query(
			'newpoints_payforsticky', 			// Table
			'pid IN(' . @implode(',', $pids) . ')' // Where clause
		);
        
		// Unstick all threads founded
        require_once MYBB_ROOT . "/inc/class_moderation.php";
        $moderation = new Moderation();
        $moderation->unstick_threads($ids);
    }
	
	// Task ran successfully
	add_task_log($task, 'The Pay For Sticky task successfully ran.');
}
?>
