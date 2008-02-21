<?php /* $Id$ $URL$ */
if (!defined('W2P_BASE_DIR')) {
	die('You should not call this file directly.');
}
$AppUI->savePlace();
$sort = w2PgetParam($_REQUEST, 'sort', 'asc');
$forum_id = w2PgetParam($_REQUEST, 'forum_id', 0);
$message_id = w2PgetParam($_REQUEST, 'message_id', 0);
$perms = &$AppUI->acl();

if (!$perms->checkModuleItem('forums', 'view', $message_id)) {
	$AppUI->redirect('m=public&a=access_denied');
}

$q = new DBQuery;
$q->addTable('forums');
$q->addTable('forum_messages');
$q->addQuery('forum_messages.*,	contact_first_name, contact_last_name, contact_email, user_username, forum_moderated, visit_user');
$q->addJoin('forum_visits', 'v', 'visit_user = ' . (int)$AppUI->user_id . ' AND visit_forum = ' . (int)$forum_id . ' AND visit_message = forum_messages.message_id');
$q->addJoin('users', 'u', 'message_author = u.user_id', 'inner');
$q->addJoin('contacts', 'con', 'contact_id = user_contact', 'inner');
$q->addWhere('forum_id = message_forum AND (message_id = ' . (int)$message_id . ' OR message_parent = ' . (int)$message_id . ')');
if (w2PgetConfig('forum_descendent_order') || w2PgetParam($_REQUEST, 'sort', 0)) {
	$q->addOrder('message_date ' . $sort);
}

$messages = $q->loadList();

$x = false;

$date = new CDate();
$pdfdata = array();
$pdfhead = array('Date', 'User', 'Message');

$new_messages = array();

foreach ($messages as $row) {
	// Find the parent message - the topic.
	if ($row['message_id'] == $message_id)
		$topic = $row['message_title'];

	$q = new DBQuery;
	$q->addTable('forum_messages');
	$q->addTable('users');
	$q->addQuery('DISTINCT contact_email, contact_first_name, contact_last_name, user_username');
	$q->addJoin('contacts', 'con', 'contact_id = user_contact', 'inner');
	$q->addWhere('users.user_id = ' . (int)$row['message_editor']);
	$editor = $q->loadList();

	$date = intval($row['message_date']) ? new CDate($row['message_date']) : null;

	$pdfdata[] = array($row['message_date'], $row['contact_first_name'] . ' ' . $row['contact_last_name'], '<b>' . $row['message_title'] . '</b>' . $row['message_body']);
}

$font_dir = W2P_BASE_DIR . '/lib/ezpdf/fonts';
$temp_dir = W2P_BASE_DIR . '/files/temp';
require ($AppUI->getLibraryClass('ezpdf/class.ezpdf'));

$pdf = &new Cezpdf($paper = 'A4', $orientation = 'portrait');
$pdf->ezSetCmMargins(1, 2, 1.5, 1.5);
$pdf->selectFont($font_dir . '/Helvetica.afm');
$pdf->ezText('Project: ' . $forum['project_name'] . '   Forum: ' . $forum['forum_name']);
$pdf->ezText('Topic: ' . $topic);
$pdf->ezText('');
$options = array('showLines' => 1, 'showHeadings' => 1, 'fontSize' => 8, 'rowGap' => 2, 'colGap' => 5, 'xPos' => 50, 'xOrientation' => 'right', 'width' => '500');

$pdf->ezTable($pdfdata, $pdfhead, null, $options);

$pdf->ezStream();
?>