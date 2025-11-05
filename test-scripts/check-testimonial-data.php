<?php
/**
 * Check actual testimonial data in WordPress database
 */

if (!defined('ABSPATH')) {
    echo "This script must be run within WordPress context\n";
    exit;
}

// Get all testimonials
$testimonials = get_posts([
    'post_type' => 'testimonial',
    'post_status' => 'any',
    'numberposts' => -1
]);

echo "=== Testimonials in WordPress Database ===\n\n";
echo "Total testimonials: " . count($testimonials) . "\n\n";

if (empty($testimonials)) {
    echo "No testimonials found!\n";
    exit;
}

foreach ($testimonials as $testimonial) {
    echo "--- Testimonial ID: {$testimonial->ID} ---\n";
    echo "Post Title: {$testimonial->post_title}\n";
    echo "Post Status: {$testimonial->post_status}\n";
    
    // Get all meta for this testimonial
    $meta = get_post_meta($testimonial->ID);
    
    echo "\nMeta Fields:\n";
    foreach ($meta as $key => $values) {
        $value = is_array($values) ? (isset($values[0]) ? $values[0] : json_encode($values)) : $values;
        echo "  {$key}: " . (is_string($value) ? $value : json_encode($value)) . "\n";
    }
    
    // Specifically check rating
    $rating = get_post_meta($testimonial->ID, '_tm_rating', true);
    $name = get_post_meta($testimonial->ID, '_tm_name', true);
    $comment = get_post_meta($testimonial->ID, '_tm_comment', true);
    $sp_id = get_post_meta($testimonial->ID, '_tm_sp_id', true);
    
    echo "\nParsed Fields:\n";
    echo "  SP ID (_tm_sp_id): " . ($sp_id ? $sp_id : 'NOT SET') . "\n";
    echo "  Name (_tm_name): " . ($name ? $name : 'NOT SET') . "\n";
    echo "  Comment (_tm_comment): " . ($comment ? $comment : 'NOT SET') . "\n";
    echo "  Rating (_tm_rating): " . ($rating !== '' ? intval($rating) . " (type: " . gettype($rating) . ")" : 'NOT SET') . "\n";
    
    // Check if rating is displayed correctly
    if ($rating !== '') {
        $rating_int = intval($rating);
        echo "  Rating Display: ";
        for ($i = 1; $i <= 5; $i++) {
            echo ($i <= $rating_int ? '★' : '☆');
        }
        echo "\n";
    }
    
    echo "\n";
}

// Also check if shortcode would display correctly
echo "\n=== Shortcode Display Test ===\n\n";

if (!empty($testimonials)) {
    $testimonial = $testimonials[0];
    $rating = intval(get_post_meta($testimonial->ID, '_tm_rating', true));
    
    echo "First testimonial rating value: " . ($rating !== 0 ? $rating : 'ZERO or NOT SET') . "\n";
    echo "Display as stars: ";
    for ($i = 1; $i <= 5; $i++) {
        echo ($i <= $rating ? '★' : '☆');
    }
    echo "\n";
}
?>
