<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require_once('C:/xampp/htdocs/wordpress/wp-load.php');

// Get board member 710
$bm = get_post(710);
echo "Board member: " . $bm->post_title . "\n";
$sp_id = get_post_meta($bm->ID, '_tm_sp_id', true);
echo "SP ID: $sp_id\n";
$photo_id = get_post_meta($bm->ID, '_tm_photo', true);
echo "Photo ID: $photo_id\n";

$photo_post = get_post($photo_id);
echo "Photo file: " . get_attached_file($photo_id) . "\n";
echo "Photo filename: " . basename(get_attached_file($photo_id)) . "\n";

$filename = basename(get_attached_file($photo_id));
echo "\nFilename: $filename\n";
echo "Base: " . pathinfo($filename, PATHINFO_FILENAME) . "\n";
echo "Ext: " . strtolower(pathinfo($filename, PATHINFO_EXTENSION)) . "\n";

?>
