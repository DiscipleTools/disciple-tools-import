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

    private static function dt_sanitize_post_request_array_field( $post, $key ) {
        if ( !isset( $post[$key] ) ){
            return false;
        }
        $post[$key] = dt_import_sanitize_array( $post[$key] );
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
            $tab = 'contact';
        }

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
                'contact' => [
                    'tab' => 'contact',
                    'label' => 'Contact'
                ],
//                'location' => [
//                    'tab' => 'location',
//                    'label' => 'Location'
//                ]
            ] as $my_tab):
                $my_tab_name = "{$my_tab['tab']}";
                $my_tab_label = "{$my_tab['label']}";
                $my_tab_link = "admin.php?page={$this->token}&tab={$my_tab_name}";
                ?>

                <a href="<?php echo esc_html( $my_tab_link ) ?>"
                   class="nav-tab <?php ( $tab == $my_tab_name ) ? esc_attr_e( 'nav-tab-active', 'dt_import_export' ) : print ''; ?>">
                <?php echo esc_attr( $my_tab_label ) ?>
                </a>

            <?php endforeach; ?>

            </h2>

            <?php

            if ( $tab == 'general' ) {
                $object = new DT_Import_Export_Tab_General();
                $object->content();
            }  else if ( $tab == 'contact' ) {
                $object = new DT_Import_Export_Tab_Contact();

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


                        $file_source = null;
                        if ( isset( $_POST['csv_source'] ) ) {
                            $file_source = sanitize_text_field( wp_unslash( $_POST['csv_source'] ) );
                        }

                        $file_assigned_to = null;
                        if ( isset( $_POST['csv_assign'] ) ) {
                            $file_assigned_to = sanitize_text_field( wp_unslash( $_POST['csv_assign'] ) );
                        }

                        $import_settings = [
                            "source" => $file_source,
                            "assigned_to" => $file_assigned_to,
                            "data" => $object->mapping_process( $temp_name, $file_source, $file_assigned_to ),
                        ];
                        set_transient( "dt_import_export_settings", $import_settings, 3600 * 24 );
                        $object->display_field_mapping_step();

                    } /**end-of condition --isset( $_FILES["csv_file"] )-- */

                } else if ( isset( $_POST['csv_mappings_nonce'] )
                            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_mappings_nonce'] ) ), 'csv_mappings' )
                            && $run ) {

                    if ( isset( $_POST["csv_mapper"] ) ) {

                        $mapping_data = self::dt_sanitize_post_request_array_field( $_POST, 'csv_mapper' );
                        $value_mapperi_data = isset( $_POST['VMD'] ) ? self::dt_sanitize_post_request_array_field( $_POST, 'VMD' ) : [];
                        $value_mapper_data = isset( $_POST['VM'] ) ? self::dt_sanitize_post_request_array_field( $_POST, 'VM' ) : [];

                        $object->preview( $mapping_data, $value_mapperi_data, $value_mapper_data );

                    }
                } else if ( isset( $_POST['csv_correct_nonce'] )
                            && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_correct_nonce'] ) ), 'csv_correct' )
                            && $run ) {

                    if ( isset( $_POST["go_back"] ) ){
                        $object->display_field_mapping_step();
                    } else {
                        $object->insert_contacts();
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
            <tr>
                <th><h1><?php esc_html_e( "Step 1: Upload File", 'disciple_tools' ); ?></h1></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>

                    
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'csv_import', 'csv_import_nonce' ); ?>
                        <table class="widefat striped">
                            <tr>
                                <td>
                                    <label for="csv_file"><?php esc_html_e( 'Select your csv file (comma separated file)' ) ?></label><br>
                                    <input class="button" type="file" name="csv_file" id="csv_file" />
                                </td>
                            </tr>
<!--                            <tr style="display:none">-->
<!--                                <td>-->
<!--                                    <label for="csv_multivalued">--><?php //esc_html_e( 'Does the file contain multivalues?' ) ?><!--</label><br>-->
<!--                                    --><?php ///** <input class="checkbox" type="checkbox" name="csv_multivalued" id="csv_multivalued" onclick="setDel()" disabled="disabled" /> */ ?>
<!--                                    <input class="checkbox" type="checkbox" name="csv_multivalued" id="csv_multivalued" onclick="setDel()" disabled="disabled" checked="checked" />-->
<!--                                </td>-->
<!--                            </tr>-->
<!--                            <tr style="display:none">-->
<!--                                <td>-->
<!--                                    <label for="csv_del">--><?php //esc_html_e( "Add csv delimiter (default is fine)", 'disciple_tools' ) ?><!--</label><br>-->
<!--                                    --><?php ///** <input type="text" name="csv_del" id="csv_del" value=',' size=2 />*/ ?>
<!--                                    <select name="csv_del" id="csv_del" onclick="deactMV()" disabled="disabled">-->
<!--                                        <option value="," selected="selected">, comma</option>-->
<!--                                        <option value="|">| tab</option>-->
<!--                                        <option value=";">; semi-colon</option>-->
<!--                                        <option value=":">: colon</option>-->
<!--                                        <option value="$">$ dollar</option>-->
<!--                                    </select>-->
<!--                                </td>-->
<!--                            </tr>-->
<!--                            <tr style="display:none">-->
<!--                                <td>-->
<!--                                    <label for="csv_header">--><?php //esc_html_e( "Does the file have a header? (i.e. a first row with the names of the columns?)", 'disciple_tools' ) ?><!--</label><br>-->
<!--                                    <select name="csv_header" id="csv_header" readonly="readonly" disabled="disabled">-->
<!--                                        <option value=yes>--><?php //esc_html_e( "yes", 'disciple_tools' ) ?><!--</option>-->
<!--                                        <option value=no>--><?php //esc_html_e( "no", 'disciple_tools' ) ?><!--</option>-->
<!--                                    </select>-->
<!---->
<!--                                </td>-->
<!--                            </tr>-->
                            <tr>
                                <td>
                                    <label for="csv_source">
                                        <?php esc_html_e( "Where did these contacts come from? Add a source.", 'disciple_tools' ) ?>
                                    </label><br>
                                    <select name="csv_source" id="csv_source">
                                        <?php
                                        $post_settings = apply_filters( "dt_get_post_type_settings", [], "contacts" );
                                        $sources = isset( $post_settings["fields"]["sources"]["default"] ) ? $post_settings["fields"]["sources"]["default"] : [];
                                        foreach ( $sources as $key => $value ) {
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
            <tr>
                <th>Information</th>
            </tr>
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
            <tr>
                <th>ERROR <?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) ) ?></th>
            </tr>
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


    /**
     * Tool to map fields to their correct field options
     */
    public function display_field_mapping_step() {

        $import_settings = get_transient( "dt_import_export_settings" );
        $data = $import_settings["data"];

        $csv_headers = $data['csv_headers'];
        $con_headers_info = $data['con_headers_info'];
        $uploaded_file_headers = $data['uploaded_file_headers'];
        $my_opt_fields = $data['my_opt_fields'];
        $unique = $data['unique'];


        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>
                    <h1><?php esc_html_e( "Step 2: Map Columns", 'disciple_tools' ); ?></h1>
                </th>
            </tr>
            </thead>

            <tbody>
                
            <tr>
                <td>
                    <p><strong>Important!</strong> Data in Unmapped Columns will be skipped</p>

                    <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'csv_mappings', 'csv_mappings_nonce' ); ?>

                    <div class="mapper-table-container">
                    <table class="mapper-table">
                    <thead>
                        <tr> 
                            <th style="vertical-align:top"> Source (Uploaded File) </th>
                            <th style="vertical-align:top"> Destination (DT) </th>
                            <th style="vertical-align:top"> Unique Values/Mapper </th>
                        </tr>
                    </thead>


                    <tbody>
                    <?php

                    //correct csv headers
                    foreach ( $csv_headers as $ci => $ch ):

                        $col_data_type = isset( $my_opt_fields['fields'][$ch]['type'] ) ? $my_opt_fields['fields'][$ch]['type'] : null;

                        ?>
                        <tr class="mapper-coloumn" data-row-id="<?php echo esc_attr( $ci ) ?>">
                            <th data-field="<?php echo esc_attr( $ch ) ?>" class="src-column">
                                <?php echo esc_html( $uploaded_file_headers[$ci] ) ?>
                            </th>


                            <td class="dest-column">
                                <?php
                                $dd_params = [
                                    'name' => esc_attr( 'csv_mapper['.$ci.']' ),
                                    'class' => 'cf-mapper',
                                    'onchange' => "getDefaultValues({$ci})"
                                ];

                                self::get_dropdown_list_html( $ch, "csv_mapper_{$ci}", $dd_params );
                                ?>
                                
                                <div id="helper-fields-<?php echo esc_attr( $ci ) ?>" class="helper-fields" style="display:none"></div>

                            </td>


                            <td class="mapper-column">
                            <div id="unique-values-<?php echo esc_attr( $ci ) ?>"
                                class="unique-values"
                                data-id="<?php echo esc_attr( $ci ) ?>"
                                data-type="<?php echo esc_attr( $col_data_type ) ?>"
                                <?php /** <?php if ( $col_data_type!='key_select' ) : ?> style="display:none"<?php endif; ?>> */ ?>
                                <?php if ( !( $col_data_type == 'key_select' || $col_data_type == 'multi_select' ) ) : ?> style="display:none"<?php endif; ?>>

                                <div class="mapper-helper-text">
                                    <span class="mapper-helper-title">Map import values to DT values</span><br/>
                                    <span class="mapper-helper-description">
                                        <span class="selected-mapper-column-name"><?php echo esc_attr( $ch ) ?><?php //echo $mapper_title ?></span>
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
                                                <?php echo esc_attr( $v ) ?>
                                            <?php else : ?>
                                                <span class="empty">-blank/null-</span>
                                            <?php endif; ?>
                                        </td>


                                        <td>

                                            <input name="VMD[<?php echo esc_attr( $ci ) ?>][<?php echo esc_attr( $vi ) ?>]" type="hidden" value="<?php echo esc_attr( $v ) ?>" />

                                            <select id="value-mapper-<?php echo esc_attr( $ci ) ?>-<?php echo esc_attr( $vi ) ?>"
                                                    name="VM[<?php echo esc_attr( $ci ) ?>][<?php echo esc_attr( $vi ) ?>]"
                                                    class="value-mapper-<?php echo esc_attr( $ci ) ?>"
                                                    data-value="<?php echo esc_attr( $v ) ?>">
                                            <option>--Not Selected--</option>
                                            <?php if ( isset( $my_opt_fields['fields'][$ch]['default'] ) && is_array( $my_opt_fields['fields'][$ch]['default'] ) ):
                                                foreach ( $my_opt_fields['fields'][$ch]['default'] as $option_key => $option_value ): ?>
                                                <option value="<?php echo esc_attr( $option_key ) ?>"<?php if ( $option_key == $v ): ?> selected="selected"<?php endif; ?>><?php echo esc_attr( $option_value['label'] ) ?></option>
                                                <?php endforeach;
                                            endif; ?>
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
                                <input type="submit" name="submit" id="submit"
                                       style="background-color:#4CAF50; color:white" 
                                       class="button" 
                                       value=<?php esc_html_e( "Next", 'disciple_tools' ) ?>>
                            </td>
                        </tr>

                    </tfoot>

                    </table>
                    </div>

                    </form>

                    <script type="text/javascript">
                        let jQuery = window.jQuery
                        let cfs = <?php echo wp_json_encode( $my_opt_fields['fields'] ); ?>;

                        jQuery(document).ready(function(){
                            getAllDefaultValues();
                        });

                        function check_column_mappings(id){
                            let elements, selected, selectedValue;

                            selected = document.getElementById('csv_mapper_'+id);
                            selectedValue = selected.options[selected.selectedIndex].value;

                            elements = document.getElementsByClassName('cf-mapper');
                            for (let i = 0; i < elements.length; i++) {
                                if (i!==id && selectedValue===elements[i].value) {
                                    selected.selectedIndex = 'IGNORE';
                                    if(elements[i].value!=='IGNORE'){
                                        alert('Already Mapped!');
                                    }                       
                                }
                            }
                        }            

                        function getAllDefaultValues(){                
                            jQuery('.mapper-table tbody > tr.mapper-coloumn').each(function(){
                                let i = jQuery(this).attr('data-row-id');
                                if(typeof i !== 'undefined'){ getDefaultValues(i); }
                            });                
                        }

                        function getDefaultValues(id){                
                            let selected = document.getElementById('csv_mapper_'+id);
                            let selected_field_key = selected.options[selected.selectedIndex].value;
                            let select_field = cfs[selected_field_key]
                            let field_type = select_field ? select_field.type : null;

                            if(field_type === 'key_select' || field_type === 'multi_select'){
                                let value_mapper_select = jQuery('.value-mapper-'+id)
                                value_mapper_select.html('')
                                value_mapper_select.append('<option value="">--select-one--</option>');

                                let unique_values = jQuery('#unique-values-'+id)
                                unique_values.show();
                                unique_values.find('.selected-mapper-column-name').html( jQuery('#csv_mapper_'+id+' option:selected').text() );

                                //default-value-options
                                Object.keys(select_field.default).forEach(key=>{
                                    let h_html = `<option value="${key}">${select_field.default[key].label}</option>`
                                    value_mapper_select.append(h_html);
                                });

                                value_mapper_select.each(function(){
                                    let h_sel = jQuery(this).attr('data-value');
                                    jQuery(this).find('option').each(function(){
                                        if(h_sel===jQuery(this).attr('value')){
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
        $delimiter = ',';
        $incl_headers = 'yes';
        $multi_separator = ';';

        //open file
        ini_set( 'auto_detect_line_endings', true );
        $file_data = fopen( $filepath, "r" );

        $data_rows = array();
        while ( $row = fgetcsv( $file_data, 0, $delimiter, '"', '"' ) ) {
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

        //correct csv headers
        foreach ( $csv_headers as $ci => $ch ) {
            $mapped_column = self::find_field_for_col_header( $ch );
            if ( $mapped_column != null && strlen( $mapped_column ) > 0 ) { $csv_headers[$ci] = $mapped_column; }
        }

        //loop over array
        foreach ( $data_rows as $ri => $row ) {

            $fields = [];

            if ( $file_assigned_to != '') { $fields["assigned_to"] = (int) $file_assigned_to; }

            $fields["sources"] = [ "values" => array( [ "value" => $file_source ] ) ];

            foreach ( $row as $index => $i ) {

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
                    }
                }
            }

            //add person
            $people[] = array( $fields );
            unset( $fields );

        }
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

            foreach ( $unique[$ci] as $ui => $uv ) {

                $pos = strpos( $uv, $multi_separator );
                if ( $pos === false ) {
                    continue;
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
        }


        return [
            'people' => $people,
            'temp_contacts_data' => $temp_contacts_data,
            'uploaded_file_headers' => $uploaded_file_headers,
            'my_opt_fields' => $my_opt_fields,
            'csv_headers' => $csv_headers,
            'con_headers_info' => $con_headers_info,
            'unique' => $unique,
            'delimiter' => $delimiter,
            'multi_separator' => $multi_separator
        ];
    }

    public function preview( $mapping_data = [], $value_mapperi_data = [], $value_mapper_data = [] ) {
        foreach ( (array) $mapping_data as $my_id => $my_data ){
            if ( $my_data == 'IGNORE' || $my_data == 'NONE'){
                unset( $mapping_data[$my_id] );
            }
        }

        $import_settings = get_transient( "dt_import_export_settings" );
        $people = self::process_data( $import_settings, $mapping_data, $value_mapperi_data, $value_mapper_data );
        $import_settings["people"] = $people;
        set_transient( "dt_import_export_settings", $import_settings, 3600 * 24 );

        ?>

        <table class="widefat striped">
            <thead>
            <tr>
                <th><h1>Step 3: Confirm & Import</h1></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>

                <?php self::display_data( $people );?>


                <form method="post" enctype="multipart/form-data">

                    <?php wp_nonce_field( 'csv_correct', 'csv_correct_nonce' ); ?>

                    <input type="submit" name="go_back" class="button button-primary" value="<?php esc_html_e( "Back - Something is wrong!", 'disciple_tools' ) ?>" />

                    <input type="submit" name="submit" 
                           id="submit" 
                           style="background-color:#4CAF50; color:white" 
                           class="button" 
                           value=<?php esc_html_e( "Import", 'disciple_tools' ) ?> />
                    
                </form>


                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function insert_contacts(){
        $import_settings = get_transient( "dt_import_export_settings" );
        $contacts = $import_settings["people"];
        $contacts = dt_import_sanitize_array( $contacts );
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <tbody>
            <tr>
                <td>
                    <div id="import-logs">&nbsp;</div>
                    <div id="contact-links">&nbsp;</div>

                    <script type="text/javascript">
                    let pid = 1000;
                    function process( q, num, fn, done ) {
                        // remove a batch of items from the queue
                        let items = q.splice(0, num),
                            count = items.length;

                        // no more items?
                        if ( !count ) {
                            // exec done callback if specified
                            done && done();
                            // quit
                            return;
                        }

                        // loop over each item
                        for ( let i = 0; i < count; i++ ) {
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
                                xhr.setRequestHeader('X-WP-Nonce', "<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ); ?>");
                            },
                            success: function() {
                                console.log('done'); t('PID#'+pid+' done');
                                done();
                            },
                            error: function(xhr) { // if error occured
                                alert("Error occured.please try again");
                                console.log("%o",xhr);
                                t('PID#'+pid+' Error occurred. please try again');
                            }
                        });
                    }

                    // an all-done action
                    function doDone() {
                        console.log('All Done!'); t('All Done');
                        jQuery("#back").show();
                    }

                    function t(m){
                        let el, v;
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
                    $js_contacts = [];
                    foreach ( $contacts as $num => $f ) {
                        $js_array = wp_json_encode( $f[0] );
                        $js_contacts[] = $js_array;
                    }
                    ?>
                    <script type="text/javascript">

                    reset();
                    t('started processing queue!');

                    // start processing queue!
                    let queue = <?php echo wp_json_encode( $js_contacts ); ?>;
                    process(queue, 5, doEach, doDone);
                    </script>

                    <?php
                    $num = count( $contacts );
                    echo esc_html( sprintf( __( "Creating %s Contacts DO NOT LEAVE THE PAGE the \"All Done\" message appears", 'disciple_tools' ), $num ) );
                    ?>

                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function get_contact_header_info(){

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



    public static function find_field_for_col_header( $source_col_heading = '' ) {
        $column_name = null;
        $src = strtolower( trim( $source_col_heading ) );
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
            $fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
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
        return $column_name;
    }

    public static function get_dropdown_list_html( $field, $id = 'selector', $html_options = [] ) {

        if ( isset( $html_options['id'] ) ) { unset( $html_options['id'] ); }

        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $data = Disciple_Tools_Contacts::get_contact_fields();

        ?>
        <select id="<?php echo esc_html( $id ); ?>"
            <?php foreach ( $html_options as $opt => $values ) {
                echo esc_html( $opt ) . ' ="' . esc_html( $values ) . '"';
            } ?>
        >

            <option value="NONE">--select-one--</option>
            <option value="IGNORE">don't import</option>

            <optgroup label="Standard Fields">

                <option value="title" <?php selected( $field == 'title' ) ?> >Contact Name</option>

                <?php
                foreach ( $channels as $label => $item ) {
                    $label = "contact_{$label}";
                    ?>
                    <option value="<?php echo esc_html( $label ); ?>"
                        <?php selected( $field != null && $field == $label ) ?>
                    ><?php echo esc_html( $item['label'] ); ?></option>
                <?php } ?>
            </optgroup>

            <?php
            $list_data = array();
            foreach ( $data as $key => $item ) {
                $list_data[$key] = $item['name'];
            }
            asort( $list_data );

            ?>
            <optgroup label="Other Fields">
            <?php
            foreach ( $list_data as $key => $label ) { ?>
                <option value="<?php echo esc_html( $key ); ?>" <?php selected( $field != null && $field == $key ) ?>><?php echo esc_html( $label ); ?></option>
            <?php } ?>
            </optgroup>
        </select>
        <?php
    }

    /**
     * @param $import_settings
     * @param array $mapping_data
     * @param array $value_mapperi_data
     * @param array $value_mapper_data
     * @return array
     */
    public static function process_data( $import_settings, $mapping_data = [], $value_mapperi_data = [], $value_mapper_data = [] ){
        $csv_headers = $mapping_data;
        $data_rows = $import_settings["data"]["temp_contacts_data"] ?? [];
        $assign = $import_settings["assigned_to"] ?? "";
        $source = $import_settings["source"] ?? "";
        $multi_separator = ';';
        $people = [];
        if ( is_array( $csv_headers ) ) {
            $header_occurrence_counts = array_count_values( $csv_headers );
        } else {
            $header_occurrence_counts = [];
        }

        //handle N columns to ONE column mapping
        //phone(primary)/phone(mobile) -> phone
        $mids = [];
        foreach ( $header_occurrence_counts as $mch => $count ) {
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

        $channel_keys = [];
        foreach ( $channels as $channel_key => $val ) {
            $channel_keys[] = "contact_" . $channel_key;
        }

        foreach ( $data_rows as $ri => $row ) {
            $fields = [];
            if ( $assign != '' ) {
                $fields["assigned_to"] = (int) $assign;
            }
            foreach ($row as $index => $row_value) {


                //cleanup
                $row_value = str_replace( "\"", "", $row_value );

                if ( isset( $csv_headers[$index] ) ) {

                    $ch = $csv_headers[$index];

                    $type = isset( $cfs[$ch]['type'] ) ? $cfs[$ch]['type'] : null;
                    if ( $type == null && in_array( $ch, $channel_keys ) ) {
                        $type = 'contact_method';
                    }

                    if ( $ch == 'title' ) {
                        $fields[$ch] = $row_value;

                    } else if ( $type == 'key_select' ) {

                        if ( isset( $value_mapperi_data[$index] ) ) {
                            foreach ( $value_mapperi_data[$index] as $vmdi => $vmdv ) {
                                if ( $vmdv == $row_value && isset( $value_mapper_data[$index][$vmdi] ) ) {
                                    $fields[$ch] = $value_mapper_data[$index][$vmdi];
                                }
                            }
                        }
                    } else if ( $type == 'multi_select' ) {

                        $multivalued = explode( $multi_separator, $row_value );
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
                        $fields[$ch] = $row_value[0] === "1";

                    } else if ( $type == 'date' ) {
                        $my_temp_time = strtotime( $row_value );
                        if ( $my_temp_time ) {
                            $fields[$ch] = gmdate( 'Y-m-d', $my_temp_time );
                        } else {
                            $fields[$ch] = '';
                        }
                    } else if ( $type === "contact_method") {
                        //handle multivalued data
                        $pos = strpos( $row_value, $multi_separator );
                        if ( $pos === false ) {
                            $fields[$ch][] = [ "value" => $row_value ];
                        } else {
                            $multivalued = explode( $multi_separator, $row_value );
                            foreach ( $multivalued as $mx ) {
                                $fields[$ch]["values"][] = [ "value" => trim( $mx ) ];
                            }
                        }
                    } else {
                        //field not recognized.
                        continue;
                    }
                }
            }
            if ( !isset( $fields["sources"] ) ) {
                $fields["sources"] = [ "values" => array( [ "value" => $source ] ) ];
            }

            //add person
            $people[] = array( $fields );

        }

        return $people;
    }

    /**
     * @param array $people
     */
    public static function display_data( $people ) {
        //return Disciple_Tools_Contacts::display_data( $people );
        $headings = [];
        $multi_separator = ';';

        $prefix = 'contact_';
        $channels = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $cfs = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();

        $rowindex = 0;
        $error_summary = [];
        if ( isset( $people[0][0] ) ) {
            $headings = array_keys( $people[0][0] );
            if ( isset( $headings['assigned_to'] ) ) { unset( $headings['assigned_to'] ); }
        }
        ?>

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

                    if ( $heading == 'title' || $heading == 'assigned_to' ) {
                        continue;
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

                if ( $ch == 'title' || $ch == 'assigned_to' ) {
                    continue;
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
                                    if ( isset( $v["value"] ) ) {
                                        $label = isset( $cfs[$ch]["default"][esc_html( $v["value"] )]["label"] ) ? $cfs[$ch]["default"][esc_html( $v["value"] )]["label"] : esc_html( $v["value"] );
                                        $values[] = $label;
                                    }
                                }
                                $value = implode( $multi_separator, (array) $values );
                            }
                        } else if ( isset( $person_data[$ch] ) ) {
                            $values = [];
                            if ( isset( $person_data[$ch]["values"] ) && is_array( $person_data[$ch]["values"] ) ) {
                                foreach ( $person_data[$ch]["values"] as $mi => $v ) {
                                    if ( isset( $v["value"] ) ) { $values[] = esc_html( $v["value"] ); }
                                }
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


    public static function get_all_default_values() {
        $data = array();
        $data['channels'] = Disciple_Tools_Contact_Post_Type::instance()->get_channels_list();
        $data['fields'] = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
        return $data;
    }

    private static function validate_data( $field, $data ) {
        $err_count = 0;
        $multi_separator = ';';
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

function dt_import_sanitize_array( &$array ) {
    foreach ( $array as &$value ) {
        if ( !is_array( $value ) ) {
            $value = sanitize_text_field( wp_unslash( $value ) );
        } else {
            dt_import_sanitize_array( $value );
        }
    }
    return $array;
}
