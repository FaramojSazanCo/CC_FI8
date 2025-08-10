<?php
/**
 * Plugin Name: CCIF Iran Checkout (Fresh Rebuild)
 * Description: A plugin to customize the WooCommerce checkout form for Iran.
 * Version: 6.0
 * Author: Your Name
 * Text Domain: ccif-iran-checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CCIF_Iran_Checkout_Rebuild {

    private $order_notes_field = [];
    private $custom_billing_fields = [
        'billing_person_type',
        'billing_national_code',
        'billing_company_name',
        'billing_economic_code',
        'billing_agent_first_name',
        'billing_agent_last_name',
        'billing_invoice_request'
    ];

    public function __construct() {
        // Add custom validation
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_custom_fields' ] );

        // Modify checkout fields
        add_filter( 'woocommerce_checkout_fields', [ $this, 'modify_checkout_fields' ] );

        // Hook into checkout fields to manage them
        add_filter( 'woocommerce_checkout_fields', [ $this, 'move_order_notes_field' ] );

        // Modify field arguments, e.g., to remove '(optional)' text
        add_filter( 'woocommerce_form_field_args', [ $this, 'remove_optional_text' ], 999, 3 );

        // Save custom fields to order meta
        add_action( 'woocommerce_checkout_create_order', [ $this, 'save_custom_fields_to_order_meta' ], 10, 2 );

        // Save custom fields to user meta
        add_action( 'woocommerce_checkout_update_user_meta', [ $this, 'save_custom_fields_to_user_meta' ], 10, 2 );

        // Pre-populate custom fields from user meta
        add_filter( 'woocommerce_checkout_get_value', [ $this, 'get_custom_field_value_from_user_meta' ], 10, 2 );

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Display custom fields on the order details pages (thank you & my-account)
        add_action( 'woocommerce_order_details_after_order_table', [ $this, 'display_custom_fields_on_order_pages' ], 999, 1 );

        // Display custom fields in the admin order edit page
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'display_custom_fields_in_admin_order' ], 10, 1 );

        // --- Add custom columns to the admin orders list (HPOS and legacy compatible) ---

        // Legacy (non-HPOS) hooks
        add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_invoice_details_column_to_admin_orders_list' ], 999 );
        add_action( 'manage_shop_order_posts_custom_column', [ $this, 'populate_invoice_details_column_legacy' ], 10, 2 );

        // HPOS-compatible hooks
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_invoice_details_column_to_admin_orders_list' ], 999 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'populate_invoice_details_column' ], 10, 2 );

        // The new approach will use a template override, so all old layout hooks are removed.
        // We will add the template override filter later.
    }

    public function validate_custom_fields() {
        $is_invoice_requested = isset( $_POST['billing_invoice_request'] ) && $_POST['billing_invoice_request'] == 1;
        $person_type = isset( $_POST['billing_person_type'] ) ? sanitize_text_field($_POST['billing_person_type']) : '';

        // --- Standard Format Validations ---
        if ( ! empty( $_POST['billing_postcode'] ) && ( ! is_numeric( $_POST['billing_postcode'] ) || strlen( $_POST['billing_postcode'] ) !== 10 ) ) {
            wc_add_notice( __( 'لطفاً یک <strong>کد پستی</strong> معتبر ۱۰ رقمی و عددی وارد کنید.' ), 'error' );
        }
        if ( ! empty( $_POST['billing_phone'] ) && ! is_numeric( $_POST['billing_phone'] ) ) {
            wc_add_notice( __( 'فیلد <strong>شماره تماس</strong> باید فقط شامل اعداد باشد.' ), 'error' );
        }

        // --- Conditional Validation for Invoice ---
        if ( $is_invoice_requested ) {
            // 1. Validate Person Type
            if ( empty( $person_type ) ) {
                wc_add_notice( __( 'برای صدور فاکتور رسمی، لطفاً <strong>نوع شخص</strong> را انتخاب کنید.' ), 'error' );
            }

            // 2. Validate Real Person Fields
            if ( $person_type === 'real' ) {
                if ( empty( $_POST['billing_first_name'] ) ) wc_add_notice( __( '<strong>نام</strong> برای صدور فاکتور الزامی است.' ), 'error' );
                if ( empty( $_POST['billing_last_name'] ) ) wc_add_notice( __( '<strong>نام خانوادگی</strong> برای صدور فاکتور الزامی است.' ), 'error' );
                if ( empty( $_POST['billing_national_code'] ) ) {
                    wc_add_notice( __( '<strong>کد ملی</strong> برای صدور فاکتور الزامی است.' ), 'error' );
                } elseif ( ! is_numeric( $_POST['billing_national_code'] ) || strlen( $_POST['billing_national_code'] ) !== 10 ) {
                    wc_add_notice( __( 'لطفاً یک <strong>کد ملی</strong> معتبر ۱۰ رقمی و عددی وارد کنید.' ), 'error' );
                }
            }

            // 3. Validate Legal Person Fields
            if ( $person_type === 'legal' ) {
                if ( empty( $_POST['billing_company_name'] ) ) wc_add_notice( __( '<strong>نام شرکت</strong> برای صدور فاکتور الزامی است.' ), 'error' );
                if ( empty( $_POST['billing_economic_code'] ) ) {
                     wc_add_notice( __( '<strong>شناسه ملی/اقتصادی</strong> برای صدور فاکتور الزامی است.' ), 'error' );
                } elseif ( ! is_numeric( $_POST['billing_economic_code'] ) ) {
                    wc_add_notice( __( 'فیلد <strong>شناسه ملی/اقتصادی</strong> باید فقط شامل اعداد باشد.' ), 'error' );
                }
                if ( empty( $_POST['billing_agent_first_name'] ) ) wc_add_notice( __( '<strong>نام نماینده</strong> برای صدور فاکتور الزامی است.' ), 'error' );
                if ( empty( $_POST['billing_agent_last_name'] ) ) wc_add_notice( __( '<strong>نام خانوادگی نماینده</strong> برای صدور فاکتور الزامی است.' ), 'error' );
            }
        }
    }

    public function save_custom_fields_to_order_meta( $order, $data ) {
        $this->log_message( 'Attempting to save custom fields for order ID: ' . $order->get_id() );
        foreach ( $this->custom_billing_fields as $field_key ) {
            if ( isset( $data[ $field_key ] ) && ! empty( $data[ $field_key ] ) ) {
                $value = sanitize_text_field( $data[ $field_key ] );
                $order->update_meta_data( '_' . $field_key, $value );
                $this->log_message( "Saved meta for order {$order->get_id()}: { '{$field_key}' : '{$value}' }" );
            }
        }
    }

    public function save_custom_fields_to_user_meta( $customer_id, $posted_data ) {
        if ( ! $customer_id ) {
            return; // Only for logged-in users
        }

        foreach ( $this->custom_billing_fields as $field_key ) {
            if ( isset( $posted_data[ $field_key ] ) ) {
                $value = sanitize_text_field( $posted_data[ $field_key ] );
                update_user_meta( $customer_id, $field_key, $value );
            }
        }
    }

    public function get_custom_field_value_from_user_meta( $value, $key ) {
        // If there's already a value (e.g., from a failed submission), don't override it.
        if ( ! empty( $value ) ) {
            return $value;
        }

        // Check if the user is logged in and the key is one of our custom fields.
        if ( is_user_logged_in() && in_array( $key, $this->custom_billing_fields ) ) {
            $saved_value = get_user_meta( get_current_user_id(), $key, true );
            if ( $saved_value ) {
                return $saved_value;
            }
        }

        return $value;
    }

    public function move_order_notes_field( $fields ) {
        if ( isset( $fields['order'] ) && isset( $fields['order']['order_comments'] ) ) {
            // We capture the field definition but no longer unset it from the main array.
            // WooCommerce will render it in its default location unless we render it manually elsewhere.
            $this->order_notes_field = $fields['order']['order_comments'];
        }
        return $fields;
    }

    public function remove_optional_text( $args, $key, $value ) {
        if ( isset($args['label']) ) {
            // This function now removes the "(optional)" text from ALL fields, required or not,
            // to ensure consistency and address user feedback.

            // First, remove the <span> tag that WooCommerce adds to non-required fields.
            $args['label'] = preg_replace( '/(\s|&nbsp;)?<span class="optional">.*?<\/span>/i', '', $args['label'] );

            // Second, remove the literal text `(اختیاری)` in case it was hardcoded in a label.
            $args['label'] = str_replace( '(اختیاری)', '', $args['label'] );
        }
        return $args;
    }

    private function normalize_persian_string($string) {
        // Replace common Arabic characters with Persian equivalents for better matching.
        $string = str_replace(['ي', 'ك', 'آ'], ['ی', 'ک', 'ا'], $string);
        // Remove non-breaking spaces and trim whitespace from the beginning and end.
        $string = trim(str_replace('&nbsp;', ' ', $string));
        return $string;
    }

    private function load_iran_data() {
        // Ensure WooCommerce is active to avoid fatal errors.
        if ( ! class_exists('WooCommerce') ) {
            return ['states' => [], 'cities' => []];
        }

        // Get the official list of states from WooCommerce for Iran.
        // This returns an array like: [ 'TEH' => 'تهران', 'KHZ' => 'خوزستان', ... ]
        $wc_states = WC()->countries->get_states('IR');

        if (empty($wc_states)) {
            return ['states' => [], 'cities' => []];
        }

        // Load the city data from our custom JSON file.
        $json_file = plugin_dir_path(__FILE__) . 'assets/data/iran_data-2.json';
        if (!file_exists($json_file)) {
            // If the city file is missing, still return the official states for the dropdown.
            return ['states' => $wc_states, 'cities' => []];
        }
        $custom_data = json_decode(file_get_contents($json_file), true);

        // Create a reverse map of normalized state names to state codes for robust lookup.
        // e.g., [ 'تهران' => 'TEH', 'خوزستان' => 'KHZ', ... ]
        $normalized_name_to_code_map = [];
        foreach ($wc_states as $code => $name) {
            $normalized_name_to_code_map[$this->normalize_persian_string($name)] = $code;
        }

        $cities = [];
        if (is_array($custom_data)) {
            foreach ($custom_data as $province) {
                if (isset($province['name']) && isset($province['cities'])) {
                    $normalized_province_name = $this->normalize_persian_string($province['name']);
                    // Find the official WooCommerce code for the current province name.
                    if (isset($normalized_name_to_code_map[$normalized_province_name])) {
                        $state_code = $normalized_name_to_code_map[$normalized_province_name];
                        // Use the official state code as the key for the cities array.
                        $cities[$state_code] = $province['cities'];
                    }
                }
            }
        }

        // Return the official WC states for the dropdown and the cities keyed by official codes.
        return ['states' => $wc_states, 'cities' => $cities];
    }

    public function modify_checkout_fields( $fields ) {
        $iran_data = $this->load_iran_data();

        // --- Define our NEW custom fields ---
        $custom_fields = [
            'billing_invoice_request' => [
                'type' => 'checkbox',
                'label' => 'درخواست صدور فاکتور رسمی',
                'class' => ['form-row-wide'],
                'priority' => 1,
            ],
            'billing_person_type' => [
                'type' => 'select',
                'label' => 'نوع شخص',
                'class' => ['form-row-wide'],
                'options' => ['' => 'انتخاب کنید', 'real' => 'حقیقی', 'legal' => 'حقوقی'],
                'priority' => 10,
            ],
            'billing_national_code' => [
                'label' => 'کد ملی',
                'class' => ['form-row-wide'],
                'placeholder' => '۱۰ رقم بدون خط تیره',
                'priority' => 22,
            ],
            'billing_company_name' => [
                'label' => 'نام شرکت',
                'class' => ['form-row-first'],
                'priority' => 30,
            ],
            'billing_economic_code' => [
                'label' => 'شناسه ملی/اقتصادی',
                'class' => ['form-row-last'],
                'priority' => 31,
            ],
            'billing_agent_first_name' => [
                'label' => 'نام نماینده',
                'class' => ['form-row-first'],
                'priority' => 32,
            ],
            'billing_agent_last_name' => [
                'label' => 'نام خانوادگی نماینده',
                'class' => ['form-row-last'],
                'priority' => 33,
            ],
            'billing_custom_state' => [
                'type' => 'select',
                'label' => __('استان', 'woocommerce'),
                'options' => [ '' => 'انتخاب کنید' ] + $iran_data['states'],
                'class' => ['form-row-first'],
                'priority' => 40,
                'required' => true,
            ],
            'billing_custom_city' => [
                'type' => 'select',
                'label' => __('شهر', 'woocommerce'),
                'options' => [ '' => 'ابتدا استان را انتخاب کنید' ],
                'class' => ['form-row-last'],
                'priority' => 41,
                'required' => true,
            ],
        ];

        $fields['billing'] = array_merge($fields['billing'], $custom_fields);

        // --- Modify the ORIGINAL WooCommerce fields ---
        // As per user, do not touch the logic of state/city fields. Only reorder them.
        $fields['billing']['billing_state']['class'][] = 'ccif-hidden-field';
        $fields['billing']['billing_city']['class'][] = 'ccif-hidden-field';

        // Set priorities for standard fields and make name fields optional
        $fields['billing']['billing_first_name']['priority'] = 20;
        $fields['billing']['billing_first_name']['required'] = false;
        $fields['billing']['billing_last_name']['priority'] = 21;
        $fields['billing']['billing_last_name']['required'] = false;

        $fields['billing']['billing_address_1']['label'] = 'آدرس خیابان';
        $fields['billing']['billing_address_1']['placeholder'] = 'آدرس کامل خیابان، کوچه، پلاک، واحد';
        $fields['billing']['billing_address_1']['priority'] = 50;

        $fields['billing']['billing_postcode']['label'] = 'کدپستی';
        $fields['billing']['billing_postcode']['placeholder'] = 'بدون فاصله و با اعداد انگلیسی';
        $fields['billing']['billing_postcode']['priority'] = 60;

        $fields['billing']['billing_phone']['priority'] = 61;

        // --- Unset fields we don't need at all ---
        unset($fields['billing']['billing_company']);
        unset($fields['billing']['billing_address_2']);

        // --- Reorder All Billing Fields ---
        uasort($fields['billing'], 'wc_checkout_fields_uasort_comparison');

        return $fields;
    }

    public function enqueue_assets() {
        if ( ! is_checkout() && ! is_wc_endpoint_url( 'view-order' ) && ! is_order_received_page() ) return;
        wp_enqueue_script( 'ccif-checkout-js', plugin_dir_url( __FILE__ ) . 'assets/js/ccif-checkout.js', ['jquery'], '6.0', true );
        wp_localize_script( 'ccif-checkout-js', 'ccifData', [ 'cities' => $this->load_iran_data()['cities'] ] );
        wp_enqueue_style( 'ccif-checkout-css', plugin_dir_url( __FILE__ ) . 'assets/css/ccif-checkout.css', [], '6.0' );
    }

    public function display_custom_fields_on_order_pages( $order ) {
        if ( ! is_a( $order, 'WC_Order' ) ) {
            $order = wc_get_order( $order );
        }
        if ( ! $order ) {
            return;
        }

        // Only show this section if an invoice was requested.
        if ( ! $order->get_meta( '_billing_invoice_request' ) ) {
            return;
        }

        $person_type = $order->get_meta( '_billing_person_type' );
        $person_type_label = $person_type === 'real' ? 'حقیقی' : ( $person_type === 'legal' ? 'حقوقی' : '' );

        $fields_to_display = [ 'نوع شخص' => $person_type_label ];

        if ( $person_type === 'real' ) {
            $fields_to_display['نام'] = $order->get_billing_first_name();
            $fields_to_display['نام خانوادگی'] = $order->get_billing_last_name();
            $fields_to_display['کد ملی'] = $order->get_meta( '_billing_national_code' );
        } elseif ( $person_type === 'legal' ) {
            $fields_to_display['نام شرکت'] = $order->get_meta( '_billing_company_name' );
            $fields_to_display['شناسه ملی/اقتصادی'] = $order->get_meta( '_billing_economic_code' );
            $fields_to_display['نام نماینده'] = $order->get_meta( '_billing_agent_first_name' );
            $fields_to_display['نام خانوادگی نماینده'] = $order->get_meta( '_billing_agent_last_name' );
        }

        // Add the formatted billing address
        $address = $order->get_formatted_billing_address();
        if ( $address ) {
            $fields_to_display['آدرس صورتحساب'] = $address;
        }

        $fields_to_display = array_filter( $fields_to_display );

        if ( empty( $fields_to_display ) ) {
            return;
        }

        echo '<div class="ccif-order-details-box">';
        echo '<h2 class="ccif-invoice-requested-title">اطلاعات فاکتور</h2>';
        echo '<table class="woocommerce-table ccif-invoice-table"><tbody>';
        foreach ( $fields_to_display as $label => $value ) {
            // Use wp_kses_post for address to allow <br> tags, and esc_html for everything else.
            $formatted_value = ( $label === 'آدرس صورتحساب' ) ? wp_kses_post( $value ) : esc_html( $value );
            echo '<tr><th>' . esc_html( $label ) . ':</th><td>' . $formatted_value . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function display_custom_fields_in_admin_order( $order ) {
        if ( ! $order->get_meta( '_billing_invoice_request' ) ) {
            return;
        }

        echo '<div class="order_data_column">';
        echo '<h4 class="ccif-invoice-requested-title">' . __( 'اطلاعات تکمیلی فاکتور', 'ccif-iran-checkout' ) . '</h4>';

        $person_type = $order->get_meta( '_billing_person_type' );
        $person_type_label = $person_type === 'real' ? 'حقیقی' : ( $person_type === 'legal' ? 'حقوقی' : '' );

        $fields_to_display = [ 'نوع شخص' => $person_type_label ];

        if ( $person_type === 'real' ) {
            $fields_to_display['کد ملی'] = $order->get_meta( '_billing_national_code' );
        } elseif ( $person_type === 'legal' ) {
            $fields_to_display['نام شرکت'] = $order->get_meta( '_billing_company_name' );
            $fields_to_display['شناسه ملی/اقتصادی'] = $order->get_meta( '_billing_economic_code' );
            $fields_to_display['نام نماینده'] = $order->get_meta( '_billing_agent_first_name' );
            $fields_to_display['نام خانوادگی نماینده'] = $order->get_meta( '_billing_agent_last_name' );
        }

        foreach ( $fields_to_display as $label => $value ) {
            if ( ! empty( $value ) ) {
                echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
            }
        }
        echo '</div>';
    }

    // All old layout functions are removed. The layout will be handled by a template override.

    public function add_invoice_details_column_to_admin_orders_list( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            // Insert our columns before the 'order_date' column for better positioning.
            if ( $key === 'order_date' ) {
                $new_columns['invoice_request'] = __( 'فاکتور رسمی', 'ccif-iran-checkout' );
                $new_columns['person_type'] = __( 'نوع شخص', 'ccif-iran-checkout' );
            }
            $new_columns[ $key ] = $value;
        }
        return $new_columns;
    }

    // Wrapper for the legacy (non-HPOS) hook, which passes post_id
    public function populate_invoice_details_column_legacy( $column, $post_id ) {
        $this->populate_invoice_details_column( $column, wc_get_order( $post_id ) );
    }

    // Main function for populating columns (HPOS-compatible, accepts WC_Order object)
    public function populate_invoice_details_column( $column, $order ) {
        if ( ! $order ) return;

        switch ( $column ) {
            case 'invoice_request':
                if ( $order->get_meta( '_billing_invoice_request' ) ) {
                    echo '<strong style="color: #d63638;">' . __( 'بله', 'ccif-iran-checkout' ) . '</strong>';
                } else {
                    echo __( 'خیر', 'ccif-iran-checkout' );
                }
                break;

            case 'person_type':
                $person_type = $order->get_meta( '_billing_person_type' );
                if ( $person_type === 'real' ) {
                    echo __( 'حقیقی', 'ccif-iran-checkout' );
                } elseif ( $person_type === 'legal' ) {
                    echo __( 'حقوقی', 'ccif-iran-checkout' );
                } else {
                    echo $order->get_meta( '_billing_invoice_request' ) ? '<i>نامشخص</i>' : '—';
                }
                break;
        }
    }

    private function log_message( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                $logger = wc_get_logger();
                $logger->debug( $message, [ 'source' => 'ccif-iran-checkout' ] );
            }
        }
    }
}

new CCIF_Iran_Checkout_Rebuild();
