<?php
/*
	Plugin Name: WooCommerce Variable Checkout
	Description: Custom checkout using variable product name, amount and details
	Version: 1.0.0
	Author: John Jason Q. Taladro
	Author URI: http://goldcoastmultimedia.com/
	Requires at least: 3.1
	Tested up to: 4.0.1

Copyright: © 2009-2012 WooThemes.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );
  
/**
 * Check WooCommerce exists
 */
if ( is_woocommerce_active() ) {
  
  /**
   * WC_Variable_Checkout
   **/
  if ( ! class_exists( 'WC_Variable_Checkout' ) ) {
    
    class WC_Variable_Checkout {
      
      /**
       * @var array
       */
      var $errors = array();
    
      /**
       * Constructor
       */
      public function __construct() {
        if ( is_admin() ) {
          add_action( 'admin_init', array( $this, 'admin_init' ) );
          add_action( 'admin_menu', array( $this, 'admin_menu' ) );
          add_action( 'admin_print_styles', array( $this, 'admin_scripts' ) );
        }
        
        add_action( 'template_redirect', array( $this, 'do_variable_checkout' ) );
        add_filter( 'woocommerce_get_item_data', array( $this, 'wc_checkout_description' ), 10, 2 );
        
      }
      
      /**
       * Admin Init
       */
      public function admin_init() {
        if ( !empty($_GET['page']) && $_GET['page'] == 'wc_variable_checkout' ) {
          if ( $_POST )
            $this->save_variable_checkout();
        }
      }
      
      /**
       * Admin Menu
       */
      public function admin_menu() {
        $page = add_submenu_page('woocommerce', __( 'Variable Checkout', 'wc_variable_checkout' ), __( 'Variable Checkout', 'wc_variable_checkout' ), 'manage_woocommerce', 'wc_variable_checkout', array($this, 'admin_page') );
      }
      
      /**
       * Admin Page
       */
      public function admin_scripts() {
        global $woocommerce;
        
        /* custom js and css here */
      }
      
      /**
       * Admin Page
       */
      public function admin_page() {
      
        global $woocommerce;                
        ?>
        <div class="wrap woocommerce">
          <form id="mainform" method="post">
          
            <?php $this->display_error(); ?>
            
            <?php wp_nonce_field('nonce_variable_checkout','nonce_variable_checkout'); ?>
            <h3 class="title"><?php _e( 'Variable Item Checkout', 'wc_variable_checkout'); ?></h3>
            <p><?php _e( 'Sets variable product item for checkout. Useful when a product/item is not on the product lists and / or the site owner gives a special price for a customer. Only admin or shop manager can do this kind of checkout.', 'wc_variable_checkout' ); ?></p>
            <table class="form-table">
              <tbody>
                <?php $this->generate_field_row( 'wc_variable_checkout_product_name', array( 'label' => 'Product Name', 'type' => 'text', 'help_tip' => true, 'help_tip_text' => 'Enter product name.' ) ); ?>
                <?php $this->generate_field_row( 'wc_variable_checkout_amount', array( 'label' => 'Amount', 'type' => 'number', 'help_tip' => true, 'help_tip_text' => 'Enter total amount (excluding flat rate & gst).' ) ); ?>
                <?php $this->generate_field_row( 'wc_variable_checkout_details', array( 'label' => 'Details', 'type' => 'textarea', 'help_tip' => true, 'help_tip_text' => 'Additional information for the Item.' ) ); ?>
              </tbody>
            </table>
            <p class="submit">
              <input class="button-primary" type="submit" value="<?php _e( 'Go to checkout page', 'wc_variable_checkout' ); ?>" name="Save" />
            </p>
          </form>
        </div>
        <?php
      }
      
      /**
       * Generate table row field
       */
      public function generate_field_row( $fieldname, $settings = array( 'help_tip' => true ) ) {
        global $woocommerce;
        
        $settings['label'] = ( isset( $settings['label'] ) ) ? $settings['label'] : '';
        $settings['help_tip'] = ( isset( $settings['help_tip'] ) ) ? $settings['help_tip'] : false;
        $settings['help_tip_text'] = ( isset( $settings['help_tip_text'] ) ) ? $settings['help_tip_text'] : '';
        $settings['css'] = ( isset( $settings['css'] ) ) ? $settings['css'] : '';
        $settings['type'] = ( isset( $settings['type'] ) ) ? $settings['type'] : 'text';
        
      ?>
        <tr valign="top">
          <th scope="row" class="titledesc">
            <label for="<?php echo $fieldname; ?>"><?php echo $settings['label']; ?></label>
            <?php if ( $settings['help_tip'] ) : ?>
            <img class="help_tip" data-tip="<?php echo $settings['help_tip_text']; ?>" src="<?php 
              echo $woocommerce->plugin_url; ?>/assets/images/help.png" height="16" width="16" />
            <?php endif; ?>
          </th>
          <td class="forminp">
            <fieldset>
              <legend class="screen-reader-text"><span><?php echo $settings['label']; ?></span></legend>
              <?php 
              switch ( $settings['type'] ):
                case 'number': ?>
                <input class="input-text regular-input" type="number" name="<?php echo $fieldname; ?>" id="<?php echo $fieldname; ?>" style="<?php 
                  echo $settings['css']; ?>" step="any" min="0" placeholder="0.00" />
                  <?php break;
                case 'textarea': ?>
                <textarea rows="3" cols="20" class="input-text wide-input" name="<?php echo $fieldname; ?>" id="<?php echo $fieldname; ?>" placeholder="<?php 
                  echo $settings['label']; ?>" style="<?php echo $settings['css']; ?>"></textarea>
                    <?php break; 
                default: ?>
                <input class="input-text regular-input " type="text" name="<?php echo $fieldname; ?>" id="<?php echo $fieldname; ?>" style="<?php 
                  echo $settings['css']; ?>" value="" placeholder="<?php echo $settings['label']; ?>" />
                    <?php break;
              endswitch; ?>
            </fieldset>
          </td>
        </tr>
      <?php
      }
      
      /**
       * Save variable checkout, creates new product with private status
       */
      public function save_variable_checkout() {
        if ( ! isset( $_POST['nonce_variable_checkout'] ) || ! wp_verify_nonce( $_POST['nonce_variable_checkout'], 'nonce_variable_checkout' ) ) {
           $this->errors[] = __( 'Sorry, security check did not verify.', 'wc_variable_checkout' );
        }        
        if ( ! isset( $_POST['wc_variable_checkout_product_name'] ) || trim( $_POST['wc_variable_checkout_product_name'] ) == '' ) {
          $this->errors[] = __( 'Product Name is required.', 'wc_variable_checkout' );
        }
        if ( ! isset( $_POST['wc_variable_checkout_amount'] ) 
          || ! is_numeric( $_POST['wc_variable_checkout_amount'] ) 
          || (int) $_POST['wc_variable_checkout_amount'] > 0 ) {
          $this->errors[] = __( 'Amount must be numeric and no less than 0.', 'wc_variable_checkout' );
        }
        
        
        if ( ! empty( $this->errors ) ) {
          $post = array(
            'post_content' => esc_textarea( $_POST['wc_variable_checkout_details'] ),
            'post_status' => "private",
            'post_title' => sanitize_text_field( $_POST['wc_variable_checkout_product_name'] ),
            'post_parent' => '',
            'post_type' => "product",
          );
          
          //Create post
          $post_id = wp_insert_post( $post );
          if ( $post_id ) {
            wp_set_object_terms($post_id, 'simple', 'product_type');

            update_post_meta( $post_id, '_visibility', 'visible' );
            update_post_meta( $post_id, '_stock_status', 'instock');
            update_post_meta( $post_id, 'total_sales', '0');
            update_post_meta( $post_id, '_downloadable', 'no');
            update_post_meta( $post_id, '_virtual', 'no');
            update_post_meta( $post_id, '_product_image_gallery', '');
            update_post_meta( $post_id, '_regular_price', (float) $_POST['wc_variable_checkout_amount'] );
            update_post_meta( $post_id, '_sale_price', "" );
            update_post_meta( $post_id, '_tax_status', "taxable" );
            update_post_meta( $post_id, '_tax_class', "" );
            update_post_meta( $post_id, '_purchase_note', "" );
            update_post_meta( $post_id, '_featured', "no" );
            update_post_meta( $post_id, '_weight', "" );
            update_post_meta( $post_id, '_length', "" );
            update_post_meta( $post_id, '_width', "" );
            update_post_meta( $post_id, '_height', "" );
            update_post_meta($post_id, '_sku', "");
            update_post_meta( $post_id, '_product_attributes', array());
            update_post_meta( $post_id, '_sale_price_dates_from', "" );
            update_post_meta( $post_id, '_sale_price_dates_to', "" );
            update_post_meta( $post_id, '_price', (float) $_POST['wc_variable_checkout_amount'] );
            update_post_meta( $post_id, '_sold_individually', "" );
            update_post_meta( $post_id, '_manage_stock', "no" );
            update_post_meta( $post_id, '_backorders', "no" );
            update_post_meta( $post_id, '_stock', "" );
            
            $product_url = get_permalink( $post_id );
            $param = array( 'action' => 'do_variable_checkout' );
            wp_safe_redirect( add_query_arg( $param, $product_url ) );
          }
        }
        
      }
      
      /**
       * Display error 
       */
      public function display_error() {
        if ( ! empty( $this->errors ) ) : ?>
          <div class="error">
            <ul>
              <?php foreach( $this->errors as $error ) : ?>
              <li><strong><?php echo $error; ?></strong></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif;
      }
      
      /**
       * Adds new product to cart, and redirect to checkout page
       */
      public function do_variable_checkout() {
        if ( ! is_admin() && is_product() && current_user_can( 'manage_woocommerce' ) && $_GET['action'] === "do_variable_checkout" ) {
          global $post, $woocommerce;
          $product_id = $post->ID;
          
          $this->add_product_to_cart( $product_id );
          wp_safe_redirect( $woocommerce->cart->get_checkout_url() );
        }
      }
      
      /**
       * Clear Cart
       */ 
      public function clear_cart() {
        global $woocommerce;
        $woocommerce->cart->empty_cart();
      }
            
      /**
       * Add product to cart
       */
      public function add_product_to_cart( $product_id ) {
        global $woocommerce;   
        
        //check if cart has products
        if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {
          // Empty cart
          $this->clear_cart();          
        }
        
        // if no products in cart, add it
        $woocommerce->cart->add_to_cart( $product_id );        
      }
      
      /**
       * Edit product display on cart to include description
       */
      public function wc_checkout_description( $other_data, $cart_item ) {
        $post_data = get_post( $cart_item['product_id'] );
        $other_data[] = array( 'name' =>  $post_data->post_content );
        return $other_data;
      }
      
    }
  }
  
  new WC_Variable_Checkout();
}