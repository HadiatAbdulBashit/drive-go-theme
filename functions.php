<?php

function drive_go_theme_setup() {
    add_theme_support('woocommerce');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('drive-go-logo');
    add_theme_support('html5', array('search-form', 'comment-form', 'gallery'));
}
add_action('after_setup_theme', 'drive_go_theme_setup');

function drive_go_enqueue_scripts() {
    wp_enqueue_style('drive-go-style', get_stylesheet_uri(), array(), wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'drive_go_enqueue_scripts');

function drive_go_register_menus() {
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'drive-go-woocommerce-theme'),
    ));
}
add_action('init', 'drive_go_register_menus');

function drive_go_woocommerce_setup() {
    add_filter('woocommerce_product_single_add_to_cart_text', function() {
        return __('Book Now', 'drive-go-woocommerce-theme');
    });
    add_filter('woocommerce_product_add_to_cart_text', function() {
        return __('Book Now', 'drive-go-woocommerce-theme');
    });
}
add_action('after_setup_theme', 'drive_go_woocommerce_setup');

function drive_go_add_vehicle_availability_field() {
    woocommerce_wp_checkbox(array(
        'id' => '_vehicle_availability',
        'label' => __('Available for Booking', 'drive-go-woocommerce-theme'),
        'description' => __('Check if the vehicle is available for rental.', 'drive-go-woocommerce-theme')
    ));
}
add_action('woocommerce_product_options_general_product_data', 'drive_go_add_vehicle_availability_field');

function drive_go_save_vehicle_availability_field($post_id) {
    $availability = isset($_POST['_vehicle_availability']) ? 'yes' : 'no';
    update_post_meta($post_id, '_vehicle_availability', $availability);
}
add_action('woocommerce_process_product_meta', 'drive_go_save_vehicle_availability_field');

function drive_go_disable_booking_button() {
    global $product;
    $availability = get_post_meta($product->get_id(), '_vehicle_availability', true);
    if ($availability !== 'yes') {
        echo '<p style="color: red; font-weight: bold;">This vehicle is not available for booking.</p>';
    }
}
add_action('woocommerce_single_product_summary', 'drive_go_disable_booking_button', 25);

function drive_go_add_product_modal() {
    ?>
    <div id="drive-go-product-modal" class="drive-go-modal" style="display: none;">
        <div class="drive-go-modal-content">
            <span class="drive-go-close">&times;</span>
            <div id="drive-go-product-details"></div>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'drive_go_add_product_modal');

function drive_go_load_product_details() {
    if (!isset($_POST['product_id'])) {
        wp_send_json_error(['message' => 'Product ID is missing.']);
    }

    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => 'Product not found.']);
    }

    $image_url = wp_get_attachment_image_url($product->get_image_id(), 'full');
    $price = $product->get_price_html();
    $short_description = $product->get_short_description();

    $attributes = $product->get_attributes();
    $attribute_data = [];

    foreach ($attributes as $attribute_name => $attribute) {
        $terms = wc_get_product_terms($product_id, $attribute_name, ['fields' => 'names']);
        $attribute_data[$attribute_name] = !empty($terms) ? implode(', ', $terms) : 'N/A';
    }

    $product_url = get_permalink($product_id);

    wp_send_json_success([
        'title'        => $product->get_name(),
        'image'        => $image_url,
        'price'        => $price,
        'description'  => $short_description,
        'engine_type'  => $attribute_data['pa_engine-type'] ?? 'N/A',
        'availability' => $attribute_data['pa_availability'] ?? 'N/A',
        'fuel'         => $attribute_data['pa_fuel'] ?? 'N/A',
        'gearbox'      => $attribute_data['pa_gearbox'] ?? 'N/A',
        'seats'        => $attribute_data['pa_seats'] ?? 'N/A',
        'product_url'  => $product_url
    ]);
}
add_action('wp_ajax_drive_go_load_product_details', 'drive_go_load_product_details');
add_action('wp_ajax_nopriv_drive_go_load_product_details', 'drive_go_load_product_details');

function drive_go_enqueue_assets() {
    wp_enqueue_style(
        'drive-go-style',
        get_template_directory_uri() . '/assets/css/drive-go-style.css',
        array(),
        wp_get_theme()->get('Version')
    );

    wp_enqueue_script(
        'drive-go-modal-js',
        get_template_directory_uri() . '/assets/js/drive-go-modal.js',
        array('jquery'),
        null,
        true
    );

    wp_localize_script('drive-go-modal-js', 'driveGoAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'drive_go_enqueue_assets');

function drive_go_add_search_bar() {
    if (is_shop() || is_product_category()) {
        ?>
        <div class="search_bar">
            <form action="/" method="get" autocomplete="off" id="product_search">
                <input type="search" name="s" placeholder="Search Product..." id="keyword" class="input_search" onkeyup="mukto_fetch()">
                <select name="cat" id="cat" onchange="mukto_fetch()">
                    <option value="">All Categories</option>
                    <?php
                        $terms = get_terms(array(
                            'taxonomy'   => 'product_cat',
                            'hide_empty' => true,
                        ));

                        foreach ($terms as $term) {
                            echo '<option value="' . $term->term_id . '"> ' . $term->name . ' </option>';
                        }
                    ?>
                </select>
            </form>
            <div class="search_result" id="datafetch">
                <ul>
                    <li style="padding: 10px;">Please wait..</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
add_action('woocommerce_before_shop_loop', 'drive_go_add_search_bar', 15);

add_action( 'wp_footer', 'ajax_fetch' );
function ajax_fetch() {
?>
<script type="text/javascript">
function mukto_fetch(){
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'post',
        data: { action: 'data_fetch', keyword: jQuery('#keyword').val(), pcat: jQuery('#cat').val() },
        success: function(data) {
             jQuery('#datafetch').html( data );
        }
    });
}
</script>

<?php
}

function data_fetch(){
    if ($_POST['pcat']) {
        $product_cat_id = array(esc_attr( $_POST['pcat'] ));
    } else {
        $terms = get_terms( 'product_cat' ); 
        $product_cat_id = wp_list_pluck( $terms, 'term_id' );
    }
    
    $the_query = new WP_Query( 
        array( 
            'posts_per_page' => -1, 
            's' => esc_attr( $_POST['keyword'] ), 
            'post_type' => array('product'),
            
            'tax_query' => array(
                array(
                    'taxonomy'  => 'product_cat',
                    'field'     => 'term_id',
                    'terms'     => $product_cat_id,
                    'operator'  => 'IN',
                )
           )
        ) 
    );


    if( $the_query->have_posts() ) :
        echo '<ul>';
        while( $the_query->have_posts() ): $the_query->the_post();
            global $product;
            ?>

            <li>
                <a href="<?php echo esc_url( get_permalink() ); ?>">
                    <?php the_post_thumbnail('thumbnail'); ?>
					<div class='search-desc-wrapper'>
						<strong><?php the_title(); ?></strong>
						<p><?php echo wp_trim_words( get_the_excerpt(), 32 ); ?></p> <!-- Show product description (trimmed) -->
					</div>
                </a>
            </li>

        <?php endwhile;
        echo '</ul>';
        wp_reset_postdata();  
    endif;

    die();
}
add_action('wp_ajax_data_fetch' , 'data_fetch');
add_action('wp_ajax_nopriv_data_fetch','data_fetch');

function drive_go_theme_admin_notice() {
    if (!class_exists('WooCommerce') || !is_plugin_active('woocommerce-easy-booking-system/woocommerce-easy-booking-system.php')) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Drive Go Theme:</strong> This theme works best with <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> and <a href="https://wordpress.org/plugins/woocommerce-easy-booking-system/" target="_blank">Easy Booking</a>. Please install and activate them for full functionality.</p>';
        echo '</div>';
    }
}

add_action('admin_notices', 'drive_go_theme_admin_notice');
