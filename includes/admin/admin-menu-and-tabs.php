<?php
/**
 * DT_Import_Export_Menu class for the admin page
 *
 * @class       DT_Import_Export_Menu
 * @version     0.1.0
 * @since       0.1.0
 */

//@todo Replace all instances if DT_Import_Export
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

            <?php foreach ( [
                //'general'=>['tab'=>'general', 'label'=>'General'],
                //'second'=>['tab'=>'second', 'label'=>'Second'],
                'contact' => [
                    'tab' => 'contact',
                    'label' => 'Contact'
                ],

                'location' => [
                    'tab' => 'location',
                    'label' => 'Location'
                ]

            ] as $mytab): ?>

                <a href="<?php echo esc_attr_e( (string) $link, 'dt_import_export' ) . $mytab['tab'] ?>"
                   class="nav-tab <?php ( $tab == $mytab['tab'] ) ? esc_attr_e( 'nav-tab-active', 'dt_import_export' ) : print ''; ?>">
                <?php esc_attr_e( (string) $mytab['label'], 'dt_import_export' ) ?>
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

                    //@codingStandardsIgnoreLine
                    if ( isset( $_FILES["csv_file"] ) ) {
                        $file_parts = explode( ".", sanitize_text_field( wp_unslash( $_FILES["csv_file"]["name"] ) ) )[ count( explode( ".", sanitize_text_field( wp_unslash( $_FILES["csv_file"]["name"] ) ) ) ) - 1 ];

                        if ( $_FILES["csv_file"]["error"] > 0 ) {
                            esc_html_e( "ERROR UPLOADING FILE", 'disciple_tools' );
                            $object->go_back();
                            exit;

                        } else if ( $file_parts != 'csv' ) {
                            esc_html_e( "NOT CSV", 'disciple_tools' );
                            $object->go_back();
                            exit;

                        //@codingStandardsIgnoreLine
                        } else if ( mb_detect_encoding( file_get_contents( $_FILES["csv_file"]['tmp_name'], false, null, 0, 100 ), 'UTF-8', true ) === false ) {
                            esc_html_e( "FILE IS NOT UTF-8", 'disciple_tools' );
                            $object->go_back();
                            exit;
                        }

                        //upload file to server
                        //echo plugin_dir_path(__FILE__); ///home/bicomau/public_html/wpdt/wp-content/plugins/disciple-tools-import-export/includes/admin/
                        //echo plugin_dir_url(__FILE__); //https://bicom.shalomsoft.com/wpdt/wp-content/plugins/disciple-tools-import-export/includes/admin/

                        $path = plugin_dir_path( __FILE__ ).'../../uploads/'.$timestamp;
                        if ( !file_exists( $path ) ) {  mkdir( $path, 0777, true ); }

                        $basename = sanitize_text_field( wp_unslash( $_FILES["csv_file"]['name'] ) );
                        $source = sanitize_text_field( wp_unslash( $_FILES["csv_file"]["tmp_name"] ) );
                        $destination = "{$path}/{$basename}";
                        move_uploaded_file( $source, $destination );
                        //
                        //$filename   = uniqid() . "_" . $timestamp; // 5dab1961e93a7_1571494241
                        //$extension  = pathinfo( $_FILES["csv_file"]["name"], PATHINFO_EXTENSION ); // csv
                        //$basename   = $filename . '.' . $extension; // 5dab1961e93a7_1571494241.csv
                        //
                        //$source = $_FILES["csv_file"]["tmp_name"];
                        //$destination = "{$path}/{$basename}";
                        //if (!move_uploaded_file( $source, $destination ) ) {
                        //    throw new RuntimeException('Failed to move uploaded file.');
                        //}

                        $file_source = sanitize_text_field( wp_unslash( $_POST['csv_source'] ) );
                        $file_assigned_to = sanitize_text_field( wp_unslash( $_POST['csv_assign'] ) );

                        $object->mapping( $destination, $file_source, $file_assigned_to );

                    } /**end-of condition --isset( $_FILES["csv_file"] )-- */

                } else if ( isset( $_POST['csv_mappings_nonce'] )
                            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_mappings_nonce'] ) ), 'csv_mappings' )
                            && $run ) {

                    if ( isset( $_POST["csv_mapper"], $_POST["csv_data"] ) ) {
                        $mapping_data = sanitize_text_field ( wp_unslash( $_POST[ 'csv_mapper' ] ) );

                        $value_mapper_idata = isset( $_POST['VMD']) ? $_POST['VMD'] : [];
                        $value_mapper_data = isset( $_POST['VM']) ? $_POST['VM'] : [];

                        //$mapping_data = unserialize( base64_decode( $_POST["csv_mapper"] ) );
                        $csv_data = unserialize( base64_decode( $_POST["csv_data"] ) );
                        $csv_headers = unserialize( base64_decode( $_POST["csv_headers"] ) );


                        //$tempContactsData

                        $delimeter = sanitize_text_field( wp_unslash( $_POST['csv_delimeter_temp'] ) );
                        $multiseperator = sanitize_text_field( wp_unslash( $_POST['csv_multiseperator_temp'] ) );
                        $filepath = sanitize_text_field( wp_unslash( $_POST['csv_file_path'] ) );
                        $file_source = sanitize_text_field( wp_unslash( $_POST['csv_source_temp'] ) );
                        $file_assigned_to = sanitize_text_field( wp_unslash( $_POST['csv_assign_temp'] ) );

                        $object->preview(
                            $filepath,
                            $csv_data,
                            $csv_headers,
                            $delimeter,
                            $multiseperator,
                            $file_source,
                            $file_assigned_to,
                            $mapping_data,
                            $value_mapper_idata,
                            $value_mapper_data
                        );
                    }

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



                            } else if ( $tab == 'location' ) { 
                die ( 'ERROR_'.__LINE__ );
                $object = new DT_Import_Export_Tab_Location();
                $object->content();  

            } else {

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
    public function content( ) { 

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
            <th>ERROR <?= date('Y-m-d H:i:s') ?></th>
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
    
    public function mapping($filepath, $file_source = 'web', $file_assigned_to='') {
        
//echo "path:{$filepath}<br/>";
//echo "source:{$file_source}<br/>";
//echo "assignedTo:{$file_assigned_to}<br/>";
        
        
        $data = $this->mapping_process($filepath,$file_source,$file_assigned_to);
        
        $delimeter = $data['delimeter'];
        $multiSeparator = $data['multiSeperator'];
        
        $csv_headers = $data['csvHeaders'];
        $conHeadersInfo = $data['conHeadersInfo'];
        $uploadeFileHeaders = $data['uploadeFileHeaders'];
        $myOptionalFields = $data['myOptionalFields'];
        
        $tempContactsData = $data['tempContactsData'];
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
                foreach ( $csv_headers as $ci=>$ch):

                    $colDataType = isset( $myOptionalFields['fields'][$ch]['type'] )?$myOptionalFields['fields'][$ch]['type']:null;

                        $mapperTitle = '';
                        if ( isset($conHeadersInfo[$ch]['name']) ) { 
                            $mapperTitle = $conHeadersInfo[$ch]['name'];
                        } else if ( $ch=='title' ) { 
                            //$mapperTitle = 'Contact Name';
                            $mapperTitle = ucwords($ch);
                        } else {
                            //$mapperTitle = "<span style=\"color:red\" title=\"un-mapped data column\">{$ch}</span>";
                            $mapperTitle = "<span class=\"unmapped\" title=\"un-mapped data column\">{$ch}</span>";
                        }

                    ?>
                    <tr class="mapper-coloumn" data-row-id="<?php echo $ci ?>">

                        <th data-field="<?= $ch ?>" class="src-column">
                        <?php //= $mapperTitle ?><?= $uploadeFileHeaders[$ci] ?>
                        </th>


                        <td class="dest-column">
                            <?= self::getDropdownListHtml( $ch, "csv_mapper_{$ci}", $conHeadersInfo,$ch, [
                                'name' => "csv_mapper[{$ci}]", 
                                'class' => 'cf-mapper', 
                                //'onchange'=>"check_column_mappings({$ci})"
                                'onchange' => "getDefaultValues({$ci})"
                                ],true) ?>
                            <?php /** <div id="helper-fields-<?= $ci ?>" class="helper-fields"<?php if ( $colDataType!='key_select'): ?> style="display:none"<?php endif; ?>></div> */ ?>
                            <div id="helper-fields-<?= $ci ?>" class="helper-fields" style="display:none"></div>

                        </td>


                        <td class="mapper-column">
                        <div id="unique-values-<?= $ci ?>" 
            class="unique-values"
            data-id="<?= $ci ?>"
            data-type="<?= $colDataType ?>"
            <?php /** <?php if ( $colDataType!='key_select'): ?> style="display:none"<?php endif; ?>> */ ?>
            <?php if ( !( $colDataType == 'key_select' || $colDataType == 'multi_select' )): ?> style="display:none"<?php endif; ?>>

            <div class="mapper-helper-text">
                <span class="mapper-helper-title">Map import values to DT values</span><br/>
                <span class="mapper-helper-description">
                    <span class="selected-mapper-column-name"><?= $ch ?><?php //echo $mapperTitle ?></span> 
                    only accepts specific values (as a Selection). 
                    Please map following unique values from your data to existing values in DT. 
                    You can add new values into the DT system if you want by first ...</span>
            </div>

                           <?php if ( isset($unique[$ci])): ?> 
                           <table>

                           <?php foreach ( $unique[$ci] as $vi=>$v): ?>

               <tr>
                   <td>
                       <?php if ( strlen(trim($v))>0): ?>
            <?= $v ?>
                        <?php else: ?>
            <span class="empty">-blank/null-</span>
                        <?php endif; ?>
                   </td>


                   <td>  

                       <input name="VMD[<?= $ci ?>][<?= $vi ?>]" type="hidden" value="<?= $v ?>" />

                       <select id="value-mapper-<?= $ci ?>-<?= $vi ?>" 
               name="VM[<?= $ci ?>][<?= $vi ?>]" 
               class="value-mapper-<?= $ci ?>" 
               data-value="<?= $v ?>">
                       <option>--Not Selected--</option>
                       <?php /**/ ?>
                       <?php if ( isset($myOptionalFields['fields'][$ch]['default'])): ?>               
                       <?php foreach ( $myOptionalFields['fields'][$ch]['default'] as $di=>$dt): ?>
                           <option value="<?= $di ?>"<?php if ( $di==$v): ?> selected="selected"<?php endif; ?>><?= $dt['label'] ?></option>
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
                <?php  endforeach; ?>
                </tbody>

                <tfoot>
                    <tr><td></td>
                        <td colspan="2">


            <?php /** */ ?>
            <input type="hidden" name="csv_data" value="<?php echo esc_html( base64_encode( serialize( $tempContactsData ) ) ); ?>">
            <input type="hidden" name="csv_headers" value="<?php echo esc_html( base64_encode( serialize( $csv_headers ) ) ); ?>">
            <?php /** */ ?>

            <input type="hidden" name="csv_delimeter_temp" value='<?= $delimeter ?>' />
            <input type="hidden" name="csv_multiseperator_temp" value='<?= $multiSeparator ?>' />
            <input type="hidden" name="csv_file_path" value='<?= $filepath ?>' />
            <input type="hidden" name="csv_source_temp" value='<?= $file_source ?>' />
            <input type="hidden" name="csv_assign_temp" value='<?= $file_assigned_to ?>' />

            <?php wp_nonce_field( 'csv_mapping', 'csv_mapping_nonce' ); ?>

            <input type="submit" name="submit" id="submit" 
                   style="background-color:#4CAF50; color:white" 
                   class="button" 
                   value=<?php esc_html_e( "Next", 'disciple_tools' ) ?>>            

                        </td>
                    </tr>


                    <?php /** <tr>
                        <td colspan="3">
            DELIMETER:<strong><?= $delimeter ?></strong><br/>
            MULTI-SEPARATOR:<strong><?= $multiSeparator ?></strong><br/>
            FILE-PATH:<strong><?= $filepath ?></strong><br/>
            SOURCE:<strong><?= $file_source ?></strong><br/>
            ASSIGNED-TO:<strong><?= $file_assigned_to ?></strong><br/>            
                        </td>
                    </tr> */ ?>
                </tfoot>

                </table>
                </div>

                <div class="helper-fields-txt" style="display:none">
                <?php foreach ( $myOptionalFields['fields'] as $myfi=>$myfield): ?>
                <div id="helper-fields-<?= $myfi ?>-txt" data-type="<?= $myfield['type'] ?>">

                    <span>Field: <strong><?= $myfi ?></strong></span><br/>
                    <span>Type: <strong><?= $myfield['type'] ?></strong></span><br/>
                    <span>Description: <strong><?= $myfield['description'] ?></strong></span><br/>

                    <?php if ( $myfield['type']=='key_select'||$myfield['type']=='multi_select'): ?>

                    <span>Value:</span><br/>
                    <ul class="default-value-options">
                    <?php asort($myfield['default']); ?>    
                    <?php foreach ( $myfield['default'] as $di=>$dt): ?>
                    <li>
                        <strong><span class="hlp-value"><?= $di ?></span></strong>:
                        <span class="hlp-label"><?= $dt['label'] ?></span>
                    </li>
                    <?php endforeach; ?>
                    </ul>

                    <?php else: ?>
                    <span>Value: <strong><?= $myfield['default'] ?></strong></span><br/>   
                    <?php endif; ?>    

                </div>
                <?php endforeach; ?>   
                </div>

                </form>        

                <?php 

                ob_start();
                include __DIR__ .'/script1.php';
                $jsCodeBlock = ob_get_clean();
                echo $jsCodeBlock;
                ?>    


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
    
    public function mapping_process($filepath, $file_source = 'web', $file_assigned_to='' ) { 
        $people = [];
        
        $delimeter = ','; $inclHeaders = 'yes'; $multiSeparator = ';';
        
        //open file
        ini_set( 'auto_detect_line_endings', true );
        $file_data = fopen( $filepath, "r" );
        
        $dataRows = array();
        while ( $row = fgetcsv( $file_data, 0, $delimeter,'"','"' ) ) {
            $dataRows[] = $row;
        }
        
        $uploadeFileHeaders = [];
        
        $myOptionalFields = self::getAllDefaultValues();
        $conHeadersInfo = self::getContactHeaderInfo();
        $conHeadersInfoKeys = array_keys($conHeadersInfo);
        
        if ( $inclHeaders == "yes" && isset($dataRows[0])) {
            $csv_headers = $dataRows[0];
            $uploadeFileHeaders = $dataRows[0];
            unset($dataRows[0]);
            
        } else {
            //if csv headers are not provided
            $csv_headers = $conHeadersInfoKeys;
        }
        
        $tempContactsData = $dataRows;
        
//echo '<hr/>OPT_FLDS:<br/><pre>'; print_r($myOptionalFields); echo '</pre>';
//echo '<hr/>HEADER:<br/><pre>'; print_r($conHeadersInfo); echo '</pre>';
//echo '<hr/>KEYS:<br/><pre>'; print_r($conHeadersInfoKeys); echo '</pre>';
//echo '<hr/>DATA_ROWS:<br/><pre>'; print_r($dataRows); echo '</pre>';
//echo '<hr/>TEMP<br/><pre>'; print_r($tempContactsData); echo '</pre>';
        
/******************************************************************************/
        //correct csv headers
        foreach ( $csv_headers as $ci=>$ch ) { 
            $dest = $ch;
            $myMapperColumn = self::getMapper($ch);
            if ( $myMapperColumn != null && strlen($myMapperColumn)>0 ) {  $csv_headers[$ci] = $myMapperColumn; }
        }    
        
        //loop over array
        foreach ( $dataRows as $ri=>$row) {
            
            $fields = []; 
            
            foreach ( $row as $index => $i) {
          
                if ($file_assigned_to != '') { $fields["assigned_to"] = (int) $file_assigned_to; }

                $fields["sources"] = [ "values" => array ( [ "value" => $file_source ] ) ];

                //cleanup
                $i = str_replace( "\"", "", $i );
               
                if ( isset( $csv_headers[$index] ) ) { 
                    $ch = $csv_headers[$index];
                    $pos = strpos ( $i, $multiSeparator );

                    if ( $ch == 'title' ) { 
                        $fields['title'] = $i;

                    } else if ( $ch == 'cf_gender' ) { 

                        $i = strtolower( $i );
                        $i = substr( $i, 0, 1 );
                        $gender = "not-set";
                        if ( $i == "m"  ) {  $gender = "male";
                        } else if ( $i == "f"  ) {  $gender = "female"; }
                        $fields['cf_gender'] = $gender;

                    } else if ( $ch == 'cf_notes' ) {      
                    //} else if ( $ch == 'cf_notes' || $ch == 'cf_dob' || $ch == 'cf_join_date' ) {   
                        $fields[$ch][] = $i;

                    } else {

                        if ($pos === false) {
                            $fields[$ch][] = [ "value" => $i ];
                        } else {
                            $multivalued = explode( $multiSeparator, $i );
                            foreach ( $multivalued as $mx ) { 
                               //$fields[$ch][] = [ "value" => $mx ]; 
                               $fields[$ch][] = [ "value" => trim($mx) ]; 
                            }
                        }

                    }
                }
            }
            
            //add person
            $people[] = array ( $fields );
            unset ( $fields );

        }
/******************************************************************************/
        //close the file
        fclose( $file_data );
        
        
        $unique = array();
        foreach ( $csv_headers as $ci => $ch ) { 
            foreach ( $dataRows as $ri => $row ) {
                $unique[$ci][] = $row[$ci];
            }
        }
        
        
        foreach ( $unique as $ci => $list ) {             
            $unique[$ci] = array_unique( $list ); 
            
            asort( $unique[$ci] ); //sort-the-value(s)
            
            $ch = $csv_headers[$ci];  
            //if ( isset($myOptionalFields['fields'][$ch]['type'])
            //         &&$myOptionalFields['fields'][$ch]['type']=='multi_select' ) { 
                //$multiSeparator = ';';
                foreach ( $unique[$ci] as $ui => $uv ) { 

                    $pos = strpos ( $uv, $multiSeparator );
                    if ( $pos === false ) {

                    } else {
                        unset ( $unique[$ci][$ui] );
                        $multivalued = explode( $multiSeparator, $uv );
                        foreach ( $multivalued as $mxid => $mx ) { 
                            $unique[$ci][] = trim( $mx );
                        }
                    }
                }

                $unique[$ci] = array_unique( $unique[$ci] );
                asort ( $unique[$ci] ); //sort-the-value(s)
            //}
        }
        
        
        return ['people' => $people, 
                'tempContactsData' => $tempContactsData, 
                'uploadeFileHeaders' => $uploadeFileHeaders, 
                'myOptionalFields' => $myOptionalFields, 
                'csvHeaders' => $csv_headers, 
                'conHeadersInfo' => $conHeadersInfo, 
                'unique' => $unique, 
                'delimeter' => $delimeter, 
                'multiSeperator' => $multiSeparator];
    }
       
    public function preview_process (
        $csv_data = [],       
        $csv_headers = [], 
        $value_mapper_idata = [], 
        $value_mapper_data = [], 
        $file_assigned_to = '', 
        $file_source = '', 
        $delimeter = ','
    ) { 
            return self::process_data(
       $csv_data, 
       $csv_headers, 
       $value_mapper_idata, 
       $value_mapper_data, 
       $file_assigned_to, 
       $file_source, 
       $delimeter);

    }
    
    public function preview (
        $filepath, 
        $csv_data,
        $csv_headers,
        $delimeter = ',',
        $multiseperator = ';',
        $file_source = 'web', 
        $file_assigned_to = '',

        $mapping_data,
        $value_mapper_idata,
        $value_mapper_data ) { 
        

        $conHeadersInfo = self::getContactHeaderInfo();
        foreach ( (array)$mapping_data as $myMapIndex=>$myMapData ) {  
            if ( $myMapData == 'IGNORE' || $myMapData == 'NONE' ) {  
                unset($mapping_data[$myMapIndex]);    
            }
        }
        
        $people = $this->preview_process($csv_data, $mapping_data, $value_mapper_idata, $value_mapper_data, $file_assigned_to, $file_source, $delimeter, $multiseperator, $filepath);
        $html = self::displayData($people);
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
<fieldset id="debug-data-<?= __LINE__ ?>" class="debug-data" style="display:none"><legend onclick="jQuery('#debug-data-<?= __LINE__ ?> section').toggle()">CSV DATA</legend><section><pre><?php print_r($csv_data) ?></pre></section></fieldset> 
<fieldset id="debug-data-<?= __LINE__ ?>" class="debug-data" style="display:none"><legend onclick="jQuery('#debug-data-<?= __LINE__ ?> section').toggle()">PPL</legend><section><pre><?php print_r($people) ?></pre></section></fieldset>


<?php echo $html; ?> 

<form method="post" enctype="multipart/form-data">
    
    <input type="hidden" name="csv_contacts" value="<?php echo esc_html( base64_encode( serialize( $people ) ) ); ?>">
    
    <?php wp_nonce_field( 'csv_correct', 'csv_correct_nonce' ); ?>
    
    <a href="<?= admin_url( 'admin.php?page=dt_utilities&tab=contact-import' ) ?>" 
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
    
    public function insert_contacts($contacts ) { 
        
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
    
    <?php 
    ob_start();
    include __DIR__ .'/script2.php';
    $jsCodeBlock = ob_get_clean();
    echo $jsCodeBlock;
    ?>
    
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
    
    private function _getFileHeaders($filepath ) { 
        
    } 
    
    
    

    public function getContactHeaderInfo( ) { 
        //return Disciple_Tools_Contacts::getContactHeaderInfo();
        
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

        foreach ( $channels as $label=>$channel ) {  
            $label = "contact_{$label}";  // @NOTE: prefix "contact_"          
            $data[$label] = ['name'=>$channel['label'], 'type'=>'standard', 'defaults'=>null];
        }
        
        $fields = Disciple_Tools_Contacts::get_contact_fields();
        if ( isset($fields) ) {  
            $data = array_merge($data,$fields);
        }        
        return $data;
        
    }
    
    public static function getMapper($name='' ) {         
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
        
        return self::colheadcompare($name);
    }
    
    public static function colheadcompare ($source_col_heading ) { 
        $column_name = null;
        $src = strtolower(trim($source_col_heading));
        //echo "souce-heading:{$src}<br/>";
        
        // @NOTE: prefix "contact_"
        $prefix = 'contact_';
        
        if ( array_search($src, self::$contact_name_headings)>0 ) {  
            $column_name = 'title';         
        } else if ( array_search($src, self::$contact_phone_headings)>0 ) { 
            $column_name = "{$prefix}phone"; 
            
        } else if ( array_search($src, self::$contact_email_headings)>0 ) { 
            $column_name = "{$prefix}email";
            
        } else if ( array_search($src, self::$contact_address_headings)>0 ) { 
            $column_name = "{$prefix}address";             

        } else {
            //get custom contact fields added by user
            //$custom_field_options = dt_get_option( "dt_field_customizations" );
            //if ( isset($custom_field_options['contacts'][$src]) ) {  
            //    $column_name = $src; 
            //}
            $fields = Disciple_Tools_Contacts::get_contact_fields();
            if ( isset($fields[$src]) ) {  
                $column_name = $src;
            } else {
                $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
                if ( isset($channels[$src]) ) {  
                    //$column_name = $src;
                    $column_name = "{$prefix}{$src}";
                }
            }
            
            //try to match the field label
            //assigned_to => ['name'=>"Assigned To"]
            if ( $column_name==null ) { 
                foreach ( $fields as $f=>$field ) {     
                    if ( isset($field['name'])&&strtolower(trim($field['name']))==$src ) { 
                        $column_name = $f;
                    }
                }            
            }
            
            if ( $column_name==null ) { 
                $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
                foreach ( $channels as $f=>$field ) { 
                    if ( isset($field['name'])&&strtolower(trim($field['name']))==$src ) { 
                        $column_name = $f;
                    }
                }
            }        
        }        
        //echo "destination-column-name:{$column_name}";
        return $column_name;
    }
    
    public static function getDropdownListHtml($field, $id = 'selector', $data = [], $selected = null, $htmlOptions = [], $allowAllTypes = true ) { 
        
        if ( isset($htmlOptions['id']) ) { unset($htmlOptions['id']); }
        
        $f = true;
        
        $html = "<select id=\"{$id}\"";          
        $html .= " data=\"".$field."\"";
        
        foreach ( $htmlOptions as $opt=>$values ) { 
            $html .= " {$opt}=\"{$values}\"";
        }        
        
        $html .= ">";       
        
        $html .= "<option value=\"NONE\">--select-one--</option>";
        $html .= "<option value=\"IGNORE\">don't import</option>";         
////////////////////////////////////////////////////////////////////////////////
        
        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $html .= "<optgroup label=\"Standard Fields\">";        
        
        $html .= "<option value=\"title\"";
        if ( $selected=='title' ) {  $html .= " selected=\"selected\""; }
        $html .= ">Contact Name</option>";
        
        foreach ( $channels as $label=>$item ) {  
            
            //if ( $label=='phone'||$label=='email'||$label=='address' ) { 
            $label = "contact_{$label}";
            //} // @NOTE: prefix "contact_"
            
            $html .= "<option value=\"{$label}\"";
            if ( $selected!=null && $selected=="{$label}" ) {  $html .= " selected=\"selected\""; }
            $html .= ">{$item['label']}</option>";
        }
        $html .= "</optgroup>"; 

        $data = Disciple_Tools_Contacts::get_contact_fields();

        $listData = array();
        foreach ( $data as $key=>$item ) {    
            $listData[$key] = $item['name'];
        }
        asort($listData);

        $html .= "<optgroup label=\"Other Fields\">";
        foreach ( $listData as $key=>$label ) {  
            
            //$f = true; 
            $type = null;            
            
            if ( isset($data[$key]['type']) ) {  
                $type = $data[$key]['type']; 
                //if ( $data[$key]['type']=='multi_select' ) { 
                //    $html .= " disabled=\"disabled\"";
                //    $f = false;
                //}
            }
            
            //if ( $f ) { 
                $html .= "<option value=\"{$key}\"";
                if ( $selected!=null && $selected==$key ) {  
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
     * @param array $dataRows
     * @param array $csv_headers
     * @param array $value_mapper_idata
     * @param array $value_mapper_data
     * @param string $assign
     * @param string $source
     * @param char $del
     */
    public static function process_data(
            $dataRows, 
            $csv_headers=[], 
            $value_mapper_idata=[], 
            $value_mapper_data=[], 
            $assign='', 
            $source='', 
            $del=',' ) { 
        
        $mulSep = ';';
        $people = [];

        $chi = array_count_values($csv_headers);
        
        foreach ( $csv_headers as $ci=>$ch ) { 
            $myMapperColumn = self::getMapper($ch);
            if ( $myMapperColumn != null && strlen($myMapperColumn)>0 ) {  
                $csv_headers[$ci] = $myMapperColumn;   
            }
        } 
        
        //handle N columns to ONE column mapping
        //phone(primary)/phone(mobile) -> phone
        $mids = []; $delCsvHeaders = [];
        foreach ( $chi as $mch=>$count ) { 
            if ( $count>1 ) { 
                $mids[$mch]['count'] = $count;
                foreach ( $csv_headers as $ci=>$ch ) { 
                    if ( $mch==$ch ) {  
                        $mids[$mch]['columIds'][] = $ci;
                    }
                }
                $mids[$mch]['primaryCol'] = $mids[$mch]['columIds'][0];
                unset($mids[$mch]['columIds'][0]); //array_pop
             }
        }

        foreach ($dataRows as $rowId=>$rowData) { 
            foreach ($rowData as $colId => $colData ) { 
                if ( isset($csv_headers[$colId]) ) { 
                    $ch = $csv_headers[$colId];
                    if ( isset($mids[$ch]['columIds']) ) { 
                        foreach ( $mids[$ch]['columIds'] as $xcolid ) { 
                            if ( $colId==$xcolid ) { 
                $dataRows[$rowId][ $mids[$ch]['primaryCol'] ] .= $mulSep.$colData;
                unset($dataRows[$rowId][$xcolid]);
                            }
                        }            
                    } 
                }  
            }     
        }

        foreach ( $mids as $ch=>$xdata ) { 
            foreach ( $xdata['columIds'] as $xcolid ) { 
                if ( isset($csv_headers[$xcolid]) ) {         
                    unset($csv_headers[$xcolid]);
                }
            }
        }

        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();

        
        foreach ($dataRows as $ri=>$row) {             
            $fields = [];
            foreach ($row as $index => $i) { 

                if ($assign != '') { $fields["assigned_to"] = (int) $assign; }
                $fields["sources"] = [ "values" => array( [ "value" => $source ] ) ];

                //cleanup
                $i = str_replace( "\"", "", $i );
            
                if ( isset($csv_headers[$index]) ) { 

                    $ch = $csv_headers[$index];

                    $type = isset($cfs[$ch]['type'])?$cfs[$ch]['type']:null;
                    if ( $type==null ) {  $type = isset($channels[$ch])?$channels[$ch]:null; }

                //echo "R{$ri}C{$index}|Ch:{$ch}|Type:{$type}<br/>";

                    if ( $ch=='title' ) { 
                        $fields[$ch] = $i;

                        //} else if ( $ch=='gender'||$ch=='cf_gender' ) { 
                        //
                        //    $i = strtolower( $i );
                        //    $i = substr( $i, 0, 1 );
                        //    $gender = "not-set";
                        //    if ($i == "m"  ) {  $gender = "male";
                        //    } else if ($i == "f"  ) {  $gender = "female"; }
                        //    $fields[$ch] = $gender;           

                    } else if ( $type=='key_select' ) { 

                        if ( isset($value_mapper_idata[$index]) ) { 
                            foreach ( $value_mapper_idata[$index] as $vmdi=>$vmdv ) { 
                if ( $vmdv==$i && isset($value_mapper_data[$index][$vmdi]) ) { 
                    $fields[$ch] = $value_mapper_data[$index][$vmdi];
                }
                            }
                        }

                    } else if ( $type=='multi_select' ) { 

                            $multivalued = explode($mulSep, $i);
                            foreach ( $multivalued as $mx ) { 

                $mx = trim($mx);

                if ( isset($value_mapper_idata[$index]) ) { 

                    foreach ( $value_mapper_idata[$index] as $vmdi=>$vmdv ) { 
                        if ( $vmdv==$mx && isset($value_mapper_data[$index][$vmdi]) ) { 
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
                    //} else if ( $type=='user_select' ) { 
                    //    $fields[$ch] = $i; /**/

                    } else if ( $type=='boolean' ) { 
                        $fields[$ch] = $i[0] === "1";

                    } else if ( $type=='date' ) { 
                        $myTempTime = strtotime($i);
                        if ( $myTempTime ) {  
                            $fields[$ch] = date('Y-m-d', $myTempTime); 
                        } else {
                            $fields[$ch] = '';
                        }


                //    } else if ( $type=='array' ) { //        
                //        foreach ( (array)$i as $av ) { 
                //            $fields[$ch][] = [ "value" => $av ];
                //        }//            
                //    //} else if ( $type=='text' ) {            
                //    //} else if ( $type=='connection' ) { 

                    } else {

                        //handle multivalued data
                        $pos = strpos($i, $mulSep);
                        if ($pos === false) {
                            $fields[$ch][] = [ "value" => $i ];
                        } else {
                            $multivalued = explode($mulSep, $i);
                            foreach ( $multivalued as $mx ) { 
                               //$fields[$ch][] = [ "value" => trim($mx) ]; 
                $fields[$ch]["values"][] = [ "value" => trim($mx) ];
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
    public static function displayData($people ) { 
        //return Disciple_Tools_Contacts::displayData($people);
        $headings = []; $del = ','; $mulSep = ';';
        
        if ( isset($people[0][0]) ) { 
            $headings = array_keys($people[0][0]);
            //echo '<pre>'; print_r($headings); echo '</pre>';
            //echo '<pre>'; print_r($people[0][0]); echo '</pre>';
            if ( isset($headings['sources']) ) {  unset($headings['sources']); }
            if ( isset($headings['assigned_to']) ) {  unset($headings['assigned_to']); }
            //if ( isset($headings['title']) ) {  unset($headings['title']); }
        }
        
        echo '<fieldset class="debug-data" style="display:none"> <legend>Heading</legend> <pre>'; print_r($headings); echo '</pre></fieldset>';
        
        $prefix = 'contact_';
        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();        
        
        $errorSummary=[];
        $html = '';

        $htmlBody = '<tbody>';
        $rowindex=0;
        foreach ( $people as $pid=>$pplData ) {  
            
            $rowindex++;
            $personData = $pplData[0];

            $htmlBody .= '<tr id="person-data-item-'.$pid.'" class="person-data-item">'; 
            
            $htmlBody .= '<td data-col-id=0>'.$rowindex.'</td>';
            
            $htmlBody .= '<td data-col-id=1 data-key="title">';
            $htmlBody .= $personData['title'];
            $htmlBody .= '</td>'; 

            foreach ( $headings as $hi=>$ch ) { 

                $type = isset($cfs[$ch]['type'])?$cfs[$ch]['type']:null;
                if ( $type==null ) {  $type = isset($channels[$ch])?$channels[$ch]:null; }

                //if ( isset( $personData[$ch][0]["value"] ) ) {  
                    //$htmlBody .= esc_html( $personData[$ch][0]["value"] );

                if ( $ch=='title' ) {     
                } else if ( $ch=='assigned_to' ) {     
                } else if ( $ch=='sources' ) { 
                } else {
                    $errors = '';

                    if ( $personData[$ch] != null || strlen(trim($personData[$ch]))>0 ) { 
                        $errors = self::validateData($ch, $personData[$ch]); 
                    }

                    $htmlBody .= '<td data-col-id='.$hi.' data-key="'.$ch.'"'; 
                    if ( $errors>0 ) {  
                        $htmlBody .= ' class="data-error"'; 

                        if ( isset($errorSummary[$ch]) ) { 
                            $errorSummary[$ch]['error-count'] = intval($errorSummary[$ch]['error-count']) + 1;
                        } else {
                            $errorSummary[$ch]['error-count'] = 1;
                        }


                    }
                    $htmlBody .= '>';

                    if ( $type=='key_select'
                        ||$type=='date'    
                        ||$type=='boolean' ) { 

                        $htmlBody .= esc_html( $personData[$ch]);

                    } else if ( ($type=='multi_select') ) { 

                        if ( isset($personData[$ch]["values"]) && is_array($personData[$ch]["values"]) ) { 
                            $values = [];
                            foreach ( $personData[$ch]["values"] as $mi=>$v ) { 
                                if ( isset($v["value"]) ) {  $values[] = esc_html( $v["value"] ); }
                            }            
                            $htmlBody .= implode($mulSep, (array)$values);
                        }        

                    } else if ( isset($personData[$ch]) ) {         

                        $values = [];
                        if ( isset($personData[$ch]["values"]) && is_array($personData[$ch]["values"]) ) { 
                            foreach ( $personData[$ch]["values"] as $mi=>$v ) { 
                                if ( isset($v["value"]) ) {  $values[] = esc_html( $v["value"] ); }
                            }

                        } else {
                            foreach ( $personData[$ch] as $mi=>$v ) { 
                                if ( isset($v["value"]) ) {  $values[] = esc_html( $v["value"] ); }
                            }
                        }  

                        $htmlBody .= implode($mulSep, (array)$values);
                    }
                    $htmlBody .= '</td>';
                } 
            }

            $htmlBody .= '<td>';
            $htmlBody .= $personData['sources']["values"][0]["value"];
            $htmlBody .= '</td>';
            

            $htmlBody .= '<td>';        
            if ( ( isset( $personData['assigned_to'] ) && $personData['assigned_to'] != '' ) ) { 
                $htmlBody .= esc_html( get_user_by( 'id', $personData['assigned_to'] )->data->display_name );
            } else { $htmlBody .= 'Not Set'; }        
            $htmlBody .= '</td>';
            
            $htmlBody .= '</tr>';        
        }
        
        $htmlBody .= '</tbody>';    
        
        
        
        $htmlHeading = '<thead>';
        $htmlHeading .= '<tr>';        
        $htmlHeading .= '<th data-col-id=0></th>';
        
        $htmlHeading .= '<th data-col-id=1>';
        $htmlHeading .= '<span class="cflabel">';
        $htmlHeading .= esc_html( translate( 'Contact Name', 'disciple_tools' ));        
        $htmlHeading .= '</span>';        
        //$htmlHeading .= '<br/><span class="cffield">title</span>';
        $htmlHeading .= '</th> '; 
        

        foreach ( $headings as $hi=>$heading ) { 
            
            if ( $heading=='title' ) { 
            } else if ( $heading=='assigned_to' ) {     
            } else if ( $heading=='sources' ) {     
            } else {

                $htmlHeading .= '<th data-col-id='.$hi.'>'; 
                $htmlHeading .='<span class="cflabel">';
                $ch = str_replace($prefix,'',$heading);
                if ( isset($cfs[$ch],$cfs[$ch]['name']) ) { 
                    $htmlHeading .= esc_html( translate( $cfs[$ch]['name'], 'disciple_tools' ));      
                } else if ( isset($channels[$ch],$channels[$ch]['label']) ) { 
                    $htmlHeading .= esc_html( translate( $channels[$ch]['label'], 'disciple_tools' ));
                }
                $htmlHeading .= '</span>';

                //$htmlHeading .= '<br/>';
                //$htmlHeading .= '<span class="cffield">';
                //$htmlHeading .= esc_html( translate( $heading, 'disciple_tools' )); 
                //$htmlHeading .= '</span>';
                $htmlHeading .= '</th>';            
            }
        }        
        
        $htmlHeading .= '<th><span class="cflabel">';
        $htmlHeading .= esc_html( translate( 'Source', 'disciple_tools' ));        
        $htmlHeading .= '</span></th>';
        
        $htmlHeading .= '<th><span class="cflabel">';
        $htmlHeading .= esc_html( translate( 'Assigned To', 'disciple_tools' ));        
        $htmlHeading .= '</span></th>';        
        
        $htmlHeading .= '</tr>';
        $htmlHeading .= '</thead>';          
        
        $html = '<table class="data-table">'.$htmlHeading.$htmlBody.'</table>';
 
        
        $errorHtml = ''; $totalDataRows = count((array)$people);
        foreach ( $errorSummary as $ch=>$err ) { 
            $columnType = null;
            $channelField = str_replace($prefix,'',$ch);  
            $errorHtml .= '<div class="error-summary-title">Column ';     //str_replace($prefix,'',$ch) 
            //$errorHtml .= '-->'.$ch.'<--';
            
            $errorHtml .= '<span class="error-field-name">';
            
            if ( isset($cfs[$ch],$cfs[$ch]['name']) ) {  
                $errorHtml .= $cfs[$ch]['name']; 
                if ( isset($cfs[$ch]['type']) ) {  $columnType = $cfs[$ch]['type']; }
            } else if ( isset($channels[$channelField],$channels[$channelField]['label']) ) {  
                $errorHtml .= $channels[$channelField]['label'];

            } else { $errorHtml .= $ch; }
            
            $errorHtml .= '</span>';            
            
            if ( $columnType!=null && in_array($columnType,['key_select','multi_select']) ) { 
                $errorHtml .= ' match allowed values';
            } else if ( $columnType=='date' ) {  $errorHtml .= ' needs to be in format yyyy-mm-dd';    
            } else { $errorHtml .= ' needs to contain valid format'; 
            }
            
            $errorHtml .= '</div>'; 
            //TODO Column Email needs to contain valid email address format
            //$errorHtml .= '<div style="clear:both;"></div>';
            
            $errorHtml .= '<div class="error-summary-details">';
            $errorHtml .= $err['error-count'].' out of '.$totalDataRows.' rows contain invalid format.<br/>';            
            $errorHtml .= '</div>';
            $errorHtml .= '<div style="clear:both;"></div>';
        }
        
        if ( count($errorSummary)>0 ) {  
            $errorSummary .= '<div class="error-summary-details">Please fix these issues before importing.</div>'; 
        }        
        $html = $errorHtml.$html;        
        return $html;        
    }    
    
    public static function displayDataVersion1($people, $conHeadersInfo, $csv_headers ) { 
        $html = '';        
        
        $html .= '<table class="data-table">';
        $html .= '<thead>';
        $html .= '<tr>';        
        $html .= '<th></th>';
        $html .= '<th>';
        $html .= '<span class="cflabel">';
        $html .= esc_html( translate( 'Contact Name', 'disciple_tools' ));        
        $html .= '</span><br/>';
        
        $html .= '<span class="cffield">title</span>';
        $html .= '</th> ';   
        
        
        foreach ( $csv_headers as $ci=>$ch ) {         
        
            if ( $ch=='title' ) {  continue; } 

            $html .= '<th>';            
            $html .= '<span class="cflabel">';
            if ( $ch=='csv_source' ) { 
                $html .= '<span style="color:green">Source</span>';
            } else if ( isset($conHeadersInfo[$ch]['name']) ) {  
                $html .= $conHeadersInfo[$ch]['name']; 
            } else {
                $html .= '<span style="color:red">UNMAPPED</span>';
            }   
            $html .= '</span><br/>';

            $html .= '<span class="cffield">';
            //$html .= esc_html( translate( 'Title', 'disciple_tools' ));
            $html .= $ch;
            $html .= '</span>';
            $html .= '</th>';
        }

        $html .= '<th><span class="cflabel">';
        $html .= esc_html( translate( 'Source', 'disciple_tools' ));        
        $html .= '</span></th>';
        
        $html .= '<th><span class="cflabel">';
        $html .= esc_html( translate( 'Assigned To', 'disciple_tools' ));        
        $html .= '</span></th>';
        $html .= '</tr>';
        $html .= '</thead>';              
        
        $html .= '<tbody>';
        
        $rowindex=0;
        foreach ( $people as $pid=>$pplData ) {  
            $rowindex++;
            $personData = $pplData[0];

            $html .= '<tr id="person-data-item-'.$pid.'" class="person-data-item">'; 
            
            $html .= '<td>'.$rowindex.'</td>';
            
            $html .= '<td>';
            $html .= $personData['title'];
            $html .= '</td>';       

            foreach ( $csv_headers as $ci=>$ch ) {   

                if ( $ch=='title' ) {  continue; }
                $html .= '<td data-key="'.$ch.'">';

                if ( $ch=='cf_gender' ) { 
                    if ( isset( $personData[$ch] ) ) {  $html .= esc_html( $personData[$ch] );    
                    } else { $html .= 'None'; }

                } else if ( $ch=='cf_notes' ) { 
                //} else if ( $ch=='cf_notes'||$ch=='cf_dob'||$ch=='cf_join_date' ) { 
                    if ( isset( $personData[$ch][0] ) ) {  $html .= esc_html( $personData[$ch][0] );
                    } else { $html .= 'None'; }

                } else {

                    if ( isset( $personData[$ch][0]["value"] ) ) {  
                        $html .= esc_html( $personData[$ch][0]["value"] );
                    } else {
                        $html .= 'None';
                    }
                }

                $html .= '</td>';    
            }


            $html .= '<td>';
            $html .= $personData['sources']["values"][0]["value"];
            $html .= '</td>';

            $html .= '<td>';        
            if ( ( isset( $personData['assigned_to'] ) && $personData['assigned_to'] != '' ) ) { 
                $html .= esc_html( get_user_by( 'id', $personData['assigned_to'] )->data->display_name );
            } else { $html .= 'Not Set'; }        
            $html .= '</td>';        
            $html .= '</tr>';        
        }
        
        $html .= '</tbody>';        
        $html .= '</table>';       
        
        return $html;        
    }
    
    public static function getAllDefaultValues( ) { 
        $data = array();
        $data['channels'] = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();        
        $data['fields'] = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();   
        
        if ( isset($data['fields']['sources']) ) {  
            //$data['fields']['sources']['default'] = Disciple_Tools_Contacts::list_sources();
            $myListSources = Disciple_Tools_Contacts::list_sources();
            foreach ( $myListSources as $myListSourceIndex=>$myListSourceLabel ) { 
                if ( !(isset($_list_source) && strlen($_list_source)>0) ) {  
                    $myListSourceLabel = $myListSourceIndex;
                }
                $data['fields']['sources']['default'][$myListSourceIndex] = ['label'=>$myListSourceLabel];
            }             
        }
        return $data;
    }
    
    private static function validateData($field, $data ) {         
        $errCount = 0; $mulSep = ';';        
        //if ( $data!=null && strlen($data)>0 ) { 
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();         
        
        if ( isset($cfs[$field]) ) { 
            
            if ( isset($cfs[$field]['type']) ) {  
                
                $type = $cfs[$field]['type'];  
                
                if ( $type=='boolean' && !filter_var($data, FILTER_VALIDATE_BOOLEAN) ) {  
                    $errCount++; 
                    
                } else if ( $type=='date' && !( preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$data) )  ) {  
                    $errCount++; 
                // } else if ( $type=='number' && is_numeric($data) ) { 
                // } else if ( $type=='array' && !is_array($data) ) { 
                } else if ( $type=='key_select' && !in_array($data, array_keys($cfs[$field]['default'])) ) {  
                    $errCount++;  
                    
                } else if ( $type=='multi_select' ) { 
                    
                    if ( isset($data['values']) ) { 
                        
                        foreach ( $data['values'] as $mx ) { 
                            
                            if ( isset($mx['value']) ) { 
                                
                                $value = trim($mx['value']);
                                
                                if ( !in_array($value, array_keys($cfs[$field]['default'])) ) {  
                                    $errCount++; 
                                }
                            }
                        }
                    }
                }
            }
            
        } else {
            
            $prefix = 'contact_'; 
            $ch = str_replace($prefix,'',$field);
            $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();  
            
            if ( isset($channels[$ch]) ) { 
                $values = [];
                if ( isset($data['values']) ) {     
                    foreach ( $data['values'] as $mx ) { 
                        if ( isset($mx['value']) ) {  
                            $values[] = trim($mx['value']);
                         }
                    }    
                } else {    
                    foreach ( $data as $mx ) { 
                        if ( isset($mx['value']) ) {  
                            $values[] = trim($mx['value']); 
                        }
                    }    
                }

                foreach ( $values as $value ) { 
                    if ( $ch=='email' ) { 
                        if ( !filter_var($value, FILTER_VALIDATE_EMAIL)) { 
                            $errCount++;
                        }
                    }  
                }
            }             
        }        
        //}        
        return $errCount;
    }    
}
