<?php
/**
 * DT_Import_Export_Menu class for the admin page
 *
 * @class       DT_Import_Export_Menu
 * @version     0.1.0
 * @since       0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Initialize menu class
 */
DT_Import_Export_Menu::instance();

/**
 * Class DT_Import_Export_Menu
 */
class DT_Import_Export_Menu {

    public $token = 'dt_import_export';

    private static $_instance = null;

    /**
     * DT_Import_Export_Menu Instance
     *
     * Ensures only one instance of DT_Import_Export_Menu is loaded or can be loaded.
     *
     * @since 0.1.0
     * @static
     * @return DT_Import_Export_Menu instance
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
        add_action( "admin_menu", array( $this, "register_menu" ) );
    } // End __construct()

    /**
     * Loads the subnav page
     * @since 0.1
     */
    public function register_menu() {
        add_menu_page( __( 'Extensions (DT)', 'disciple_tools' ), __( 'Extensions (DT)', 'disciple_tools' ), 'manage_dt', 'dt_extensions', [ $this, 'extensions_menu' ], 'dashicons-admin-generic', 59 );
        add_submenu_page( 'dt_extensions', __( 'Import and Export', 'dt_import_export' ), __( 'Import and Export', 'dt_import_export' ), 'manage_dt', $this->token, [ $this, 'content' ] );
    }

    /**
     * Menu stub. Replaced when Disciple Tools Theme fully loads.
     */
    public function extensions_menu() {}


    private static function dt_sanitize_array( &$array ) {
        foreach ( $array as &$value ) {
            if ( !is_array( $value ) ) {
                $value = sanitize_text_field( wp_unslash( $value ) );
            } else {
                dt_sanitize_array( $value );
            }
        }
        return $array;
    }

    private static function dt_sanitize_post( $post, $key ) {
        if ( !isset( $post[$key] ) ){
            return false;
        }
        $post[$key] = self::dt_sanitize_array( $post[$key] );
        return $post[$key];
    }

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( !current_user_can( 'manage_dt' ) ) { // manage dt is a permission that is specific to Disciple Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
        }
        if ( isset( $_GET["tab"] ) ) {
            $tab = sanitize_key( wp_unslash( $_GET["tab"] ) );
        } else {
            //$tab = 'general';
            $tab = 'contact';
        }

        $link = 'admin.php?page='.$this->token.'&tab=';
        $run = true;
        if ( !is_admin() ) {
            $run = false;
        } //check for admin
        $timestamp = time();

        ?>
        <div class="wrap">
            <h2><?php esc_attr_e( 'Import and Export', 'dt_import_export' ) ?></h2>
            <h2 class="nav-tab-wrapper">

            <?php
            foreach ( [
                //'general' => ['tab' => 'general', 'label' => 'General'],
                //'second' => ['tab' => 'second', 'label' => 'Second'],
                'contact' => [
                    'tab' => 'contact',
                    'label' => 'Contact'
                ],
                'location' => [
                    'tab' => 'location',
                    'label' => 'Location'
                ]
            ] as $mytab):
                $mytab_name = "{$mytab['tab']}";
                $mytab_label = "{$mytab['label']}";
                $mytab_link = "admin.php?page={$this->token}&tab={$mytab_name}";
                ?>

                <a href="<?php echo esc_html( $mytab_link ) ?>"
                   class="nav-tab <?php ( $tab == $mytab_name ) ? esc_attr_e( 'nav-tab-active', 'dt_import_export' ) : print ''; ?>">
                <?php echo esc_attr( $mytab_label ) ?>
                </a>

            <?php endforeach; ?>

            </h2>

            <?php

            if ( $tab == 'general' ) {
                $object = new DT_Import_Export_Tab_General();
                $object->content();
            } else if ( $tab == 'second' ) {
                $object = new DT_Import_Export_Tab_Second();
                $object->content();

            } else if ( $tab == 'contact' ) {
                $object = new DT_Import_Export_Tab_Contact();
                //$object->content();

                if ( isset( $_POST['csv_import_nonce'] )
                            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_import_nonce'] ) ), 'csv_import' )
                            && $run ) {

                    if ( isset( $_FILES["csv_file"]["name"] ) ) {
                        $temp_name = isset( $_FILES["csv_file"]["tmp_name"] ) ? sanitize_text_field( wp_unslash( $_FILES["csv_file"]["tmp_name"] ) ) : '';
                        $file_parts = explode( ".", sanitize_text_field( wp_unslash( $_FILES["csv_file"]["name"] ) ) )[ count( explode( ".", sanitize_text_field( wp_unslash( $_FILES["csv_file"]["name"] ) ) ) ) - 1 ];
                        if ( isset( $_FILES["csv_file"]["error"] ) && $_FILES["csv_file"]["error"] > 0 ) {
                            esc_html_e( "ERROR UPLOADING FILE", 'disciple_tools' );
                            $object->go_back();
                            exit;

                        } else if ( $file_parts != 'csv' ) {
                            esc_html_e( "NOT CSV", 'disciple_tools' );
                            $object->go_back();
                            exit;

                        } else if ( mb_detect_encoding( file_get_contents( $temp_name, false, null, 0, 100 ), 'UTF-8', true ) === false ) {
                            esc_html_e( "FILE IS NOT UTF-8", 'disciple_tools' );
                            $object->go_back();
                            exit;
                        }


                        //upload file to server
                        //echo plugin_dir_path(__FILE__); ///home/bicomau/public_html/wpdt/wp-content/plugins/disciple-tools-import-export/includes/admin/
                        //echo plugin_dir_url(__FILE__); //https://bicom.shalomsoft.com/wpdt/wp-content/plugins/disciple-tools-import-export/includes/admin/

                        $path = plugin_dir_path( __FILE__ ).'../../uploads/'.$timestamp;
                        if ( !file_exists( $path ) ) { mkdir( $path, 0777, true ); }

                        $basename = sanitize_text_field( wp_unslash( $_FILES["csv_file"]['name'] ) );
                        $source = $temp_name;
                        $destination = "{$path}/{$basename}";
                        move_uploaded_file( $source, $destination );
                        //
                        //$filename   = uniqid() . "_" . $timestamp; // 5dab1961e93a7_1571494241
                        //$extension  = pathinfo( $_FILES["csv_file"]["name"], PATHINFO_EXTENSION ); // csv
                        //$basename   = $filename . '.' . $extension; // 5dab1961e93a7_1571494241.csv
                        //
                        //$source = $_FILES["csv_file"]["tmp_name"];
                        //$destination = "{$path}/{$basename}";
                        //if (!move_uploaded_file( $source, $destination ) ){
                        //    throw new RuntimeException('Failed to move uploaded file.');
                        //}

                        $file_source = null;
                        if ( isset( $_POST['csv_source'] ) ) {
                            $file_source = sanitize_text_field( wp_unslash( $_POST['csv_source'] ) );
                        }

                        $file_assigned_to = null;
                        if ( isset( $_POST['csv_assign'] ) ) {
                            $file_assigned_to = sanitize_text_field( wp_unslash( $_POST['csv_assign'] ) );
                        }

                        $object->mapping( $destination, $file_source, $file_assigned_to );

                    } /**end-of condition --isset( $_FILES["csv_file"] )-- */

                } else if ( isset( $_POST['csv_mappings_nonce'] )
                            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_mappings_nonce'] ) ), 'csv_mappings' )
                            && $run ) {

                    if ( isset( $_POST["csv_mapper"], $_POST["csv_data"] ) ) {

                        $mapping_data = self::dt_sanitize_post( $_POST, 'csv_mapper' );
                        $value_mapperi_data = isset( $_POST['VMD'] ) ? self::dt_sanitize_post( $_POST, 'VMD' ) : [];
                        $value_mapper_data = isset( $_POST['VM'] ) ? self::dt_sanitize_post( $_POST, 'VM' ) : [];

                        //$mapping_data = unserialize( base64_decode( $_POST["csv_mapper"] ) );
                        $csv_data = unserialize( base64_decode( sanitize_text_field( wp_unslash( $_POST["csv_data"] ) ) ) );

                        $csv_headers = '';
                        if ( isset( $_POST["csv_headers"] ) ) {
                            $csv_headers = unserialize( base64_decode( sanitize_text_field( wp_unslash( $_POST["csv_headers"] ) ) ) );
                        }

                        //$temp_contacts_data
                        $delimeter = ',';
                        if ( isset( $_POST["csv_delimeter_temp"] ) ) {
                            $delimeter = sanitize_text_field( wp_unslash( $_POST['csv_delimeter_temp'] ) );
                        }

                        $multiseperator = ';';
                        if ( isset( $_POST["csv_multiseperator_temp"] ) ) {
                            $multiseperator = sanitize_text_field( wp_unslash( $_POST['csv_multiseperator_temp'] ) );
                        }

                        $filepath = '';
                        if ( isset( $_POST["csv_file_path"] ) ) {
                            $filepath = sanitize_text_field( wp_unslash( $_POST['csv_file_path'] ) );
                        }

                        $file_source = '';
                        if ( isset( $_POST["csv_source_temp"] ) ) {
                            $file_source = sanitize_text_field( wp_unslash( $_POST['csv_source_temp'] ) );
                        }

                        $file_assigned_to = '';
                        if ( isset( $_POST["csv_assign_temp"] ) ) {
                            $file_assigned_to = sanitize_text_field( wp_unslash( $_POST['csv_assign_temp'] ) );
                        }

                        $object->preview( $filepath, $csv_data, $csv_headers, $delimeter, $multiseperator, $file_source, $file_assigned_to, $mapping_data, $value_mapperi_data, $value_mapper_data );

                    }
////////////////////////////////////////////////////////////////////////////////
                } else if ( isset( $_POST['csv_correct_nonce'] )
                            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_correct_nonce'] ) ), 'csv_correct' )
                            && $run ) {

                    //@codingStandardsIgnoreLine
                    if ( isset( $_POST["csv_contacts"] ) ) {
                        //@codingStandardsIgnoreLine
                        $object->insert_contacts( unserialize( base64_decode( $_POST["csv_contacts"] ) ) );
                    }
                    exit;

                } else {
                    $object->content();
                }
            }
            ?>

        </div><!-- End wrap -->

        <?php
    }
}

/**
 * Class DT_Import_Export_Tab_General
 */
class DT_Import_Export_Tab_General
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
            <th>Header</th>
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
            <th>Information</th>
            </thead>
            <tbody>
            <tr>
                <td>
                    <p><?php esc_html_e( "use utf-8 file format", 'disciple_tools' ) ?></p>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

}

/**
 * Class DT_Import_Export_Tab_Second
 */
class DT_Import_Export_Tab_Second
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
            <th>Header</th>
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
            <th>Information</th>
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
}

class DT_Import_Export_Tab_Test{
    public function content(){
        //Disciple_Tools_Contacts::get_all_default_values();
    }
}

/**
 * Class DT_Import_Export_Tab_General
 */
class DT_Import_Export_Tab_Contact {

    public static $contact_phone_headings;
    public static $contact_address_headings;
    public static $contact_email_headings;
    public static $contact_name_headings;
    public static $contact_gender_headings;
    public static $contact_notes_headings;

    public function __construct() {
        self::$contact_phone_headings = [
                                'contact_phone',
                                'phone',
                                'mobile',
                                'telephone',
        ];

        self::$contact_address_headings = [
                                'contact_address',
                                'address'
        ];

        self::$contact_email_headings = [
                                'contact_email',
                                'email',
                                'email_address',
        ];

        self::$contact_name_headings = [
                                'title',
                                'name',
                                'contact_name',
        ];
        self::$contact_gender_headings = [
                                'gender',
                                'sex',
        ];

        self::$contact_notes_headings = [
                                'cf_notes',
                                'note',
                                'notes',
                                'comment',
                                'comments'
        ];

    }

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
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <th><h1><?php echo esc_html_e( "Step 1: Upload File", 'disciple_tools' ); ?></h1></th>
            </thead>
            <tbody>
            <tr>
                <td>

                    
<form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field( 'csv_import', 'csv_import_nonce' ); ?>
    <table class="widefat striped">
        <tr>
            <td>
                <label for="csv_file"><?php esc_html_e( 'Select your csv file (comma seperated file)' ) ?></label><br>
                <input class="button" type="file" name="csv_file" id="csv_file" />
            </td>
        </tr>
        <tr style="display:none">
            <td>
                <label for="csv_multivalued"><?php esc_html_e( 'Does the file contain multivalues?' ) ?></label><br>
                <?php /** <input class="checkbox" type="checkbox" name="csv_multivalued" id="csv_multivalued" onclick="setDel()" disabled="disabled" /> */ ?>
                <input class="checkbox" type="checkbox" name="csv_multivalued" id="csv_multivalued" onclick="setDel()" disabled="disabled" checked="checked" />
            </td>
        </tr>
        <tr style="display:none">
            <td>
                <label for="csv_del"><?php esc_html_e( "Add csv delimiter (default is fine)", 'disciple_tools' ) ?></label><br>
                <?php /** <input type="text" name="csv_del" id="csv_del" value=',' size=2 />*/ ?>
                <select name="csv_del" id="csv_del" onclick="deactMV()" disabled="disabled">
                    <option value="," selected="selected">, comma</option>
                    <option value="|">| tab</option>
                    <option value=";">; semi-colon</option>
                    <option value=":">: colon</option>
                    <option value="$">$ dollar</option>
                </select>
            </td>
        </tr>
        <tr style="display:none">
            <td>
                <label for="csv_header"><?php esc_html_e( "Does the file have a header? (i.e. a first row with the names of the columns?)", 'disciple_tools' ) ?></label><br>
                <select name="csv_header" id="csv_header" readonly="readonly" disabled="disabled">
                    <option value=yes><?php esc_html_e( "yes", 'disciple_tools' ) ?></option>
                    <option value=no><?php esc_html_e( "no", 'disciple_tools' ) ?></option>
                </select>

            </td>
        </tr>
        <tr>
            <td>
                <label for="csv_source">
                    <?php esc_html_e( "Where did these contacts come from? Add a source.", 'disciple_tools' ) ?>
                </label><br>
                <select name="csv_source" id="csv_source">
                    <?php
                    $site_custom_lists = dt_get_option( 'dt_site_custom_lists' );
                    foreach ( $site_custom_lists['sources'] as $key => $value ) {
                        if ( $value['enabled'] ) {
                            ?>
                            <option value=<?php echo esc_html( $key ); ?>><?php echo esc_html( $value['label'] ); ?></option>
                            <?php
                        }
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>
                <label for="csv_assign">
                    <?php esc_html_e( "Which user do you want these assigned to?", 'disciple_tools' ) ?>
                </label><br>
                <select name="csv_assign" id="csv_assign">
                    <option value=""></option>
                    <?php
                    $args = [
                        'role__not_in' => [ 'registered' ],
                        'fields'       => [ 'ID', 'display_name' ],
                        'order'        => 'ASC',
                    ];
                    $users = get_users( $args );
                    foreach ( $users as $user ) { ?>
                        <option
                            value=<?php echo esc_html( $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
                    <?php } ?>
                </select>

            </td>
        </tr>
        <tr>
            <td>
                <p class="submit">
                    <input type="submit" 
                           name="submit" 
                           id="submit" 
                           class="button"
                           value=<?php esc_html_e( "Upload", 'disciple_tools' ) ?>>
                </p>
            </td>
        </tr>
    </table>
</form>
                    
                    
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
            <th>Information</th>
            </thead>
            <tbody>
            <tr>
                <td>
                    <p><?php esc_html_e( "use utf-8 file format", 'disciple_tools' ) ?></p>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function go_back() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <th>ERROR <?php echo esc_html( date( 'Y-m-d H:i:s' ) ) ?></th>
            </thead>
            <tbody>
            <tr>
                <td>
                    <a href="" class="button button-primary"> <?php esc_html_e( "Back", 'disciple_tools' ) ?> </a>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->

        <?php
    }

    public function mapping( $filepath, $file_source = 'web', $file_assigned_to = '' ) {

//echo "path:{$filepath}<br/>";
//echo "source:{$file_source}<br/>";
//echo "assignedTo:{$file_assigned_to}<br/>";

        $data = $this->mapping_process( $filepath, $file_source, $file_assigned_to );

        $delimeter = $data['delimeter'];
        $multi_separator = $data['multi_seperator'];

        $csv_headers = $data['csv_headers'];
        $con_headers_info = $data['con_headers_info'];
        $uploaded_file_headers = $data['uploade_file_headers'];
        $my_opt_fields = $data['my_opt_fields'];

        $temp_contacts_data = $data['temp_contacts_data'];
        $unique = $data['unique'];


        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <th>
                <h1><?php echo esc_html_e( "Step 2: Map Columns", 'disciple_tools' ); ?></h1>
            </th>
            </thead>

            <tbody>
                
            <?php /** <tr> <td> <pre><?php print_r($data); ?></pre> </td> </tr> */ ?>
            <tr> 
                <td>
                    <p><strong>Important!</strong> Data in Unmapped Columns will be skipped</p>

                    <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'csv_mappings', 'csv_mappings_nonce' ); ?>

                    <div class="mapper-table-container">
                    <table class="mapper-table">
                    <thead>
                        <tr> 
                            <th valign="top"> Source (Uploaded File) </th>
                            <th valign="top"> Destination (DT) </th> 
                            <th valign="top"> Unique Values/Mapper </th>
                        </tr>
                    </thead>


                    <tbody>
                    <?php

                    //correct csv headers
                    foreach ( $csv_headers as $ci => $ch ):

                        $col_data_type = isset( $my_opt_fields['fields'][$ch]['type'] ) ? $my_opt_fields['fields'][$ch]['type'] : null;

                        $mapper_title = '';
                        if ( isset( $con_headers_info[$ch]['name'] ) ) {
                            $mapper_title = $con_headers_info[$ch]['name'];
                        } else if ( $ch == 'title') {
                            //$mapper_title = 'Contact Name';
                            $mapper_title = ucwords( $ch );
                        } else {
                            //$mapper_title = "<span style=\"color:red\" title=\"un-mapped data column\">{$ch}</span>";
                            $mapper_title = "<span class=\"unmapped\" title=\"un-mapped data column\">{$ch}</span>";
                        }

                        ?>
                        <tr class="mapper-coloumn" data-row-id="<?php esc_html_e( "{$ci}", 'disciple_tools' ) ?>">

                            <th data-field="<?php esc_html_e( "{$ch}", 'disciple_tools' ) ?>" class="src-column">
                            <?php //= $mapper_title ?><?php echo esc_html( $uploaded_file_headers[$ci] ) ?>
                            </th>


                            <td class="dest-column">
                                <?php
                                $dd_params = [
                                                'name' => "csv_mapper[{$ci}]",
                                                'class' => 'cf-mapper',
                                                //'onchange' => "check_column_mappings({$ci})"
                                                'onchange' => "getDefaultValues({$ci})"
                                            ];
                                echo self::get_dropdown_list_html( $ch, "csv_mapper_{$ci}", $con_headers_info, $ch, $dd_params, true ) ?>
                                <?php /** <div id="helper-fields-<?php echo $ci ?>" class="helper-fields"<?php if ( $col_data_type!='key_select'): ?> style="display:none"<?php endif; ?>></div> */ ?>
                                <div id="helper-fields-<?php esc_attr_e( $ci, 'disciple_tools' ) ?>" class="helper-fields" style="display:none"></div>

                            </td>


                            <td class="mapper-column">
                            <div id="unique-values-<?php esc_attr_e( $ci, 'disciple_tools' ) ?>"
                                class="unique-values"
                                data-id="<?php esc_attr_e( $ci, 'disciple_tools' ) ?>"
                                data-type="<?php esc_attr_e( $col_data_type, 'disciple_tools' ) ?>"
                                <?php /** <?php if ( $col_data_type!='key_select' ) : ?> style="display:none"<?php endif; ?>> */ ?>
                                <?php if ( !( $col_data_type == 'key_select' || $col_data_type == 'multi_select' ) ) : ?> style="display:none"<?php endif; ?>>

                                <div class="mapper-helper-text">
                                    <span class="mapper-helper-title">Map import values to DT values</span><br/>
                                    <span class="mapper-helper-description">
                                        <span class="selected-mapper-column-name"><?php esc_html_e( "{$ch}", 'disciple_tools' ) ?><?php //echo $mapper_title ?></span>
                                        only accepts specific values (as a Selection). 
                                        Please map following unique values from your data to existing values in DT.
                                        You can add new values into the DT system if you want by first ...</span>
                                </div>

                                <?php if ( isset( $unique[$ci] ) ): ?>
                                <table>

                                <?php foreach ( $unique[$ci] as $vi => $v ): ?>

                                   <tr>
                                       <td>
                                            <?php if ( strlen( trim( $v ) ) > 0 ): ?>
                                                <?php esc_html_e( "{$v}", 'disciple_tools' ) ?>
                                            <?php else : ?>
                                                <span class="empty">-blank/null-</span>
                                            <?php endif; ?>
                                       </td>


                                       <td>

                                            <input name="VMD[<?php esc_attr_e( "{$ci}", 'disciple_tools' ) ?>][<?php esc_attr_e( "{$vi}", 'disciple_tools' ) ?>]" type="hidden" value="<?php esc_attr_e( "{$v}", 'disciple_tools' ) ?>" />

                                            <select id="value-mapper-<?php esc_attr_e( "{$ci}", 'disciple_tools' ) ?>-<?php esc_attr_e( "{$vi}", 'disciple_tools' ) ?>"
                                                    name="VM[<?php esc_attr_e( "{$ci}", 'disciple_tools' ) ?>][<?php esc_attr_e( "{$vi}", 'disciple_tools' ) ?>]"
                                                    class="value-mapper-<?php esc_attr_e( "{$ci}", 'disciple_tools' ) ?>"
                                                    data-value="<?php esc_attr_e( "{$v}", 'disciple_tools' ) ?>">
                                            <option>--Not Selected--</option>
                                            <?php /**/ ?>
                                            <?php if ( isset( $my_opt_fields['fields'][$ch]['default'] ) ): ?>
                                            <?php foreach ( $my_opt_fields['fields'][$ch]['default'] as $di => $dt ): ?>
                                                <option value="<?php esc_attr_e( "{$di}", 'disciple_tools' ) ?>"<?php if ( $di == $v ): ?> selected="selected"<?php endif; ?>><?php esc_attr_e( "{$dt['label']}", 'disciple_tools' ) ?></option>
                                            <?php endforeach; ?>
                                            <?php endif; ?> 
                                            <?php /***/ ?>
                                            </select>




                                       </td>
                                    </tr>

                                <?php endforeach; ?>

                                </table>

                                <?php endif; ?>
                            </div>
                            </td>


                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                    <tfoot>
                        <tr><td></td>
                            <td colspan="2">


                                <?php /** */ ?>
                                <input type="hidden" name="csv_data" value="<?php echo esc_html( base64_encode( serialize( $temp_contacts_data ) ) ); ?>">
                                <input type="hidden" name="csv_headers" value="<?php echo esc_html( base64_encode( serialize( $csv_headers ) ) ); ?>">
                                <?php /** */ ?>

                                <input type="hidden" name="csv_delimeter_temp" value='<?php echo esc_html( $delimeter ) ?>' />
                                <input type="hidden" name="csv_multiseperator_temp" value='<?php echo esc_html( $multi_separator ) ?>' />
                                <input type="hidden" name="csv_file_path" value='<?php echo esc_html( $filepath ) ?>' />
                                <input type="hidden" name="csv_source_temp" value='<?php echo esc_html( $file_source ) ?>' />
                                <input type="hidden" name="csv_assign_temp" value='<?php echo esc_html( $file_assigned_to ) ?>' />

                                <?php wp_nonce_field( 'csv_mapping', 'csv_mapping_nonce' ); ?>

                                <input type="submit" name="submit" id="submit" 
                                       style="background-color:#4CAF50; color:white" 
                                       class="button" 
                                       value=<?php esc_html_e( "Next", 'disciple_tools' ) ?>>

                            </td>
                        </tr>


                        <?php /** <tr>
                            <td colspan="3">
                                DELIMETER:<strong><?php echo $delimeter ?></strong><br/>
                                MULTI-SEPARATOR:<strong><?php echo $multi_separator ?></strong><br/>
                                FILE-PATH:<strong><?php echo $filepath ?></strong><br/>
                                SOURCE:<strong><?php echo $file_source ?></strong><br/>
                                ASSIGNED-TO:<strong><?php echo $file_assigned_to ?></strong><br/>
                            </td>
                        </tr> */ ?>
                    </tfoot>

                    </table>
                    </div>

                    <div class="helper-fields-txt" style="display:none">
                    <?php foreach ( $my_opt_fields['fields'] as $my_opt_field_index => $my_opt_field ): ?>
                    <div id="helper-fields-<?php echo esc_html( $my_opt_field_index ) ?>-txt" data-type="<?php echo esc_html( $my_opt_field['type'] ) ?>">

                        <span>Field: <strong><?php esc_html_e( "{$my_opt_field_index}", 'disciple_tools' ) ?></strong></span><br/>
                        <span>Type: <strong><?php esc_html_e( "{$my_opt_field['type']}", 'disciple_tools' ) ?></strong></span><br/>
                        <span>Description: <strong><?php esc_html_e( "{$my_opt_field['description']}", 'disciple_tools' ) ?></strong></span><br/>

                        <?php if ( $my_opt_field['type'] == 'key_select' || $my_opt_field['type'] == 'multi_select' ): ?>

                        <span>Value:</span><br/>
                        <ul class="default-value-options">
                        <?php asort( $my_opt_field['default'] ); ?>    
                        <?php foreach ( $my_opt_field['default'] as $di => $dt ): ?>
                        <li>
                            <strong><span class="hlp-value"><?php esc_html_e( "{$di}", 'disciple_tools' ) ?></span></strong>:
                            <span class="hlp-label"><?php esc_html_e( "{$dt['label']}", 'disciple_tools' ) ?></span>
                        </li>
                        <?php endforeach; ?>
                        </ul>

                        <?php else : ?>
                        <span>Value: <strong><?php esc_html_e( "{$my_opt_field['default']}", 'disciple_tools' ) ?></strong></span><br/>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                    </div>

                    </form>

                    <script type="text/javascript">

                        jQuery(document).ready(function(){
                            getAllDefaultValues();
                        });

                        function check_column_mappings(id){
                            //console.log('check_column_mappings');
                            var elements, selected, selectedValue, c; 

                            selected = document.getElementById('csv_mapper_'+id);
                            selectedValue = selected.options[selected.selectedIndex].value;

                            //console.log('selected_value='+selectedValue);
                            elements = document.getElementsByClassName('cf-mapper');
                            for(var i=0; i<elements.length; i++){
                                if(i!=id && selectedValue==elements[i].value){
                                    //console.log('IND:' + i + ' ID:' + elements[i].id + ' VALUE:' + elements[i].value);
                                    selected.selectedIndex = 'IGNORE';
                                    if(elements[i].value!='IGNORE'){
                                        alert('Already Mapped!');
                                    }                       
                                }
                            }
                        }            

                        function getAllDefaultValues(){                
                            jQuery('.mapper-table tbody > tr.mapper-coloumn').each(function(){
                                //console.log('C:'+jQuery(this).attr('data-row-id'));
                                var i = jQuery(this).attr('data-row-id');
                                if(typeof i !== 'undefined'){ getDefaultValues(i); }
                            });                
                        }

                        function getDefaultValues(id){                

                            var selected, selectedValue, dom, ty, hlp;
                            selected = document.getElementById('csv_mapper_'+id);
                            selectedValue = selected.options[selected.selectedIndex].value;

                            jQuery('.helper-fields').hide().html('');
                            //console.log('id:' + id + ' v:'+ selectedValue);
                            //hlp = document.getElementById('helper-fields-'+selectedValue+'-txt').innerHTML;
                            //document.getElementById('helper-fields-'+id).innerHTML = hlp;

                            dom = jQuery('#helper-fields-'+selectedValue+'-txt');                
                            ty = dom.attr('data-type');

                            if(ty == 'key_select' || ty == 'multi_select'){
                                hlp = dom.html(); //console.log('hlp:' + hlp);

                                jQuery('#unique-values-'+id).show();
                                //jQuery('#unique-values-'+id).find('.selected-mapper-column-name').html( jQuery('#csv_mapper_'+id).val() );
                                jQuery('#unique-values-'+id).find('.selected-mapper-column-name').html( jQuery('#csv_mapper_'+id+' option:selected').text() );
                                jQuery('#helper-fields-'+id).html( hlp ); //.show();
                                jQuery('.value-mapper-'+id).html('');
                                jQuery('.value-mapper-'+id).append('<option value="">--select-one--</option>');
                                //h_sel = jQuery('.value-mapper-'+id).attr('data-value');

                                //default-value-options
                                jQuery.each( dom.find('.default-value-options li'), function(i,v){
                                    var h_this, h_value, h_label, h_html, h_sel;
                                    h_this = jQuery(this);
                                    h_value = h_this.find('.hlp-value').html();
                                    h_label = h_this.find('.hlp-label').html();
                                    if(!h_label.length>0){ h_label = h_value.toUpperCase(); }
                                    //console.log('id:' +i+' value:'+h_value+' label:'+h_label);                     

                                    h_html = '<option value="'+h_value+'"'; 
                                    //if(h_sel==h_value){ h_html = h_html + ' selected="selected"'; }
                                    h_html = h_html + '>'+h_label+'</option>';
                                    jQuery('.value-mapper-'+id).append(h_html);
                                });

                                jQuery('.value-mapper-'+id).each(function(){
                                    h_sel = jQuery(this).attr('data-value');
                                    jQuery(this).find('option').each(function(){
                                        if(h_sel==jQuery(this).attr('value')){
                                            jQuery(this).attr('selected','selected');
                                        }
                                    });
                                });

                            } else {
                                jQuery('#unique-values-'+id).hide();
                            }
                        }
                    </script>
                </td>
            </tr>
            <?php /** <tr>
                <td>
                    <a href="" class="button button-primary"> <?php esc_html_e( "Back", 'disciple_tools' ) ?> </a>
                </td>
            </tr> */ ?>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function mapping_process( $filepath, $file_source = 'web', $file_assigned_to = '' ){

        $people = [];
        $delimeter = ',';
        $incl_headers = 'yes';
        $multi_separator = ';';

        //open file
        ini_set( 'auto_detect_line_endings', true );
        $file_data = fopen( $filepath, "r" );

        $data_rows = array();
        while ( $row = fgetcsv( $file_data, 0, $delimeter, '"', '"' ) ) {
            $data_rows[] = $row;
        }

        $uploaded_file_headers = [];

        $my_opt_fields = self::get_all_default_values();
        $con_headers_info = self::get_contact_header_info();
        $con_headers_info_keys = array_keys( $con_headers_info );

        if ( $incl_headers == "yes" && isset( $data_rows[0] ) ) {
            $csv_headers = $data_rows[0];
            $uploaded_file_headers = $data_rows[0];
            unset( $data_rows[0] );

        } else {
            //if csv headers are not provided
            $csv_headers = $con_headers_info_keys;
        }

        $temp_contacts_data = $data_rows;

//echo '<hr/>OPT_FLDS:<br/><pre>'; print_r($my_opt_fields); echo '</pre>';
//echo '<hr/>HEADER:<br/><pre>'; print_r($con_headers_info); echo '</pre>';
//echo '<hr/>KEYS:<br/><pre>'; print_r($con_headers_info_keys); echo '</pre>';
//echo '<hr/>DATA_ROWS:<br/><pre>'; print_r($data_rows); echo '</pre>';
//echo '<hr/>TEMP<br/><pre>'; print_r($temp_contacts_data); echo '</pre>';
/******************************************************************************/
        //correct csv headers
        foreach ( $csv_headers as $ci => $ch ) {
            $dest = $ch;
            $mapped_column = self::get_mapper( $ch );
            if ( $mapped_column != null && strlen( $mapped_column ) > 0 ) { $csv_headers[$ci] = $mapped_column; }
        }

        //loop over array
        foreach ( $data_rows as $ri => $row ) {

            $fields = [];

            foreach ( $row as $index => $i ) {

                if ( $file_assigned_to != '') { $fields["assigned_to"] = (int) $file_assigned_to; }

                $fields["sources"] = [ "values" => array( [ "value" => $file_source ] ) ];

                //cleanup
                $i = str_replace( "\"", "", $i );

                if ( isset( $csv_headers[$index] ) ) {
                    $ch = $csv_headers[$index];
                    $pos = strpos( $i, $multi_separator );

                    if ( $ch == 'title' ) {
                        $fields['title'] = $i;

                    } else if ( $ch == 'cf_gender' ) {

                        $i = strtolower( $i );
                        $i = substr( $i, 0, 1 );
                        $gender = "not-set";
                        if ( $i == "m" ){ $gender = "male";
                        } else if ( $i == "f" ){ $gender = "female"; }
                        $fields['cf_gender'] = $gender;

                    } else if ( $ch == 'cf_notes' ) {
                    //} else if ( $ch == 'cf_notes' || $ch == 'cf_dob' || $ch == 'cf_join_date' ) {
                        $fields[$ch][] = $i;

                    } else {

                        if ( $pos === false ) {
                            $fields[$ch][] = [ "value" => $i ];
                        } else {
                            $multivalued = explode( $multi_separator, $i );
                            foreach ( $multivalued as $mx ) {
                                //$fields[$ch][] = [ "value" => $mx ];
                                $fields[$ch][] = [ "value" => trim( $mx ) ];
                            }
                        }
////////////////////////////////////////////////////////////////////////////////
                    }
                }
            }

            //add person
            $people[] = array( $fields );
            unset( $fields );

        }
/******************************************************************************/
        //close the file
        fclose( $file_data );


        $unique = array();
        foreach ( $csv_headers as $ci => $ch ) {
            foreach ( $data_rows as $ri => $row ) {
                $unique[$ci][] = $row[$ci];
            }
        }


        foreach ( $unique as $ci => $list ) {
            $unique[$ci] = array_unique( $list );

            asort( $unique[$ci] ); //sort-the-value(s)

            $ch = $csv_headers[$ci];
            //if ( isset( $my_opt_fields['fields'][$ch]['type'] )
            //         && $my_opt_fields['fields'][$ch]['type'] == 'multi_select' ) {
                //$multi_separator = ';';
            foreach ( $unique[$ci] as $ui => $uv ) {

                $pos = strpos( $uv, $multi_separator );
                if ( $pos === false ) {

                } else {
                    unset( $unique[$ci][$ui] );
                    $multivalued = explode( $multi_separator, $uv );
                    foreach ( $multivalued as $mxid => $mx ) {
                        $unique[$ci][] = trim( $mx );
                    }
                }
            }

            $unique[$ci] = array_unique( $unique[$ci] );
            asort( $unique[$ci] ); //sort-the-value(s)
            //}
        }


        return [
            'people' => $people,
            'temp_contacts_data' => $temp_contacts_data,
            'uploade_file_headers' => $uploaded_file_headers,
            'my_opt_fields' => $my_opt_fields,
            'csv_headers' => $csv_headers,
            'con_headers_info' => $con_headers_info,
            'unique' => $unique,
            'delimeter' => $delimeter,
            'multi_seperator' => $multi_separator
        ];
    }

    public function preview_process( $csv_data = [], $csv_headers = [], $value_mapperi_data = [], $value_mapper_data = [], $file_assigned_to = '', $file_source = '', $delimeter = ',' ) {
            return self::process_data( $csv_data, $csv_headers, $value_mapperi_data, $value_mapper_data, $file_assigned_to, $file_source, $delimeter );
    }

    public function preview( $filepath, $csv_data, $csv_headers, $delimeter = ',', $multiseperator = ';', $file_source = 'web', $file_assigned_to = '', $mapping_data, $value_mapperi_data, $value_mapper_data ) {
        $con_headers_info = self::get_contact_header_info();
        foreach ( (array) $mapping_data as $my_id => $my_data ){
            if ( $my_data == 'IGNORE' || $my_data == 'NONE'){
                unset( $mapping_data[$my_id] );
            }
        }

        $people = $this->preview_process( $csv_data, $mapping_data, $value_mapperi_data, $value_mapper_data, $file_assigned_to, $file_source, $delimeter, $multiseperator, $filepath );

        ?>
        <!-- Box -->
        <span onclick="jQuery('.debug-data').toggle()" style="float:right;color:#e14d43;font-weight:bold;text-transform:uppercase;font-size:10px;background:#fde8eb;padding:5px;border:1px dashed #f4bbc3;">Show/Hide Debug Data</span>
        <table class="widefat striped">
            <thead>
            <th><h1>Step 3: Confirm & Import</h1></th>
            </thead>
            <tbody>
            <tr>
                <td>
<fieldset id="debug-data-<?php echo __LINE__ ?>" class="debug-data" style="display:none"><legend onclick="jQuery('#debug-data-<?php echo __LINE__ ?> section').toggle()">CSV DATA</legend><section><pre><?php print_r( $csv_data ) ?></pre></section></fieldset>
<fieldset id="debug-data-<?php echo __LINE__ ?>" class="debug-data" style="display:none"><legend onclick="jQuery('#debug-data-<?php echo __LINE__ ?> section').toggle()">PPL</legend><section><pre><?php print_r( $people ) ?></pre></section></fieldset>

                <?php self::display_data( $people );?>


                <form method="post" enctype="multipart/form-data">

                    <input type="hidden" name="csv_contacts" value="<?php echo esc_html( base64_encode( serialize( $people ) ) ); ?>">

                    <?php wp_nonce_field( 'csv_correct', 'csv_correct_nonce' ); ?>

                    <a href="<?php echo esc_html( admin_url( 'admin.php?page=dt_utilities&tab=contact-import' ) ) ?>"
                       class="button button-primary"> 
                            <?php esc_html_e( "Back - Something is wrong!", 'disciple_tools' ) ?>
                    </a>

                    <input type="submit" name="submit" 
                           id="submit" 
                           style="background-color:#4CAF50; color:white" 
                           class="button" 
                           value=<?php esc_html_e( "Import", 'disciple_tools' ) ?>>
                    
                </form>


                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function insert_contacts( $contacts ){

        set_time_limit( 0 );
        global $wpdb;

        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <th></th>
            </thead>
            <tbody>
            <tr>
                <td>
                    <div id="import-logs">&nbsp;</div>
                    <div id="contact-links">&nbsp;</div>

                    <script type="text/javascript">
                    var pid = 1000;
                    function process( q, num, fn, done ) {
                        // remove a batch of items from the queue
                        var items = q.splice(0, num),
                            count = items.length;

                        // no more items?
                        if ( !count ) {
                            // exec done callback if specified
                            done && done();
                            // quit
                            return;
                        }

                        // loop over each item
                        for ( var i = 0; i < count; i++ ) {
                            // call callback, passing item and
                            // a "done" callback
                            fn(items[i], function() {
                                // when done, decrement counter and
                                // if counter is 0, process next batch
                                --count || process(q, num, fn, done);
                                pid++;
                            });                    

                        }
                    }

                    // a per-item action
                    function doEach( item, done ) {                
                        console.log('starting ...' ); //t('starting ...');
                        jQuery.ajax({
                            type: "POST",
                            data: item,
                            contentType: "application/json; charset=utf-8",
                            dataType: "json",
                            url: "<?php echo esc_url_raw( rest_url() ); ?>" + `dt/v1/contact/create?silent=true`,
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', "<?php /*@codingStandardsIgnoreLine*/ echo sanitize_text_field( wp_unslash( wp_create_nonce( 'wp_rest' ) ) ); ?>");
                            },
                            success: function(data) {
                                console.log('done'); t('PID#'+pid+' done');
                                //jQuery('#contact-links').append('<li><a href="'+data.permalink+'" target="_blank">Contact #'+data.post_id+'</a></li>');
                                done();
                            },
                            error: function(xhr) { // if error occured
                                alert("Error occured.please try again");
                                console.log("%o",xhr);
                                t('PID#'+pid+' Error occured.please try again');
                            }
                        });
                    }

                    // an all-done action
                    function doDone() {
                        console.log('all done!'); t('all done');
                        jQuery("#back").show();
                    }

                    function t(m){
                        var el, v;
                        el = document.getElementById("import-logs");
                        v = el.innerHTML;
                        v = v + '<br/>' + m;
                        el.innerHTML = v;                
                    }

                    function reset(){
                        document.getElementById("import-logs").value = '';
                    }
                    </script>

                    <?php
                    global $wpdb;
                    $js_contacts = [];
                    foreach ( $contacts as $num => $f ) {
                        $js_array = wp_json_encode( $f[0] );
                        $js_contacts[] = $js_array;
                        $wpdb->queries = [];
                    }
                    ?>
                    <script type="text/javascript">

                    reset();
                    t('started processing queue!');

                    // start processing queue!
                    queue = <?php echo wp_json_encode( $js_contacts ); ?>;
                    process(queue, 5, doEach, doDone);
                    </script>

                    <?php
                    $num = count( $contacts );
                    echo esc_html( sprintf( __( "Creating %s Contacts DO NOT LEAVE THE PAGE UNTIL THE BACK BUTTON APPEARS", 'disciple_tools' ), $num ) );
                    ?>

                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    private function _get_file_headers( $filepath ){

    }

    public function get_contact_header_info(){
        //return Disciple_Tools_Contacts::get_contact_header_info();
/** //global $wpdb;
//$query = "SELECT
//            `id`,
//            `entity`,
//            `name`,
//            `label`,
//            `sort_num`,
//            `rv`,
//            `rvs`,
//            `mv`,
//            `ty`,
//            `tyl`,
//            `tyf`
//            FROM `wp_dt_headers`
//            WHERE `entity` = 'contact'
//          ORDER BY `sort_num`";
//$results = $wpdb->get_results($query, ARRAY_A);
//return $results; */

        $data = [];
        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();

        foreach ( $channels as $label => $channel ) {
            $label = "contact_{$label}";  // @NOTE: prefix "contact_"
            $data[$label] = [
                'name' => $channel['label'],
                'type' => 'standard',
                'defaults' => null
            ];
        }

        $fields = Disciple_Tools_Contacts::get_contact_fields();
        if ( isset( $fields ) ) {
            $data = array_merge( $data, $fields );
        }
        return $data;
    }

    public static function get_mapper( $name = '' ) {
        /** //$column_name = '';
//global $wpdb;
//$query = "SELECT wp_dt_headers.name
//    FROM wp_dt_headers_aliases, wp_dt_headers
//    WHERE wp_dt_headers_aliases.entity = 'contact'
//    AND wp_dt_headers.entity = 'contact'
//    AND wp_dt_headers_aliases.header_id = wp_dt_headers.id
//    AND wp_dt_headers_aliases.alias = %s";
//$column_name = $wpdb->get_var( $wpdb->prepare( $query, $name ) );
//return $column_name; */
        return self::colheadcompare( $name );
    }

    public static function colheadcompare( $source_col_heading ) {
        $column_name = null;
        $src = strtolower( trim( $source_col_heading ) );
        //echo "souce-heading:{$src}<br/>";

        // @NOTE: prefix "contact_"
        $prefix = 'contact_';

        if ( array_search( $src, self::$contact_name_headings ) > 0 ) {
            $column_name = 'title';
        } else if ( array_search( $src, self::$contact_phone_headings ) > 0 ) {
            $column_name = "{$prefix}phone";

        } else if ( array_search( $src, self::$contact_email_headings ) > 0 ) {
            $column_name = "{$prefix}email";

        } else if ( array_search( $src, self::$contact_address_headings ) > 0 ) {
            $column_name = "{$prefix}address";

        } else {
            //get custom contact fields added by user
            //$custom_field_options = dt_get_option( "dt_field_customizations" );
            //if ( isset( $custom_field_options['contacts'][$src] ) ) {
            //    $column_name = $src;
            //}
            $fields = Disciple_Tools_Contacts::get_contact_fields();
            if ( isset( $fields[$src] ) ) {
                $column_name = $src;
            } else {
                $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
                if ( isset( $channels[$src] ) ) {
                    //$column_name = $src;
                    $column_name = "{$prefix}{$src}";
                }
            }

            //try to match the field label
            //assigned_to => ['name'=>"Assigned To"]
            if ( $column_name == null ) {
                foreach ( $fields as $f => $field ) {
                    if ( isset( $field['name'] ) && strtolower( trim( $field['name'] ) ) == $src ) {
                        $column_name = $f;
                    }
                }
            }

            if ( $column_name == null ) {
                $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
                foreach ( $channels as $f => $field ) {
                    if ( isset( $field['name'] ) && strtolower( trim( $field['name'] ) ) == $src ) {
                        $column_name = $f;
                    }
                }
            }
        }
        //echo "destination-column-name:{$column_name}";
        return $column_name;
    }

    public static function get_dropdown_list_html( $field, $id = 'selector', $data = [], $selected = null, $html_options = [], $allow_all_types = true ) {

        if ( isset( $html_options['id'] ) ) { unset( $html_options['id'] ); }

        $f = true;

        $html = "<select id=\"{$id}\"";
        $html .= " data=\"".$field."\"";

        foreach ( $html_options as $opt => $values ) {
            $html .= " {$opt}=\"{$values}\"";
        }

        $html .= ">";

        $html .= "<option value=\"NONE\">--select-one--</option>";
        $html .= "<option value=\"IGNORE\">don't import</option>";
////////////////////////////////////////////////////////////////////////////////

        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $html .= "<optgroup label=\"Standard Fields\">";

        $html .= "<option value=\"title\"";
        if ( $selected == 'title' ) { $html .= " selected=\"selected\""; }
        $html .= ">Contact Name</option>";

        foreach ( $channels as $label => $item ) {

            //if ( $label=='phone' || $label == 'email' || $label == 'address' ) {
                $label = "contact_{$label}";
            //} // @NOTE: prefix "contact_"

            $html .= "<option value=\"{$label}\"";
            if ( $selected != null && $selected == "{$label}" ) { $html .= " selected=\"selected\""; }
            $html .= ">{$item['label']}</option>";
        }
        $html .= "</optgroup>";

        $data = Disciple_Tools_Contacts::get_contact_fields();

        $list_data = array();
        foreach ( $data as $key => $item ) {
            $list_data[$key] = $item['name'];
        }
        asort( $list_data );

        $html .= "<optgroup label=\"Other Fields\">";
        foreach ( $list_data as $key => $label ) {

            //$f = true;
            $type = null;

            if ( isset( $data[$key]['type'] ) ) {
                $type = $data[$key]['type'];
                //if ( $data[$key]['type'] == 'multi_select'){
                //    $html .= " disabled=\"disabled\"";
                //    $f = false;
                //}
            }

            //if ( $f ) {
            $html .= "<option value=\"{$key}\"";
            if ( $selected != null && $selected == $key ) {
                $html .= " selected=\"selected\"";
            }
            $html .= ">";
            $html .= "{$label}";
            $html .= "</option>";
            //}
        }
        $html .= "</optgroup>";
////////////////////////////////////////////////////////////////////////////////
        $html .= '</select>';
        return $html;
    }

    /**
     * @param array $data_rows
     * @param array $csv_headers
     * @param array $value_mapperi_data
     * @param array $value_mapper_data
     * @param string $assign
     * @param string $source
     * @param char $del
     */
    public static function process_data( $data_rows, $csv_headers = [], $value_mapperi_data = [], $value_mapper_data = [], $assign = '', $source = '', $del = ',' ){

        $multi_separator = ';';
        $people = [];

        $chi = array_count_values( $csv_headers );

        foreach ( $csv_headers as $ci => $ch ) {
            $mapped_column = self::get_mapper( $ch );
            if ( $mapped_column != null && strlen( $mapped_column ) > 0 ) {
                $csv_headers[$ci] = $mapped_column;
            }
        }

        //handle N columns to ONE column mapping
        //phone(primary)/phone(mobile) -> phone
        $mids = [];
        $del_csv_headers = [];
        foreach ( $chi as $mch => $count ) {
            if ( $count > 1 ) {
                $mids[$mch]['count'] = $count;
                foreach ( $csv_headers as $ci => $ch ) {
                    if ( $mch == $ch ) {
                        $mids[$mch]['columIds'][] = $ci;
                    }
                }
                $mids[$mch]['primaryCol'] = $mids[$mch]['columIds'][0];
                unset( $mids[$mch]['columIds'][0] ); //array_pop
            }
        }

        foreach ( $data_rows as $data_row_id => $data_row ) {
            foreach ( $data_row as $col_id => $col_data ) {
                if ( isset( $csv_headers[$col_id] ) ) {
                    $ch = $csv_headers[$col_id];
                    if ( isset( $mids[$ch]['columIds'] ) ) {
                        foreach ( $mids[$ch]['columIds'] as $xcolid ) {
                            if ( $col_id == $xcolid ) {
                                $data_rows[$data_row_id][ $mids[$ch]['primaryCol'] ] .= $multi_separator.$col_data;
                                unset( $data_rows[$data_row_id][$xcolid] );
                            }
                        }
                    }
                }
            }
        }

        foreach ( $mids as $ch => $xdata ) {
            foreach ( $xdata['columIds'] as $xcolid ) {
                if ( isset( $csv_headers[$xcolid] ) ) {
                    unset( $csv_headers[$xcolid] );
                }
            }
        }

        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();


        foreach ( $data_rows as $ri => $row ) {
            $fields = [];
            foreach ($row as $index => $i) {

                if ( $assign != '') { $fields["assigned_to"] = (int) $assign; }
                $fields["sources"] = [ "values" => array( [ "value" => $source ] ) ];

                //cleanup
                $i = str_replace( "\"", "", $i );

                if ( isset( $csv_headers[$index] ) ) {

                    $ch = $csv_headers[$index];

                    $type = isset( $cfs[$ch]['type'] ) ? $cfs[$ch]['type'] : null;
                    if ( $type == null ) { $type = isset( $channels[$ch] ) ? $channels[$ch] : null; }

//echo "R{$ri}C{$index}|Ch:{$ch}|Type:{$type}<br/>";

                    if ( $ch == 'title' ) {
                        $fields[$ch] = $i;

                        //} else if ( $ch == 'gender' || $ch == 'cf_gender' ) {
                        //
                        //    $i = strtolower( $i );
                        //    $i = substr( $i, 0, 1 );
                        //    $gender = "not-set";
                        //    if ($i == "m" ){ $gender = "male";
                        //    } else if ($i == "f" ){ $gender = "female"; }
                        //    $fields[$ch] = $gender;

                    } else if ( $type == 'key_select' ) {

                        if ( isset( $value_mapperi_data[$index] ) ) {
                            foreach ( $value_mapperi_data[$index] as $vmdi => $vmdv ) {
                                if ( $vmdv == $i && isset( $value_mapper_data[$index][$vmdi] ) ) {
                                    $fields[$ch] = $value_mapper_data[$index][$vmdi];
                                }
                            }
                        }
////////////////////////////////////////////////////////////////////////////////
                    } else if ( $type == 'multi_select' ) {

                        $multivalued = explode( $multi_separator, $i );
                        foreach ( $multivalued as $mx ) {

                            $mx = trim( $mx );

                            if ( isset( $value_mapperi_data[$index] ) ) {

                                foreach ( $value_mapperi_data[$index] as $vmdi => $vmdv ) {
                                    if ( $vmdv == $mx && isset( $value_mapper_data[$index][$vmdi] ) ) {
                                        $mx = $value_mapper_data[$index][$vmdi];
                                    }
                                }

                            //} else {
                            //    //only allow values in the default set of data values
                            //    $fields[$ch]["values"][] = [ "value" => trim($mx) ];
                            }

                            $fields[$ch]["values"][] = [ "value" => $mx ];
                        }

                    //
                    //} else if ( $type == 'user_select' ) {
                    //    $fields[$ch] = $i; /**/

                    } else if ( $type == 'boolean' ) {
                        $fields[$ch] = $i[0] === "1";

                    } else if ( $type == 'date' ) {
                        $my_temp_time = strtotime( $i );
                        if ( $my_temp_time ) {
                            $fields[$ch] = date( 'Y-m-d', $my_temp_time );
                        } else {
                            $fields[$ch] = '';
                        }


//                    } else if ( $type == 'array' ) {//
//                        foreach ( (array) $i as $av ) {
//                            $fields[$ch][] = [ "value" => $av ];
//                        }//
//                    //} else if ( $type=='text' ) {
//                    //} else if ( $type=='connection' ) {

                    } else {

                        //handle multivalued data
                        $pos = strpos( $i, $multi_separator );
                        if ( $pos === false ) {
                            $fields[$ch][] = [ "value" => $i ];
                        } else {
                            $multivalued = explode( $multi_separator, $i );
                            foreach ( $multivalued as $mx ) {
                               //$fields[$ch][] = [ "value" => trim( $mx ) ];
                                $fields[$ch]["values"][] = [ "value" => trim( $mx ) ];
                            }
                        }
                    }
                }
            }

            //add person
            $people[] = array( $fields );
            unset( $fields );

        }

        return $people;
    }

    /**
     * @param array $people
     */
    public static function display_data( $people ) {
        //return Disciple_Tools_Contacts::display_data( $people );
        $headings = [];
        $delimeter = ',';
        $multi_separator = ';';

        $prefix = 'contact_';
        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();

        $rowindex = 0;
        $error_summary = [];
        if ( isset( $people[0][0] ) ) {
            $headings = array_keys( $people[0][0] );
            //echo '<pre>'; print_r($headings); echo '</pre>';
            //echo '<pre>'; print_r($people[0][0]); echo '</pre>';
            if ( isset( $headings['sources'] ) ) { unset( $headings['sources'] ); }
            if ( isset( $headings['assigned_to'] ) ) { unset( $headings['assigned_to'] ); }
            //if ( isset( $headings['title'] ) ) { unset($headings['title'] ); }
        }
        ?>
        <fieldset class="debug-data" style="display:none"> <legend>Heading</legend>
        <pre>
        <?php print_r( $headings ) ?>
        </pre>
        </fieldset>

        <table class="data-table">
            <thead>
            <tr>
                <th data-col-id=0></th>

                <th data-col-id=1>
                    <span class="cflabel">
                    <?php esc_html_e( 'Contact Name', 'disciple_tools' ); ?>
                    </span>
                </th>

                <?php
                foreach ( $headings as $hi => $heading ) {

                    if ( $heading == 'title' ) {
                    } else if ( $heading == 'assigned_to' ) {
                    } else if ( $heading == 'sources' ) {
                    } else {
                        $ch = str_replace( $prefix, '', $heading );
                        $str = '';
                        if ( isset( $cfs[$ch], $cfs[$ch]['name'] ) ) {
                            $str = strval( $cfs[$ch]['name'] );

                        } else if ( isset( $channels[$ch], $channels[$ch]['label'] ) ) {
                            $str = strval( $channels[$ch]['label'] );

                        }
                        ?>
                        <th data-col-id="<?php echo esc_html( $hi ); ?>">
                            <span class="cflabel">
                                <?php echo esc_html( $str ); ?>
                            </span>

                            <br/>
                        </th>
                        <?php
                    }
                }
                ?>
                <th><span class="cflabel">
                <?php esc_html_e( 'Source', 'disciple_tools' ); ?>
                </span></th>

                <th><span class="cflabel">
                    <?php esc_html_e( 'Assigned To', 'disciple_tools' ); ?>

                </span></th>

            </tr>
            </thead>
        <tbody>
        <?php foreach ( $people as $pid => $ppl_data ) {
            $rowindex++;
            $person_data = $ppl_data[0]; ?>


            <tr id="person-data-item-<?php echo esc_html( $pid ) ?>" class="person-data-item">

            <td data-col-id=0><?php echo esc_html( $rowindex ); ?></td>

            <td data-col-id=1 data-key="title">
                <?php echo esc_html( $person_data['title'] ); ?>
            </td>

            <?php foreach ( $headings as $hi => $ch ) {

                $type = isset( $cfs[$ch]['type'] ) ? $cfs[$ch]['type'] : null;
                if ( $type == null ){ $type = isset( $channels[$ch] ) ? $channels[$ch] : null; }

                //if ( isset( $person_data[$ch][0]["value"] ) ) {
                    //$html_body .= esc_html( $person_data[$ch][0]["value"] );

                if ( $ch == 'title' ) {
                } else if ( $ch == 'assigned_to' ) {
                } else if ( $ch == 'sources' ) {
                } else {
                    $errors = '';

                    if ( $person_data[$ch] != null || strlen( trim( $person_data[$ch] ) ) > 0 ) {
                        $errors = self::validate_data( $ch, $person_data[$ch] );
                        if ( $errors > 0 ) {
                            if ( isset( $error_summary[$ch] ) ) {
                                $error_summary[$ch]['error-count'] = intval( $error_summary[$ch]['error-count'] ) + 1;
                            } else {
                                $error_summary[$ch]['error-count'] = 1;
                            }
                        }
                    } ?>

                    <td data-col-id='<?php echo esc_html( $hi ); ?>' data-key="<?php echo esc_html( $ch ); ?>" <?php echo esc_html( $errors > 0 ? 'class="data-error"' : '' ) ?> >

                        <?php
                        $value = '';
                        if ( $type == 'key_select'
                            || $type == 'date'
                            || $type == 'boolean' ) {

                            $value = $person_data[$ch];

                        } else if ( ( $type == 'multi_select' ) ) {

                            if ( isset( $person_data[$ch]["values"] ) && is_array( $person_data[$ch]["values"] ) ) {
                                $values = [];
                                foreach ( $person_data[$ch]["values"] as $mi => $v ) {
                                    if ( isset( $v["value"] ) ) { $values[] = esc_html( $v["value"] ); }
                                }
                                $value = implode( $multi_separator, (array) $values );
                            }
    ////////////////////////////////////////////////////////////////////////////////
                        } else if ( isset( $person_data[$ch] ) ) {
                            $values = [];
                            if ( isset( $person_data[$ch]["values"] ) && is_array( $person_data[$ch]["values"] ) ) {
                                foreach ( $person_data[$ch]["values"] as $mi => $v ) {
                                    if ( isset( $v["value"] ) ) { $values[] = esc_html( $v["value"] ); }
                                }
    ////////////////////////////////////////////////////////////////////////////////
                            } else {
                                foreach ( $person_data[$ch] as $mi => $v ) {
                                    if ( isset( $v["value"] ) ){ $values[] = esc_html( $v["value"] ); }
                                }
                            }

                            $value = implode( $multi_separator, (array) $values );
                        }
                        echo esc_html( $value );
                        ?>
                    </td> <?php
                }
            }
            ?>
            <td>
                <?php echo esc_html( $person_data['sources']["values"][0]["value"] ); ?>
            </td>


            <td>
            <?php
            if ( ( isset( $person_data['assigned_to'] ) && $person_data['assigned_to'] != '' ) ) {
                echo esc_html( get_user_by( 'id', $person_data['assigned_to'] )->data->display_name );
            } else {
                echo esc_html( 'Not Set' );
            }
            ?>
            </td>

            </tr>
        <?php } ?>

        </tbody>





        </table>

        <?php
        $total_data_rows = count( (array) $people );

        foreach ( $error_summary as $ch => $err ){
            $column_type = null;
            $channel_field = str_replace( $prefix, '', $ch );
            $error_html = '';
            if ( isset( $cfs[$ch], $cfs[$ch]['name'] ) ) {
                $error_html .= $cfs[$ch]['name'];
                if ( isset( $cfs[$ch]['type'] ) ) {
                    $column_type = $cfs[$ch]['type'];
                }
            } else if ( isset( $channels[$channel_field], $channels[$channel_field]['label'] ) ) {
                $error_html .= $channels[$channel_field]['label'];

            } else {
                $error_html .= $ch;
            }
            ?>
            <div class="error-summary-title">Column

            <span class="error-field-name">
                <?php echo esc_html( $error_html ); ?>
            </span>

            <?
            $error_html = '';
            if ( $column_type != null && in_array( $column_type, [ 'key_select', 'multi_select' ] ) ) {
                $error_html .= ' match allowed values';
            } else if ( $column_type == 'date' ) {
                $error_html .= ' needs to be in format yyyy-mm-dd';
            } else {
                $error_html .= ' needs to contain valid format';
            }
            ?>
            <?php echo esc_html( $error_html ); ?>

            </div>
<!--            //TODO Column Email needs to contain valid email address format-->
<!--            //$error_html .= '<div style="clear:both;"></div>';-->

            <div class="error-summary-details">
            <?php echo esc_html( $err['error-count'] ); ?> out of <?php echo esc_html( $total_data_rows ); ?> rows contain invalid format.<br/>
            </div>
            <div style="clear:both;"></div>
        <?php }

        if ( count( $error_summary ) > 0 ) : ?>
            <div class="error-summary-details">Please fix these issues before importing.</div>
        <?php endif;
    }

    public static function display_data_version_1( $people, $con_headers_info, $csv_headers ) {
        $html = '';

        $html .= '<table class="data-table">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th></th>';
        $html .= '<th>';
        $html .= '<span class="cflabel">';
        $html .= esc_html( translate( 'Contact Name', 'disciple_tools' ) );
        $html .= '</span><br/>';

        $html .= '<span class="cffield">title</span>';
        $html .= '</th> ';


        foreach ( $csv_headers as $ci => $ch ) {

            if ( $ch == 'title' ) { continue; }

            $html .= '<th>';
            $html .= '<span class="cflabel">';
            if ( $ch == 'csv_source' ) {
                $html .= '<span style="color:green">Source</span>';
            } else if ( isset( $con_headers_info[$ch]['name'] ) ) {
                $html .= $con_headers_info[$ch]['name'];
            } else {
                $html .= '<span style="color:red">UNMAPPED</span>';
            }
            $html .= '</span><br/>';

            $html .= '<span class="cffield">';
            //$html .= esc_html( translate( 'Title', 'disciple_tools' ) );
            $html .= $ch;
            $html .= '</span>';
            $html .= '</th>';
        }

        $html .= '<th><span class="cflabel">';
        $html .= esc_html( translate( 'Source', 'disciple_tools' ) );
        $html .= '</span></th>';

        $html .= '<th><span class="cflabel">';
        $html .= esc_html( translate( 'Assigned To', 'disciple_tools' ) );
        $html .= '</span></th>';
        $html .= '</tr>';
        $html .= '</thead>';

        $html .= '<tbody>';

        $rowindex  =0;
        foreach ( $people as $pid => $ppl_data ) {
            $rowindex++;
            $person_data = $ppl_data[0];

            $html .= '<tr id="person-data-item-'.$pid.'" class="person-data-item">';

            $html .= '<td>'.$rowindex.'</td>';

            $html .= '<td>';
            $html .= $person_data['title'];
            $html .= '</td>';

            foreach ( $csv_headers as $ci => $ch ) {

                if ( $ch == 'title' ) { continue; }
                $html .= '<td data-key="'.$ch.'">';

                if ( $ch == 'cf_gender' ) {
                    if ( isset( $person_data[$ch] ) ) {
                        $html .= esc_html( $person_data[$ch] );
                    } else {
                        $html .= 'None';
                    }
////////////////////////////////////////////////////////////////////////////////
                } else if ( $ch == 'cf_notes' ) {
                //} else if ( $ch == 'cf_notes' || $ch == 'cf_dob' || $ch == 'cf_join_date' ) {
                    if ( isset( $person_data[$ch][0] ) ) {
                        $html .= esc_html( $person_data[$ch][0] );
                    } else {
                        $html .= 'None';
                    }
////////////////////////////////////////////////////////////////////////////////
                } else {

                    if ( isset( $person_data[$ch][0]["value"] ) ) {
                        $html .= esc_html( $person_data[$ch][0]["value"] );
                    } else {
                        $html .= 'None';
                    }
                }

                $html .= '</td>';
            }


            $html .= '<td>';
            $html .= $person_data['sources']["values"][0]["value"];
            $html .= '</td>';

            $html .= '<td>';
            if ( ( isset( $person_data['assigned_to'] ) && $person_data['assigned_to'] != '' ) ) {
                $html .= esc_html( get_user_by( 'id', $person_data['assigned_to'] )->data->display_name );
            } else {
                $html .= 'Not Set';
            }
            $html .= '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    public static function get_all_default_values() {
        $data = array();
        $data['channels'] = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $data['fields'] = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();

        if ( isset( $data['fields']['sources'] ) ) {
            $my_list_sources = $data['fields']['sources']['default'];
            foreach ( $my_list_sources as $my_list_source_index => $my_list_source_label ) {
                if ( !( isset( $my_list_source ) && strlen( $my_list_source ) > 0 ) ) {
                    $my_list_source_label = $my_list_source_index;
                }
                $data['fields']['sources']['default'][$my_list_source_index] = [ 'label' => $my_list_source_label ];
            }
        }
        return $data;
    }

    private static function validate_data( $field, $data ) {
        $err_count = 0;
        $multi_separator = ';';
        //if ( $data!=null && strlen($data)>0 ) {
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();

        if ( isset( $cfs[$field] ) ) {

            if ( isset( $cfs[$field]['type'] ) ) {
                $type = $cfs[$field]['type'];
                if ( $type == 'boolean' && !filter_var( $data, FILTER_VALIDATE_BOOLEAN ) ) {
                    $err_count++;
                } else if ( $type == 'date' && !( preg_match( "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $data ) ) ) {
                    $err_count++;
                // } else if ( $type == 'number' && is_numeric($data) ) {
                // } else if ( $type == 'array' && !is_array($data) ) {
                } else if ( $type == 'key_select' && !in_array( $data, array_keys( $cfs[$field]['default'] ) ) ) {
                    $err_count++;
                } else if ( $type == 'multi_select' ) {
                    if ( isset( $data['values'] ) ) {
                        foreach ( $data['values'] as $mx ) {
                            if ( isset( $mx['value'] ) ) {
                                $value = trim( $mx['value'] );
                                if ( !in_array( $value, array_keys( $cfs[$field]['default'] ) ) ) {
                                    $err_count++;
                                }
                            }
                        }
                    }
                }
            }
////////////////////////////////////////////////////////////////////////////////
        } else {
            $prefix = 'contact_';
            $ch = str_replace( $prefix, '', $field );
            $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();

            if ( isset( $channels[$ch] ) ) {
                $values = [];
                if ( isset( $data['values'] ) ) {
                    foreach ( $data['values'] as $mx ) {
                        if ( isset( $mx['value'] ) ) {
                            $values[] = trim( $mx['value'] );
                        }
                    }
                } else {
                    foreach ( $data as $mx ) {
                        if ( isset( $mx['value'] ) ) {
                            $values[] = trim( $mx['value'] );
                        }
                    }
                }

                foreach ( $values as $value ) {
                    if ( $ch == 'email' ) {
                        if ( !filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
                            $err_count++;
                        }
                    }
                }
            }
        }
        return $err_count;
    }
}
