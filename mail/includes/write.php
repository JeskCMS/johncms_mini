<?php

/**
 * @package     JohnCMS
 * @link        http://johncms.com
 * @copyright   Copyright (C) 2008-2011 JohnCMS Community
 * @license     LICENSE.txt (see attached file)
 * @version     VERSION.txt (see attached file)
 * @author      http://johncms.com/about
 */

defined('_IN_JOHNCMS') or die('Error: restricted access');

$set_mail = unserialize($user['set_mail']);
$out = '';
$total = 0;
$ch = 0;
$mod = isset($_REQUEST['mod']) ? $_REQUEST['mod'] : '';

if ($id) {
    $stmt = $db->query("SELECT * FROM `users` WHERE `id` = '$id' LIMIT 1");
    if (!$stmt->rowCount()) {
        $textl = $lng['mail'];
        require_once('../incfiles/head.php');
        echo functions::display_error($lng['error_user_not_exist']);
        require_once("../incfiles/end.php");
        exit;
    }
    $qs = $stmt->fetch();
    if ($mod == 'clear') {
        $textl = $lng['mail'];
        require_once('../incfiles/head.php');
        echo '<div class="phdr"><b>' . $lng_mail['clear_messages'] . '</b></div>';
        if (isset($_POST['clear'])) {
            $count_message = $db->query("SELECT COUNT(*) FROM `cms_mail` WHERE ((`user_id`='$id' AND `from_id`='$user_id') OR (`user_id`='$user_id' AND `from_id`='$id')) AND `delete`!='$user_id'")->fetchColumn();
            if ($count_message) {
                $stmt = $db->query("SELECT `cms_mail`.* FROM `cms_mail` WHERE ((`cms_mail`.`user_id`='$id' AND `cms_mail`.`from_id`='$user_id') OR (`cms_mail`.`user_id`='$user_id' AND `cms_mail`.`from_id`='$id')) AND `cms_mail`.`delete`!='$user_id' LIMIT " . $count_message);
                while ($row = $stmt->fetch()) {
                    if ($row['delete']) {
                        $db->exec("DELETE FROM `cms_mail` WHERE `id`='{$row['id']}' LIMIT 1");
                    } else {
                        if ($row['read'] == 0 && $row['user_id'] == $user_id) {
                            $db->exec("DELETE FROM `cms_mail` WHERE `id`='{$row['id']}' LIMIT 1");
                        } else {
                            $db->exec("UPDATE `cms_mail` SET `delete` = '" . $user_id . "' WHERE `id` = '" . $row['id'] . "' LIMIT 1");
                        }
                    }
                }
            }
            echo '<div class="gmenu"><p>' . $lng_mail['messages_are_removed'] . '</p></div>';
        } else {
            echo '<div class="rmenu">
			<form action="index.php?act=write&amp;mod=clear&amp;id=' . $id . '" method="post">
			<p>' . $lng_mail['really_messages_removed'] . '</p>
			<p><input type="submit" name="clear" value="' . $lng['delete'] . '"/></p>
			</form>
			</div>';
        }
        echo '<div class="phdr"><a href="index.php?act=write&amp;id=' . $id . '">' . $lng['back'] . '</a></div>';
        echo '<p><a href="../users/profile.php?act=office">' . $lng['personal'] . '</a></p>';
        require_once('../incfiles/end.php');
        exit;
    }
}

if (empty($_SESSION['error'])) {
    $_SESSION['error'] = '';
}

$out .= '<div class="phdr"><b>' . $lng['mail'] . '</b></div>';

if (isset($_POST['submit']) && empty($ban['1']) && empty($ban['3']) && !functions::is_ignor($id)) {
    if (!$id) {
        $name = isset($_POST['nick']) ? functions::rus_lat(mb_strtolower(trim($_POST['nick']))) : '';
    }
    $text = isset($_POST['text']) ? trim($_POST['text']) : '';
    if ($set_user['translit'] && isset($_POST['msgtrans'])) {
        $text = functions::trans($text);
    }

    $error = array();

    if (!$id && empty($name)) {
        $error[] = $lng_mail['indicate_login_grantee'];
    }
    if (empty($text)) {
        $error[] = $lng_mail['message_not_empty'];
    }
    if (($id && $id == $user_id) || !$id && $datauser['name_lat'] == $name) {
        $error[] = $lng_mail['impossible_add_message'];
    }
    $flood = functions::antiflood();
    if ($flood) {
        $error[] = $lng['error_flood'] . ' ' . $flood . $lng['sec'];
    }
    if (empty($error)) {
        if (!$id) {
            $stmt = $db->prepare("SELECT * FROM `users` WHERE `name_lat`= ? LIMIT 1");
            $stmt->execute([
                $name
            ]);
            if (!$stmt->rowCount()) {
                $error[] = $lng['error_user_not_exist'];
            } else {
                $user = $stmt->fetch();
                $id = $user['id'];
                $set_mail = unserialize($user['set_mail']);
            }
        } else {
            $set_mail = unserialize($qs['set_mail']);
        }

        if (empty($error)) {
            if ($set_mail) {
                if ($rights < 1) {
                    if ($set_mail['access']) {
                        if ($set_mail['access'] == 1) {
                            $stmt = $db->query("SELECT COUNT(*) FROM `cms_contact` WHERE `user_id`='" . $id . "' AND `from_id`='" . $user_id . "'");
                            if (!$stmt->fetchColumn()) {
                                $error[] = $lng_mail['write_contacts'];
                            }
                        } else if ($set_mail['access'] == 2) {
                            $stmt = $db->query("SELECT COUNT(*) FROM `cms_contact` WHERE `user_id`='" . $id . "' AND `from_id`='" . $user_id . "' AND `friends`='1'");
                            if (!$stmt->fetchColumn()) {
                                $error[] = $lng_mail['write_friends'];
                            }
                        }
                    }
                }
            }
        }
    }

    if (empty($error)) {
        $ignor = $db->query("SELECT COUNT(*) FROM `cms_contact`
		WHERE `user_id`='" . $user_id . "'
		AND `from_id`='" . $id . "'
		AND `ban`='1';")->fetchColumn();
        if ($ignor)
            $error[] = $lng_mail['error_user_ignor_in'];
        if (empty($error)) {
            $ignor_m = $db->query("SELECT COUNT(*) FROM `cms_contact`
			WHERE `user_id`='" . $id . "'
			AND `from_id`='" . $user_id . "'
			AND `ban`='1';")->fetchColumn();
            if ($ignor_m) {
                $error[] = $lng_mail['error_user_ignor_out'];
            }
        }
    }

    if (empty($error)) {
        $stmt = $db->query("SELECT COUNT(*) FROM `cms_contact`
		WHERE `user_id`='" . $user_id . "' AND `from_id`='" . $id . "';");
        if (!$stmt->fetchColumn()) {
            $db->exec("INSERT INTO `cms_contact` SET
			`user_id` = '" . $user_id . "',
			`from_id` = '" . $id . "',
			`time` = '" . time() . "'");
            $ch = 1;
        }
        $stmt = $db->query("SELECT COUNT(*) FROM `cms_contact`
		WHERE `user_id`='" . $id . "' AND `from_id`='" . $user_id . "';");
        if (!$stmt->rowCount()) {
            $db->exec("INSERT INTO `cms_contact` SET
			`user_id` = '" . $id . "',
			`from_id` = '" . $user_id . "',
			`time` = '" . time() . "'");
            $ch = 1;
        }

    }

    // Проверяем на повтор сообщения
    if (empty($error)) {
        $stmt = $db->query("SELECT `text` FROM `cms_mail`
        WHERE `user_id` = $user_id
        AND `from_id` = $id
        ORDER BY `id` DESC
        LIMIT 1
        ");
        if ($stmt->rowCount()) {
            $rres = $stmt->fetch();
            if ($rres['text'] == $text) {
                $error[] = $lng['error_message_exists'];
            }
        }
    }


    if (empty($error)) {
        $stmt = $db->prepare("INSERT INTO `cms_mail` SET
		`user_id` = '" . $user_id . "',
		`from_id` = '" . $id . "',
		`text` = ?,
		`time` = '" . time() . "'");
        $stmt->execute([
            $text
        ]);

        $db->exec("UPDATE `users` SET `lastpost` = '" . time() . "' WHERE `id` = '$user_id';");
        if ($ch == 0) {
            $db->exec("UPDATE `cms_contact` SET `time` = '" . time() . "' WHERE `user_id` = '" . $user_id . "' AND
			`from_id` = '" . $id . "';");
            $db->exec("UPDATE `cms_contact` SET `time` = '" . time() . "' WHERE `user_id` = '" . $id . "' AND
			`from_id` = '" . $user_id . "';");
        }
        Header('Location: index.php?act=write' . ($id ? '&id=' . $id : ''));
        exit;
    } else {
        $out .= '<div class="rmenu">' . implode('<br />', $error) . '</div>';
    }
}

if (!functions::is_ignor($id) && empty($ban['1']) && empty($ban['3'])) {

    $out .= isset($_SESSION['error']) ? $_SESSION['error'] : '';
    $out .= '<div class="gmenu">' .
        '<form name="form" action="index.php?act=write' . ($id ? '&amp;id=' . $id : '') . '" method="post"  enctype="multipart/form-data">' .
        ($id ? '' : '<p><input type="text" name="nick" maxlength="15" value="' . (!empty($_POST['nick']) ? _e($_POST['nick']) : '') . '" placeholder="' . $lng_mail['to_whom'] . '?"/></p>') .
        '<p>';
    $out .= bbcode::auto_bb('form', 'text');
    $out .= '<textarea rows="' . $set_user['field_h'] . '" name="text"></textarea></p>';
    if ($set_user['translit'])
        $out .= '<input type="checkbox" name="msgtrans" value="1" ' . (isset($_POST['msgtrans']) ? 'checked="checked" ' : '') . '/> ' . $lng['translit'] . '<br />';
    $out .= '<p><input type="submit" name="submit" value="' . $lng['sent'] . '"/></p>' .
        '</form></div>' .
        '<div class="phdr"><b>' . ($id && isset($qs) ? $lng_mail['personal_correspondence'] . ' <a href="../users/profile.php?user=' . $qs['id'] . '">' . $qs['name'] . '</a>' : $lng_mail['sending_the_message']) . '</b></div>';
}

if ($id) {

    $total = $db->query("SELECT COUNT(*) FROM `cms_mail` WHERE ((`user_id`='$id' AND `from_id`='$user_id') OR (`user_id`='$user_id' AND `from_id`='$id')) AND `sys`!='1' AND `delete`!='$user_id' AND `spam`='0'")->fetchColumn();

    if ($total) {

        if ($total > $kmess) $out .= '<div class="topmenu">' . functions::display_pagination('index.php?act=write&amp;id=' . $id . '&amp;', $start, $total, $kmess) . '</div>';

        $stmt = $db->query("SELECT `cms_mail`.*, `cms_mail`.`id` as `mid`, `cms_mail`.`time` as `mtime`, `users`.*
            FROM `cms_mail`
            LEFT JOIN `users` ON `cms_mail`.`user_id`=`users`.`id`
            WHERE ((`cms_mail`.`user_id`='$id' AND `cms_mail`.`from_id`='$user_id') OR (`cms_mail`.`user_id`='$user_id' AND `cms_mail`.`from_id`='$id'))
            AND `cms_mail`.`delete`!='$user_id'
            AND `cms_mail`.`sys`!='1'
            AND `cms_mail`.`spam`='0'
            ORDER BY `cms_mail`.`time` DESC
            LIMIT " . $start . "," . $kmess);
        $i = 1;
        $mass_read = array();
        while ($row = $stmt->fetch()) {
            if (!$row['read']) {
                $out .= '<div class="gmenu">';
            } else {
                if ($row['from_id'] == $user_id) {
                    $out .= '<div class="list2">';
                } else {
                    $out .= '<div class="list1">';
                }
            }
            if ($row['read'] == 0 && $row['from_id'] == $user_id)
                $mass_read[] = $row['mid'];
            $post = $row['text'];
            $post = functions::checkout($post, 1, 1);
            if ($set_user['smileys']) {
                $post = functions::smileys($post, $row['rights'] >= 1 ? 1 : 0);
            }
            $subtext = '<a href="index.php?act=delete&amp;id=' . $row['mid'] . '">' . $lng['delete'] . '</a>';
            $arg = array(
                'header'  => '(' . functions::display_date($row['mtime']) . ')',
                'body'    => $post,
                'sub'     => $subtext,
                'stshide' => 1
            );
            core::$user_set['avatar'] = 0;
            $out .= functions::display_user($row, $arg);
            $out .= '</div>';
            ++$i;
        }
        //Ставим метку о прочтении
        if ($mass_read) {
            $result = implode(',', $mass_read);
            $db->exec("UPDATE `cms_mail` SET `read`='1' WHERE `from_id`='$user_id' AND `id` IN (" . $result . ")");
        }
    } else {
        $out .= '<div class="menu"><p>' . $lng['list_empty'] . '</p></div>';
    }

    $out .= '<div class="phdr">' . $lng['total'] . ': ' . $total . '</div>';
    if ($total > $kmess) {
        $out .= '<div class="topmenu">' . functions::display_pagination('index.php?act=write&amp;id=' . $id . '&amp;', $start, $total, $kmess) . '</div>';
        $out .= '<p><form action="index.php" method="get">
			<input type="hidden" name="act" value="write"/>
			<input type="hidden" name="id" value="' . $id . '"/>
			<input type="text" name="page" size="2"/>
			<input type="submit" value="' . $lng['to_page'] . ' &gt;&gt;"/></form></p>';
    }
}

$textl = $lng['mail'];
require_once('../incfiles/head.php');
echo $out;
echo '<p>';
if ($total) {
    echo '<a href="index.php?act=write&amp;mod=clear&amp;id=' . $id . '">' . $lng_mail['clear_messages'] . '</a><br/>';
}
echo '<a href="../users/profile.php?act=office">' . $lng['personal'] . '</a></p>';
unset($_SESSION['error']);