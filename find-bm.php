<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');

$bm = get_posts(['post_type' => 'board_member', 'posts_per_page' => 1]);
echo 'Board member: ' . $bm[0]->post_title . " (ID: " . $bm[0]->ID . ")\n";
$photo = get_post_meta($bm[0]->ID, '_tm_photo', true);
echo 'Has photo: ' . ($photo ? 'YES (ID ' . $photo . ')' : 'NO') . "\n";
?>
