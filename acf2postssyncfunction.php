
<?php
function compare_related_posttype($value, $post_id, $field, $field_a, $field_b)
{
    $previous_value = get_field($field_a, $post_id); // Get the previous value
    // Compare the previous value with the updated value
    //if (!empty($previous_value) && !empty($value)) {
    // Extract post IDs from previous value
    $all_are_posts_pv = true;
    $all_are_posts_v = true;
    foreach ($previous_value as $post) {
        if (!is_a($post, 'WP_Post')) {
            $all_are_posts_pv = false;
            break;
        }
    }
    foreach ($value as $post) {
        if (!is_a($post, 'WP_Post')) {
            $all_are_posts_v = false;
            break;
        }
    }
    if (!$all_are_posts_pv) {
        $previous_ids = $previous_value;
    } else {
        $previous_ids = array_map(function ($post) {
            return $post->ID;
        }, $previous_value);
    }
    // Extract post IDs from updated value
    if (!$all_are_posts_v) {
        $current_ids = $value;
    } else {
        $current_ids = array_map(function ($post) {
            return $post->ID;
        }, $value);
    }

    // Find the difference
    if (empty($current_ids)) {
        $diff_post_type_items = $previous_ids;
    } else {
        $diff_post_type_items = array_diff($previous_ids, $current_ids);
    }
    // Do something with the difference
    if (!empty($diff_post_type_items)) {
        foreach ($diff_post_type_items as $item) {
            $all_are_posts_col = true;
            $collections = get_field($field_b, $item);
            foreach ($collections as $post) {
                if (!is_a($post, 'WP_Post')) {
                    $all_are_posts_col = false;
                    break;
                }
            }
            if (!$all_are_posts_col) {
                $collections = $collections;
            } else {
                $collections = array_map(function ($post) {
                    return $post->ID;
                }, $collections);
            }
            if (empty($collections)) {
                $col_diff = (array)[$post_id];
            } else {
                $col_diff = array_diff($collections, (array)[$post_id]);
            }
            update_field($field_b, $col_diff, $item);
        }
    }
    //}
    //return $value;
}

function compare_related_hotels($value, $post_id, $field)
{
    // Check if compare_related_collections is currently being executed
    static $is_running = false;
    if ($is_running) {
        return $value;
    }
    $post_type = get_post_type($post_id);
    if ($post_type == 'collections') {
        $is_running = false;
        compare_related_posttype($value, $post_id, $field, 'related_hotels', 'related_collections');
        return $value;
    } else {
        return $value;
    }
}

function compare_related_collections($value, $post_id, $field)
{
    // Check if compare_related_collections is currently being executed
    static $is_running = false;
    if ($is_running) {
        return $value;
    }
    $post_type = get_post_type($post_id);
    if ($post_type == 'hotels') {
        $is_running = true;
        compare_related_posttype($value, $post_id, $field, 'related_collections', 'related_hotels');
        return $value;
    } else {
        return $value;
    }
}

add_filter('acf/update_value/name=related_hotels', 'compare_related_hotels', 10, 3);
add_filter('acf/update_value/name=related_collections', 'compare_related_collections', 10, 3);


function SyncronizationPostTypesAcf($a_field_name, $b_field_name, $post_id)
{
    //Update
    //If post type $a updates
    //Mega hotel
    $a_related_field_posts = get_field($b_field_name, $post_id);
    if (!empty($a_related_field_posts)) {
        foreach ($a_related_field_posts as $a_related_field_post) {
            //Related post
            $a_related_field_id = $a_related_field_post;
            $a_post_type_items_id = get_field($a_field_name, $a_related_field_id);
            //If post type not inside post a, put it
            if (is_array($a_post_type_items_id)) {
                $is_present_pid = in_array($post_id, $a_post_type_items_id);
                if (!$is_present_pid) {
                    $a_post_type_items_id[] = $post_id;
                    update_field($a_field_name, $a_post_type_items_id, $a_related_field_id);
                }
            } elseif (!is_array($a_post_type_items_id)) {
                //if not an array
                $a_pt_item_id_array = [];
                $a_post_type_item_id = $a_post_type_items_id;
                if (!empty($a_post_type_item_id)) {
                    array_push($a_pt_item_id_array, $a_post_type_item_id, $post_id);
                } else {
                    $a_pt_item_id_array[] = $post_id;
                }
                //related_collections
                update_field($a_field_name, $a_pt_item_id_array, $a_related_field_id);
            }
        }
    }


    //Remove
    //deleted_acf_sync($a_field_name, $post_id);
}

function update_connected_collections($post_id): void
{
    $post_type = get_post_type($post_id);
    if ($post_type == 'hotels') {
        SyncronizationPostTypesAcf('related_hotels', 'related_collections', $post_id);
    } else {
        return;
    }
}

function update_connected_hotels($post_id): void
{
    $post_type = get_post_type($post_id);
    if ($post_type == 'collections') {
        SyncronizationPostTypesAcf('related_collections', 'related_hotels', $post_id);
    } else {
        return;
    }
}

add_action('acf/save_post', function ($post_id) {
    update_connected_collections($post_id);
}, 20);

add_action('acf/save_post', function ($post_id) {
    update_connected_hotels($post_id);
}, 20);

?>