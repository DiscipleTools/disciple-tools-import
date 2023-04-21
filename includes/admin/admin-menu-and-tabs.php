<?php
/**
 * Disciple_Tools_Import_Menu class for the admin page
 *
 * @class       Disciple_Tools_Import_Menu
 * @version     0.1.0
 * @since       0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Initialize menu class
 */
Disciple_Tools_Import_Menu::instance();

/**
 * Class Disciple_Tools_Import_Menu
 */
class Disciple_Tools_Import_Menu {

    public $token = 'disciple_tools_import';

    private static $_instance = null;

    /**
     * Disciple_Tools_Import_Menu Instance
     *
     * Ensures only one instance of Disciple_Tools_Import_Menu is loaded or can be loaded.
     *
     * @since 0.1.0
     * @static
     * @return Disciple_Tools_Import_Menu instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    /**
     * Constructor function.
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    } // End __construct()

    /**
     * Loads the subnav page
     * @since 0.1
     */
    public function register_menu() {
        add_submenu_page( 'dt_extensions', __( 'Import', 'disciple_tools_import' ), __( 'Import', 'disciple_tools_import' ), 'manage_dt', $this->token, [ $this, 'content' ] );
    }

    /**
     * Menu stub. Replaced when Disciple.Tools Theme fully loads.
     */
    public function extensions_menu() {}

    private static function dt_sanitize_post_request_array_field( $post, $key ) {
        if ( !isset( $post[$key] ) ){
            return false;
        }
        $post[$key] = disciple_tools_import_sanitize_array( $post[$key] );
        return $post[$key];
    }

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( !current_user_can( 'manage_dt' ) ) { // manage dt is a permission that is specific to Disciple.Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
        }

        $run = true;
        if ( !is_admin() ) {
            $run = false;
        } //check for admin
        $timestamp = time();

        ?>
        <div class="wrap">
            <h2><?php esc_attr_e( 'Import', 'disciple_tools_import' ) ?></h2>
            <h2 class="nav-tab-wrapper">

            <?php
            $post_types = DT_Posts::get_post_types();
            $post_types = array_values( array_diff( $post_types, [ 'peoplegroups' ] ) ); //skip people groups for now.
            $active_post_type_object = null;

            if ( isset( $_GET['tab'] ) ) {
                $tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
            } else {
                $tab = $post_types[0];
            }

            foreach ( $post_types as $post_type ):
                $import_object = new DT_Import( $post_type );
                $my_tab_name = $import_object->get_post_name();
                $my_tab_label = $import_object->get_post_label();
                $my_tab_link = "admin.php?page={$this->token}&tab={$my_tab_name}";

                if ( $tab == $my_tab_name ):
                    $active_post_type_object = $import_object;
                endif;
                ?>
                <a href="<?php echo esc_html( $my_tab_link ) ?>"
                   class="nav-tab <?php ( $tab == $my_tab_name ) ? esc_attr_e( 'nav-tab-active', 'disciple_tools_import' ) : print ''; ?>">
                    <?php echo esc_attr( $my_tab_label ) ?>
                </a>
                <?php
            endforeach;
            ?>

            </h2>

            <?php
            $active_post_type_object->content();
            ?>

        </div><!-- End wrap -->

        <?php
    }
}

/**
 * Class Disciple_Tools_Import_Tab_General
 */
class Disciple_Tools_Import_Tab_General
{
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php $this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function main_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Header</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    Content
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Information</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <p><?php esc_html_e( 'use utf-8 file format', 'disciple_tools' ) ?></p>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

}

function disciple_tools_import_sanitize_array( &$array ) {
    foreach ( $array as &$value ) {
        if ( !is_array( $value ) ) {
            if ( ! disciple_tools_import_keep_unsanitized( $value ) ) {
                $value = sanitize_text_field( wp_unslash( $value ) );
            }
        } else {
            disciple_tools_import_sanitize_array( $value );
        }
    }
    return $array;
}

function disciple_tools_import_array_utf8_decode( &$array ) {
    foreach ( $array as &$value ) {
        if ( ! is_array( $value ) ) {
            $value = utf8_decode( $value );
        } else {
            disciple_tools_import_array_utf8_decode( $value );
        }
    }

    return $array;
}
function disciple_tools_import_keep_unsanitized( $value ): bool {
    $unsanitized = [ '<19', '<26', '<41', '>41' ];

    return in_array( $value, $unsanitized );
}
