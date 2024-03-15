<?php

/**
 * Class DT_Import
 */
class DT_Import {

    /**
     * This array stores the settings for a given post type.
     * @var array
     */
    public $post_settings = [];

    /**
     * Returns the delimiter string for parsing CSVs
     * @var string
     */
    public $delimiter = ',';

    /**
     * CSV field multi separator.
     * @var string
     */
    public $multi_separator = ';';

    public static $contact_phone_headings;
    public static $contact_address_headings;
    public static $contact_email_headings;
    public static $contact_name_headings;
    public static $contact_gender_headings;
    public static $contact_notes_headings;

    /**
     * This array stores the field settings for a given post type.
     * @var array
     */
    private $post_field_settings = [];
    /**
     * This is the name of the Post Type.
     * @var string
     */
    private $post_type = '';
    /**
     * This is the label of the Post Type.
     * @var string
     */
    private $post_label_singular = '';
    /**
     * This is the plural label of the Post Type.
     * @var string
     */
    private $post_label_plural = '';

    /**
     * DT_Import constructor.
     *
     * @param $post_type
     */
    public function __construct( $post_type ) {
        $post_type_object = get_post_type_object( $post_type );
        $post_type_labels = get_post_type_labels( $post_type_object );

        if ( ! is_null( $post_type_object ) ) {
            $this->post_type           = $post_type_object->name;
            $this->post_label_singular = $post_type_labels->singular_name;
            $this->post_label_plural   = $post_type_labels->name;
            $this->post_settings       = DT_Posts::get_post_settings( $post_type_object->name );
            $this->post_field_settings = DT_Posts::get_post_field_settings( $post_type_object->name );
        }

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

    private function dt_sanitize_post_request_array_field( $post, $key ) {
        if ( !isset( $post[$key] ) ){
            return false;
        }
        $post[$key] = disciple_tools_import_sanitize_array( $post[$key] );
        return $post[$key];
    }

    private function main_column() {
        $run = true;
        if ( !is_admin() ) {
            $run = false;
        }

        if ( isset( $_POST['csv_import_nonce'] )
             && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_import_nonce'] ) ), 'csv_import' )
             && $run ) {

            $csv_file_tmp_name = '';

            if ( isset( $_FILES['csv_file']['tmp_name'] ) ) {
                $csv_file_tmp_name = wp_normalize_path( $_FILES['csv_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput -- $FILES has unslash a bit further down.
            }

            if ( isset( $_FILES['csv_file']['name'] ) ) {
                // phpcs:ignore WordPress.Security.EscapeOutput
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
                $csv_file_name = wp_normalize_path( $_FILES['csv_file']['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput -- $FILES has unslash a bit further down.
                $temp_name     = isset( $_FILES['csv_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $csv_file_tmp_name ) ) : '';
                $file_parts    = explode( '.', sanitize_text_field( wp_unslash( $csv_file_name ) ) )[ count( explode( '.', sanitize_text_field( wp_unslash( $csv_file_name ) ) ) ) - 1 ];
                // phpcs:ignore WordPress.Security.EscapeOutput
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
                if ( isset( $_FILES['csv_file']['error'] ) && $_FILES['csv_file']['error'] > 0 ) {
                    esc_html_e( 'ERROR UPLOADING FILE', 'disciple_tools' );
                    $this->go_back();
                    exit;

                } else if ( $file_parts != 'csv' ) {
                    esc_html_e( 'NOT CSV', 'disciple_tools' );
                    $this->go_back();
                    exit;

                } else if ( mb_detect_encoding( file_get_contents( $temp_name, false, null, 0, 100 ), 'UTF-8', true ) === false ) {
                    esc_html_e( 'FILE IS NOT UTF-8', 'disciple_tools' );
                    $this->go_back();
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

                $selected_geocode_api = 'none';
                if ( isset( $_POST['selected_geocode_api'] ) ) {
                    $selected_geocode_api = sanitize_text_field( wp_unslash( $_POST['selected_geocode_api'] ) );
                }

                $import_settings = [
                    'source'                => $file_source,
                    'assigned_to'           => $file_assigned_to,
                    'data'                  => $this->mapping_process( $temp_name ),
                    'selected_geocode_api'  => $selected_geocode_api,
                    'check_for_duplicates'  => isset( $_POST['check_for_duplicates'] ),
                ];

                set_transient( 'disciple_tools_import_settings', $import_settings, 3600 * 24 );
                $this->display_field_mapping_step();

            } /**end-of condition --isset( $_FILES["csv_file"] )-- */

        } else if ( isset( $_POST['csv_mappings_nonce'] )
                    && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_mappings_nonce'] ) ), 'csv_mappings' )
                    && $run ) {

            if ( isset( $_POST['csv_mapper'] ) ) {

                $mapping_data = $this->dt_sanitize_post_request_array_field( $_POST, 'csv_mapper' );
                $value_mapperi_data = isset( $_POST['VMD'] ) ? $this->dt_sanitize_post_request_array_field( $_POST, 'VMD' ) : [];
                $value_mapper_data = isset( $_POST['VM'] ) ? $this->dt_sanitize_post_request_array_field( $_POST, 'VM' ) : [];

                $this->preview( $mapping_data, $value_mapperi_data, $value_mapper_data );

            }
        } else if ( isset( $_POST['csv_correct_nonce'] )
                    && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csv_correct_nonce'] ) ), 'csv_correct' )
                    && $run ) {

            if ( isset( $_POST['go_back'] ) ){
                $this->display_field_mapping_step();
            } else {
                $this->insert_data();
            }
            exit;

        } else {
            $this->import_form();
        }

    }

    /**
     * Tool to map fields to their correct field options
     */
    private function display_field_mapping_step() {
        $import_settings       = get_transient( 'disciple_tools_import_settings' );
        $data                  = $import_settings['data'];
        $uploaded_file_headers = $data['uploaded_file_headers'];
        $my_opt_fields         = $data['my_opt_fields'];
        $rows                  = $this->get_mapped_rows( $import_settings );
        $csv_headers           = $rows['csv_headers'];
        $unique                = $rows['unique'];
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>
                    <h1><?php esc_html_e( 'Step 2: Map Columns', 'disciple_tools' ); ?></h1>
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

                                            $this->get_dropdown_list_html( $ch, "csv_mapper_{$ci}", $dd_params );
                                            ?>

                                            <div id="helper-fields-<?php echo esc_attr( $ci ) ?>" class="helper-fields" style="display:none"></div>

                                        </td>


                                        <td class="mapper-column">
                                            <div id="unique-values-<?php echo esc_attr( $ci ) ?>"
                                                 class="unique-values"
                                                 data-id="<?php echo esc_attr( $ci ) ?>"
                                                 data-type="<?php echo esc_attr( $col_data_type ) ?>"
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
                                                                            class="value-mappers value-mapper-<?php echo esc_attr( $ci ) ?>"
                                                                            data-value="<?php echo esc_attr( $v ) ?>"
                                                                            data-subid="<?php echo esc_attr( $vi ) ?>"
                                                                            >
                                                                        <option>--Not Selected--</option>
                                                                        <?php if ( isset( $my_opt_fields['fields'][$ch]['default'] ) && is_array( $my_opt_fields['fields'][$ch]['default'] ) ):
                                                                            foreach ( $my_opt_fields['fields'][$ch]['default'] as $option_key => $option_value ): ?>
                                                                                <option value="<?php echo esc_attr( $option_key ) ?>"<?php if ( strcasecmp( $option_key, $v ) == 0 ): ?> selected="selected"<?php endif; ?>><?php echo esc_attr( $option_value['label'] ) ?></option>
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
                                               value=<?php esc_html_e( 'Next', 'disciple_tools' ) ?>>
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
                                    let h_sel = jQuery(this).attr('data-value').toLowerCase();
                                    jQuery(this).find('option').each(function(){
                                        if (
                                            h_sel == jQuery(this).attr('value').toLowerCase()
                                            || h_sel == jQuery(this).text().toLowerCase()
                                        ) {
                                            jQuery(this).attr('selected','selected');
                                        }
                                    });
                                });

                                jQuery('.value-mappers').each(function(){
                                    let h_sel = jQuery(this).data('value');

                                    if (h_sel && h_sel.toLowerCase) {
                                        jQuery(this).find('option').each(function () {
                                            if (h_sel.toLowerCase() == jQuery(this).text().toLowerCase()) {
                                                jQuery(this).attr('selected', 'selected');
                                            }
                                        });
                                    }
                                });

                            } else {
                                jQuery('#unique-values-'+id).hide();
                            }
                        }
                    </script>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function import_form() {
            $geocode_apis_are_available = false;
            $available_geocode_apis = [];
        if ( Disciple_Tools_Google_Geocode_API::get_key() ) {
            array_push( $available_geocode_apis, 'Google' );
            $geocode_apis_are_available = true;
        }
        if ( DT_Mapbox_API::get_key() ) {
            array_push( $available_geocode_apis, 'Mapbox' );
            $geocode_apis_are_available = true;
        }
        ?>
        <!-- Box -->
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th><h1><?php esc_html_e( 'Step 1: Upload File', 'disciple_tools' ); ?></h1></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>


                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'csv_import', 'csv_import_nonce' ); ?>
                        <table class="widefat striped">
                            <tr>
                                <td style="min-width: 30%">
                                    <label for="csv_file"><?php esc_html_e( 'Select your csv file (comma separated file)' ) ?></label><br>

                                </td>
                                <td style='width: 70%'>
                                    <input class='button' type='file' name='csv_file' id='csv_file'/>
                                </td>
                            </tr>
                            <?php
                            if ( isset( $this->post_settings['fields']['sources'] ) ):
                                ?>
                                <tr>
                                    <td>
                                        <label for="csv_source">
                                            <?php
                                            $post_label_plural = $this->post_label_plural;
                                            printf( 'Where did these %s come from? Add a source.', esc_attr( $post_label_plural ) );
                                            ?>
                                        </label>
                                    </td>
                                    <td>
                                        <select name="csv_source" id="csv_source">
                                            <?php
                                            $sources = isset( $this->post_settings['fields']['sources']['default'] ) ? $this->post_settings['fields']['sources']['default'] : [];
                                            foreach ( $sources as $key => $value ) {
                                                ?>
                                                <option value=<?php echo esc_html( $key ); ?>><?php echo esc_html( $value['label'] ); ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php
                            endif;
                            if ( isset( $this->post_settings['fields']['assigned_to'] ) ):
                                ?>
                                <tr>
                                    <td>
                                        <label for="csv_assign">
                                            <?php esc_html_e( 'Which user do you want these assigned to?', 'disciple_tools' ) ?>
                                        </label><br>
                                    </td>
                                    <td>
                                        <select name="csv_assign" id="csv_assign">
                                            <option value=""></option>
                                            <?php
                                            $args  = [
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
                                <?php
                            endif;
                            if ( $geocode_apis_are_available ) {
                                ?>
                                <tr>
                                    <td>
                                        <label for="selected__geocode_api">
                                            <?php esc_html_e( 'Use the following Geocode API', 'disciple_tools' ); ?>
                                        </label>
                                    </td>
                                    <td>
                                        <select name="selected_geocode_api" id="selected_geocode_api">
                                            <?php
                                            foreach ( $available_geocode_apis as $api ) {
                                                ?>
                                                <option value="<?php echo esc_html( $api ) ?>"><?php echo esc_html( $api ); ?></option>
                                                <?php
                                            }
                                            ?>
                                                <option value="none">None</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php
                            }
                            if ( $this->post_type === 'contacts' ): ?>
                                <tr>
                                    <td>
                                            Merge with Existing
                                    </td>
                                    <td>
                                        <label>
                                        <input type='checkbox' name='check_for_duplicates' id='check_for_duplicates'>
                                            <?php echo esc_html( printf( 'Instead of creating new %1$s, update existing %2$s that have the same email or phone number if a match is found.', $this->post_label_plural, $this->post_label_plural ) ); ?>
                                        </label>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td>
                                    <p class="submit">
                                        <input type="submit"
                                               name="submit"
                                               id="submit"
                                               class="button"
                                               value="<?php esc_html_e( 'Proceed to Mapping Fields', 'disciple_tools' ) ?>">
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

    private function right_column() {
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
                    <p><?php esc_html_e( 'Example CSV\'s:', 'disciple_tools' ); ?></p>
                    <ul>
                        <li>
                            <a href="<?php echo esc_attr( plugin_dir_url( __DIR__ ) ); ?>../assets/example_contacts.csv"><?php esc_html_e( 'Contacts', 'disciple_tools' ); ?></a>
                        </li>
                        <li>
                            <a href="<?php echo esc_attr( plugin_dir_url( __DIR__ ) ); ?>../assets/example_groups.csv"><?php esc_html_e( 'Groups', 'disciple_tools' ); ?></a>
                        </li>
                    </ul>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
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

    public function get_post_name() {
        return $this->post_type;
    }

    public function get_post_label() {
        return $this->post_label_plural;
    }

    private function go_back() {
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
                    <a href="" class="button button-primary"> <?php esc_html_e( 'Back', 'disciple_tools' ) ?> </a>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->

        <?php
    }

    public function mapping_process( $filepath ) {

        $delimiter       = $this->delimiter;
        $incl_headers    = 'yes';
        $multi_separator = $this->multi_separator;

        //open file
        $file_data = fopen( $filepath, 'r' );

        $data_rows = array();
        while ( $row = fgetcsv( $file_data, 0, $delimiter, '"', '"' ) ){
            // skip row if empty
            if ( empty( array_filter( $row ) ) ){
                continue;
            }
            $data_rows[] = $row;
        }
        //close the file
        fclose( $file_data );

        $field_options         = $this->post_field_settings;
        $uploaded_file_headers = [];
        $my_opt_fields         = $this->get_all_default_values();
        $headers_info          = $this->get_header_info();
        $headers_info_keys     = array_keys( $headers_info );

        if ( $incl_headers == 'yes' && isset( $data_rows[0] ) ) {
            $csv_headers           = $data_rows[0];
            $uploaded_file_headers = $data_rows[0];
            unset( $data_rows[0] );

        } else {
            //if csv headers are not provided
            $csv_headers = $headers_info_keys;
        }

        foreach ( $data_rows as $ri => $row ) {
            foreach ( $row as $index => $cell ) {
                $data_rows[$ri][$index] = mb_convert_encoding( $cell, 'UTF-8', mb_detect_encoding( $cell ) );
            }
        }
        return [
            'raw_rows'              => $data_rows,
            'uploaded_file_headers' => $uploaded_file_headers,
            'my_opt_fields'         => $my_opt_fields,
            'csv_headers'           => $csv_headers,
            'headers_info'          => $headers_info,
            'delimiter'             => $delimiter,
            'multi_separator'       => $multi_separator
        ];
    }

    public function get_mapped_rows( $import_settings ){

        $file_source = $import_settings['source'];
        $file_assigned_to = $import_settings['assigned_to'];
        $data_rows = $import_settings['data']['raw_rows'];
        $csv_headers = $import_settings['data']['csv_headers'];
        $multi_separator = $import_settings['data']['multi_separator'];
        $delimiter = $import_settings['data']['delimiter'];
        $data            = [];

        //correct csv headers
        foreach ( $csv_headers as $ci => $ch ) {
            $mapped_column = $this->find_field_for_col_header( $ch );
            if ( $mapped_column != null && strlen( $mapped_column ) > 0 ) { $csv_headers[$ci] = $mapped_column; }
        }

        //loop over array
        foreach ( $data_rows as $ri => $row ) {

            $fields = [];

            if ( $file_assigned_to != '' ) { $fields['assigned_to'] = (int) $file_assigned_to; }

            $fields['sources'] = [ 'values' => array( [ 'value' => $file_source ] ) ];

            foreach ( $row as $index => $i ) {

                //cleanup
                $i = str_replace( '"', '', $i );

                if ( isset( $csv_headers[$index] ) ) {
                    $ch = $csv_headers[$index];
                    $pos = strpos( $i, $multi_separator );

                    if ( $ch == 'title' ) {
                        $fields['title'] = $i;

                    } else if ( $ch == 'cf_gender' ) {

                        $i = strtolower( $i );
                        $i = substr( $i, 0, 1 );
                        $gender = 'not-set';
                        if ( $i == 'm' ){ $gender = 'male';
                        } else if ( $i == 'f' ){ $gender = 'female'; }
                        $fields['cf_gender'] = $gender;

                    } else if ( $ch == 'cf_notes' ) {
                        //} else if ( $ch == 'cf_notes' || $ch == 'cf_dob' || $ch == 'cf_join_date' ) {
                        $fields[$ch][] = $i;

                    } else {

                        if ( $pos === false ) {
                            if ( isset( $field_options[$ch] ) && in_array( $ch, [ 'multi_select', 'communication_channel', 'key_select' ] ) ){
                                if ( !empty( trim( $i ) ) ) {
                                    $fields[$ch][] = [ 'value' => $i ];
                                }
                            } else {
                                $fields[$ch] = $i;
                            }
                        } else {
                            $multivalued = explode( $multi_separator, $i );
                            foreach ( $multivalued as $mx ) {
                                if ( !empty( trim( $mx ) ) ) {
                                    //$fields[$ch][] = [ "value" => $mx ];
                                    $fields[$ch][] = [ 'value' => trim( $mx ) ];
                                }
                            }
                        }
                    }
                }
            }

            //add data
            $data[] = array( $fields );
            unset( $fields );

        }


        $unique       = array();
        foreach ( $csv_headers as $ci => $ch ) {
            foreach ( $data_rows as $ri => $row ) {
                $unique[ $ci ][]       = $row[ $ci ];
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

            //Too many values means this filed is likely not intended to be mapped. Breaks the form submit.
            if ( count( $unique[$ci] ) > 50 ) {
                unset( $unique[$ci] );
            }
        }

        return [
            'unique' => $unique,
            'rows' => $data,
            'csv_headers' => $csv_headers
        ];
    }

    public function get_header_info() {

        $data          = [];
        $post_settings = $this->post_settings;
        $channels      = $post_settings['channels'];

        foreach ( $channels as $label => $channel ) {
            $label = sprintf( '%s_%s', strtolower( $this->post_label_singular ), $label );  // @NOTE: prefix %singular_post_type_label_% e.g. contact_

            $data[$label] = [
                'name' => $channel['label'],
                'type' => 'standard',
                'defaults' => null
            ];
        }

        $fields = $this->post_field_settings;
        if ( isset( $fields ) ) {
            $data = array_merge( $data, $fields );
        }

        return $data;
    }

    public function preview( $mapping_data = [], $value_mapperi_data = [], $value_mapper_data = [] ) {
        foreach ( (array) $mapping_data as $my_id => $my_data ){
            if ( $my_data == 'IGNORE' || $my_data == 'NONE' ){
                unset( $mapping_data[$my_id] );
            }
        }

        $import_settings                                  = get_transient( 'disciple_tools_import_settings' );
        $import_settings['mapping'] = [
            'mapping_data' => $mapping_data,
            'value_mapperi_data' => $value_mapperi_data,
            'value_mapper_data' => $value_mapper_data
        ];
        $mapped_data                  = $this->process_data( $import_settings, $mapping_data, $value_mapperi_data, $value_mapper_data );

        set_transient( 'disciple_tools_import_settings', $import_settings, 3600 * 24 );
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

                    <?php $this->display_data( $mapped_data );?>


                    <form method="post" enctype="multipart/form-data">

                        <?php wp_nonce_field( 'csv_correct', 'csv_correct_nonce' ); ?>

                        <input type="submit" name="go_back" class="button button-primary" value="<?php esc_html_e( 'Back - Something is wrong!', 'disciple_tools' ) ?>" />

                        <input type="submit" name="submit"
                               id="submit"
                               style="background-color:#4CAF50; color:white"
                               class="button"
                               value=<?php esc_html_e( 'Import', 'disciple_tools' ) ?> />

                    </form>


                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function insert_data() {
        $import_settings = get_transient( 'disciple_tools_import_settings' );
        $selected_geocode_api = $import_settings['selected_geocode_api'];
        $data_keys       = array_filter( array_keys( $import_settings ), 'is_numeric' );
        foreach ( $data_keys as $data_key ) {
            $data[] = $import_settings[ $data_key ];
        }
        $mapped_data = $this->process_data( $import_settings, $import_settings['mapping']['mapping_data'], $import_settings['mapping']['value_mapperi_data'], $import_settings['mapping']['value_mapper_data'] );
        $data = disciple_tools_import_sanitize_array( $mapped_data );

        // Decode utf8 encoded values post transient, ahead of post record creation
        $data = disciple_tools_import_array_utf8_decode( $data );

        $js_data = [];
        foreach ( $data as $num => $f ) {
            $js_array = ( isset( $f[0] ) ) ? wp_json_encode( $f[0] ) : [];
            if ( false !== $js_array && !empty( $f ) ) {
                $js_data[] = $js_array;
            }
        }
        $rest_url = rest_url() . 'dt-posts/v2/' . $this->post_type . '?silent=true';
        if ( $import_settings['check_for_duplicates'] ){
            $rest_url .= '&check_for_duplicates=contact_phone,contact_email';
        }
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <tbody>
            <tr>
                <td>
                    <div
                        id="import-logs"
                        style="max-height: 150px; overflow: hidden; overflow-y: auto;"
                    ></div>
                    <div id="data-links">&nbsp;</div>

                    <!-- Notices -->
                    <div id="notice_Creating" class="notice notice-warning">
                        <div style="height: 50px; line-height: 50px;">
                            <span>
                                <?php
                                $num = count( $js_data );
                                printf( 'Creating %s %s DO NOT LEAVE THE PAGE until the "All Done" message appears.', esc_attr( $num ), esc_attr( $this->post_label_plural ) );
                                ?>
                            </span>
                        </div>
                    </div>

                    <div id="notice_Created" class="notice notice-success">
                        <div style="height: 50px; line-height: 50px;">
                            <span>
                                <?php
                                $num = count( $js_data );
                                printf( 'Created all %s %s.', esc_attr( $num ), esc_attr( $this->post_label_plural ) );
                                ?>
                            </span>
                        </div>
                    </div>
                    <!-- e.o Notices -->

                    <!-- Scripts -->
                    <script type="text/javascript">
                        let pid = 1;
                        let completed = false;
                        const creating = document.getElementById('notice_Creating');
                        const created = document.getElementById('notice_Created');

                        // import process
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
                            <?php
                            $lowercase_post_type = strtolower( $this->post_type );
                            ?>
                            let rest_route_post_type = "<?php echo esc_attr( $lowercase_post_type ); ?>";
                            let rest_url = "<?php echo esc_url_raw( $rest_url ); ?>";
                            const url_add_location_grid_meta = "<?php echo esc_url_raw( rest_url() ); ?>dt_import/v1/add_location_grid_meta";
                            const url_update_post = "<?php echo esc_url_raw( rest_url() ); ?>dt-posts/v2/" + rest_route_post_type;

                            const geocoderType = '<?php echo esc_html( $selected_geocode_api ); ?>'

                            // sect : modifying item
                            // removing contact_address from item to avoid address duplication
                            const modifiedItem = JSON.parse(item);
                            const tmpLocations = modifiedItem.contact_address?.values || modifiedItem.location_grid?.values || [];

                            const arrLocations = [];

                            tmpLocations.forEach(l => {
                                arrLocations.push(l.value);
                            });

                            const location = arrLocations.join(';');

                            // If geolocation enabled, then remove address
                            let geolocationEnabled = (geocoderType !== 'none' && location !== '');
                            if (geolocationEnabled) {
                                delete (modifiedItem.contact_address);
                            }
                            const modifiedItemString = JSON.stringify(modifiedItem);
                            // e.o. sect : modifying item

                            jQuery.ajax({
                                type: "POST",
                                data: modifiedItemString,
                                contentType: "application/json; charset=utf-8",
                                dataType: "json",
                                url: rest_url,
                                beforeSend: function(xhr) {
                                    xhr.setRequestHeader('X-WP-Nonce', "<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ); ?>");
                                },
                                success: function(record) {

                                    // if geocode api is not selected 'none'
                                    if (geolocationEnabled) {
                                        // adding location grid meta
                                        const payload = {
                                            id: record.ID,
                                            post_type: record.post_type,
                                            geocoder: geocoderType,
                                            address: location
                                        }

                                        jQuery.ajax({
                                            type: 'POST',
                                            data: JSON.stringify(payload),
                                            contentType: 'application/json; charset=utf-8',
                                            dataType: 'json',
                                            url: url_add_location_grid_meta,
                                            beforeSend: function(xhr) {
                                                xhr.setRequestHeader('X-WP-Nonce', "<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ); ?>");
                                            },
                                            success: function(data) {
                                                const responseGridMeta = data;

                                              if(!responseGridMeta.has_valid_address) {
                                                    addAddress(tmpLocations, url_update_post, record, responseGridMeta, done);

                                                } else {
                                                    showAddedRow(pid, responseGridMeta, record.permalink, record.name);
                                                    done();
                                                }
                                            },
                                            error: function(err) {
                                                alert('Error occured. Please try again.');
                                                console.error(err);
                                                t('PID#' + pid + ' Error occurred. please try again');
                                            }
                                        });

                                    }else {
                                        showAddedRow(pid, null, record.permalink, record.name);
                                        done();
                                    }
                                },
                                error: function(xhr) { // if error occured
                                    alert("Error occured.please try again");
                                    console.log("%o",xhr);
                                    t('PID#'+pid+' Error occurred. please try again');
                                }
                            });
                        }

                        // add address
                        function addAddress(tmpLocations, url_update_post, record, responseGridMeta, done) {

                            const updatePayload = {
                                contact_address: {
                                    values: tmpLocations
                                }
                            }

                            // update post : add address
                            jQuery.ajax({
                                type: "POST",
                                data: JSON.stringify(updatePayload),
                                contentType: "application/json; charset=utf-8",
                                dataType: "json",
                                url: url_update_post + '/' + record.ID + '?silent=true',
                                beforeSend: function(xhr) {
                                    xhr.setRequestHeader('X-WP-Nonce', "<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ); ?>");
                                },
                                success: function(record) {
                                    showAddedRow(pid, responseGridMeta, record.permalink, record.name);
                                    done();
                                },
                                error: function () {
                                    alert("Error occured.please try again");
                                    console.log("%o",xhr);
                                    t('PID#'+pid+' Error occurred. please try again');
                                }
                            });

                        }

                        // an all-done action
                        function doDone() {
                            console.log('All Done!');
                            completed = true;
                            t('All Done');
                            jQuery("#back").show();
                        }

                        function t(m){
                            let el, v;
                            el = document.getElementById("import-logs");
                            v = el.innerHTML;
                            v = v + '<br/>' + m;
                            el.innerHTML = v;
                            el.scrollTop = el.scrollHeight;
                            showNotice();
                        }

                        function reset(){
                            document.getElementById("import-logs").value = '';
                        }

                        function showNotice() {
                            if(completed) {
                                creating.style.display = 'none';
                                created.style.display = 'block';
                            } else {
                                creating.style.display = 'block';
                                created.style.display = 'none';
                            }
                        }

                        function showAddedRow(pid, data, permalink, name = null) {
                            console.group(pid + ' added.');
                            console.log(data);
                            t(`PID# ${pid} done. <a href="${permalink}" target="_blank">See ${name ? name : pid}</a>`);
                            console.groupEnd();
                        }

                        reset();
                        t('started processing queue!');

                        // start processing queue!
                        let queue = <?php echo wp_json_encode( $js_data ); ?>;
                        process(queue, 5, doEach, doDone);
                    </script>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function find_field_for_col_header( $source_col_heading = '' ) {
        $column_name = null;
        $src = strtolower( trim( $source_col_heading ) );
        $prefix = sprintf( '%s_', strtolower( $this->post_label_singular ) );

        if ( array_search( $src, self::$contact_name_headings ) > 0 ) {
            $column_name = 'title';
        } else if ( array_search( $src, self::$contact_phone_headings ) > 0 ) {
            $column_name = "{$prefix}phone";

        } else if ( array_search( $src, self::$contact_email_headings ) > 0 ) {
            $column_name = "{$prefix}email";

        } else if ( array_search( $src, self::$contact_address_headings ) > 0 ) {
            $column_name = 'contact_address';

        } else {
            $fields = $this->post_field_settings;
            if ( isset( $fields[$src] ) ) {
                $column_name = $src;
            } else {
                $channels = $this->post_settings['channels'];
                if ( isset( $channels[$src] ) ) {
                    //$column_name = $src;
                    $column_name = "{$prefix}{$src}";
                } else if ( isset( $channels[$prefix.$src] ) ) {
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
                $channels = $this->post_settings['channels'];
                foreach ( $channels as $f => $field ) {
                    if ( isset( $field['name'] ) && strtolower( trim( $field['name'] ) ) == $src ) {
                        $column_name = $f;
                    }
                }
            }
        }
        return $column_name;
    }

    public function get_dropdown_list_html( $field, $id = 'selector', $html_options = [] ) {

        if ( isset( $html_options['id'] ) ) { unset( $html_options['id'] ); }

        $channels = $this->post_settings['channels'];
        $data     = $this->post_field_settings;
        ?>
        <select id="<?php echo esc_html( $id ); ?>"
            <?php foreach ( $html_options as $opt => $values ) {
                echo esc_html( $opt ) . ' ="' . esc_html( $values ) . '"';
            } ?>
        >

            <option value="NONE">--select-one--</option>
            <option value="IGNORE">don't import</option>

            <optgroup label="Standard Fields">

                <option
                    value="title" <?php selected( $field == 'title' ) ?> ><?php esc_attr( $this->post_label_singular ); ?>
                    Name
                </option>
                <option
                    value="notes" <?php selected( $field == 'notes' ) ?> ><?php esc_attr( $this->post_label_singular ); ?>
                    Notes
                </option>

                <?php
                foreach ( $channels as $label => $item ) {
                    if ( 'address' == $label ) {
                        $label = 'contact_address';
                    } else {
                        $label = sprintf( '%s_%s', strtolower( $this->post_label_singular ), $label );
                    }
                    ?>
                    <option value="<?php echo esc_html( $label ); ?>"
                        <?php selected( $field != null && $field == $label ) ?>
                    ><?php echo esc_html( $item['label'] ); ?></option>
                <?php } ?>
            </optgroup>

            <?php
            $list_data = array();
            foreach ( $data as $key => $item ) {
                if ( $this->valid_field_type_mapping( $item['type'] ) ) {
                    $list_data[ $key ] = $item['name'];
                }
            }
            asort( $list_data );

            ?>
            <optgroup label="Other Fields">
                <?php
                $data_field = sprintf( '%s_%s', strtolower( $this->post_label_singular ), $field );
                foreach ( $list_data as $key => $label ) {
                    if ( $key === 'contact_address' ) {
                        continue;
                    }
                    ?>
                    <option value="<?php echo esc_html( $key ); ?>" <?php selected( $field != null && $field == $key || $data_field != null && $data_field == $key ) ?>><?php echo esc_html( $label ); ?></option>
                <?php } ?>
            </optgroup>
        </select>
        <?php
    }

    private function valid_field_type_mapping( $field_type ): bool {
        return in_array( trim( strtolower( $field_type ) ), [
            'key_select',
            'tags',
            'multi_select',
            'boolean',
            'date',
            'communication_channel',
            'user_select',
            'text',
            'textarea',
            'number',
            'location',
            'connection'
        ] );
    }

    public function process_data( $import_settings, $mapping_data = [], $value_mapperi_data = [], $value_mapper_data = [] ) {
        $csv_headers     = $mapping_data;
        $data_rows       = $import_settings['data']['raw_rows'] ?? [];
        $assign          = $import_settings['assigned_to'] ?? '';
        $source          = $import_settings['source'] ?? '';
        $multi_separator = $this->multi_separator;
        $data            = [];

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
        $cfs = $this->post_field_settings;

        foreach ( $data_rows as $ri => $row ) {
            $fields = [];
            if ( $assign != '' ) {
                $fields['assigned_to'] = (int) $assign;
            }
            foreach ( $row as $index => $row_value ) {


                //cleanup
                $row_value = str_replace( '"', '', $row_value );
                if ( empty( $row_value ) ) {
                    continue;
                }

                if ( isset( $csv_headers[$index] ) ) {

                    $ch = $csv_headers[$index];
                    $type = isset( $cfs[$ch]['type'] ) ? $cfs[$ch]['type'] : null;

                    if ( $ch == 'title' ) {
                        $fields[$ch] = $row_value;
                    } else if ( $ch == 'notes' ) {
                        $fields[$ch] = explode( $multi_separator, $row_value );
                    } else if ( in_array( $ch, self::$contact_address_headings ) ) {
                        $multivalued = explode( $multi_separator, $row_value );
                        foreach ( $multivalued as $mx ) {

                            $mx = trim( $mx );

                            $fields[ $ch ]['values'][] = [ 'value' => $mx ];
                        }
                    } else if ( $type == 'key_select' ) {

                        if ( isset( $value_mapperi_data[ $index ] ) ) {
                            foreach ( $value_mapperi_data[ $index ] as $vmdi => $vmdv ) {
                                if ( wp_specialchars_decode( $vmdv ) == $row_value && isset( $value_mapper_data[ $index ][ $vmdi ] ) ) {
                                    $fields[ $ch ] = wp_specialchars_decode( $value_mapper_data[ $index ][ $vmdi ] );
                                }
                            }
                        }
                    } else if ( $type == 'tags' ) {
                        $multivalued = explode( $multi_separator, $row_value );

                        foreach ( $multivalued as $mx ) {

                            $mx = trim( $mx );

                            if ( isset( $value_mapperi_data[$index] ) ) {

                                foreach ( $value_mapperi_data[$index] as $vmdi => $vmdv ) {
                                    if ( $vmdv == $mx && isset( $value_mapper_data[$index][$vmdi] ) ) {
                                        $mx = $vmdv;
                                    }
                                }
                            }

                            $fields[$ch]['values'][] = [ 'value' => $mx ];
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

                            $fields[$ch]['values'][] = [ 'value' => $mx ];

                        }

                        //
                        //} else if ( $type == 'user_select' ) {
                        //    $fields[$ch] = $i; /**/

                    } else if ( $type == 'boolean' ) {
                        $fields[$ch] = in_array( $row_value, [ 'True', 'true', '1' ], true );

                    } else if ( $type == 'date' ) {
                        $my_temp_time = strtotime( $row_value );
                        if ( $my_temp_time ) {
                            $fields[$ch] = gmdate( 'Y-m-d', $my_temp_time );
                        } else {
                            $fields[$ch] = '';
                        }
                    } else if ( $type === 'communication_channel' ) {
                        //handle multivalued data
                        $pos = strpos( $row_value, $multi_separator );
                        if ( $pos === false ) {
                            $fields[$ch][] = [ 'value' => $row_value ];
                        } else {
                            $multivalued = explode( $multi_separator, $row_value );
                            foreach ( $multivalued as $mx ) {
                                $fields[$ch]['values'][] = [ 'value' => trim( $mx ) ];
                            }
                        }
                    } else if ( $type === 'user_select' ){
                        $fields[$ch] = (int) $row_value;
                    } else if ( $type === 'text' ) {
                        $fields[ $ch ] = $row_value;
                    } else if ( $type === 'number' ) {
                        $fields[ $ch ] = (int) $row_value;
                    } else if ( $type === 'location_meta' || $type === 'location' ) {
                        $fields[ $ch ]['values'][] = [ 'value' => trim( $row_value ) ];
                    } else if ( $type === 'connection' ) {
                        $multivalued = explode( $multi_separator, $row_value );
                        foreach ( $multivalued as $mx ) {

                            $mx = trim( $mx );

                            if ( !empty( $mx ) ) {
                                $fields[ $ch ]['values'][] = [ 'value' => $mx ];
                            }
                        }
                    } else {
                        //field not recognized.
                        continue;
                    }
                }
            }

            if ( ! isset( $fields['sources'] ) && ! empty( $source ) ) {
                $fields['sources'] = [ 'values' => array( [ 'value' => $source ] ) ];
            }

            //add data
            $data[] = array( $fields );

        }

        return $data;
    }

    public function display_data( $data ) {
        $headings        = [];
        $multi_separator = $this->multi_separator;
        $prefix          = sprintf( '%s_', strtolower( $this->post_label_singular ) );
        $channels        = $this->post_settings['channels'];
        $cfs             = $this->post_field_settings;
        $rowindex        = 0;
        $error_summary   = [];
        if ( isset( $data[0][0] ) ) {
            $headings = array_keys( $data[0][0] );

            if ( isset( $headings['assigned_to'] ) ) { unset( $headings['assigned_to'] ); }
        }
        ?>

        <table class="data-table">
            <thead>
            <tr>
                <th data-col-id=0></th>

                <th data-col-id=1>
                    <span class="cflabel">
                    <?php esc_attr( $this->post_label_plural ); ?>
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

                        } else if ( isset( $channels[$ch], $channels[$ch]['name'] ) ) {
                            $str = strval( $channels[$ch]['name'] );

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

            </tr>
            </thead>
            <tbody>
            <?php foreach ( $data as $pid => $data_import_data ) {
                $rowindex++;
                $import_data = $data_import_data[0]; ?>


                <tr id="group-data-item-<?php echo esc_html( $pid ) ?>" class="group-data-item">

                    <td data-col-id="0"><?php echo esc_html( $rowindex ); ?></td>

                    <td data-col-id="1" data-key="title">
                        <?php echo esc_html( $import_data['title'] ?? $import_data['name'] ); ?>
                    </td>

                    <?php foreach ( $headings as $hi => $ch ) {

                        $type = isset( $cfs[$ch]['type'] ) ? $cfs[$ch]['type'] : null;

                        if ( $type == null ){ $type = isset( $channels[$ch] ) ? $channels[$ch] : null; }

                        if ( $ch == 'title' || $ch == 'assigned_to' ) {
                            continue;
                        } else {
                            $errors = '';

                            if ( isset( $import_data[$ch] ) && ( $import_data[$ch] != null || strlen( trim( $import_data[$ch] ) ) > 0 ) ) {
                                $errors = $this->validate_data( $ch, $import_data[$ch] );

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
                                if ( empty( $import_data[$ch] ) ){
                                    continue;
                                }
                                $value = '';
                                if ( $type == 'key_select'
                                     || $type == 'date'
                                     || $type == 'boolean' ) {

                                    $value = $import_data[$ch];

                                } else if ( ( $type == 'multi_select' ) || in_array( $ch, self::$contact_address_headings ) || ( $type == 'tags' ) ) {

                                    if ( isset( $import_data[ $ch ]['values'] ) && is_array( $import_data[ $ch ]['values'] ) ) {
                                        $values = [];
                                        foreach ( $import_data[ $ch ]['values'] as $mi => $v ) {
                                            if ( isset( $v['value'] ) ) {
                                                $label    = isset( $cfs[ $ch ]['default'][ esc_html( $v['value'] ) ]['label'] ) ? $cfs[ $ch ]['default'][ esc_html( $v['value'] ) ]['label'] : esc_html( $v['value'] );
                                                $values[] = $label;
                                            }
                                        }
                                        $value = implode( $multi_separator, (array) $values );
                                    }
                                } else if ( ( $type == 'number' || $type === 'text' || $type === 'textarea' ) ) {
                                    if ( $ch == 'notes' ) {
                                        $imploded = implode( $multi_separator, $import_data[$ch] );
                                        echo esc_html( mb_convert_encoding( $imploded, mb_detect_encoding( $imploded ), 'UTF-8' ) );
                                    } else {
                                        echo esc_html( mb_convert_encoding( $import_data[$ch], mb_detect_encoding( $import_data[$ch] ), 'UTF-8' ) );
                                    }
                                } else if ( isset( $import_data[$ch] ) ) {
                                    if ( !is_array( $import_data[$ch] ) ){
                                        echo esc_html( $import_data[ $ch ] );
                                    } else {
                                        $values = [];
                                        if ( isset( $import_data[$ch]['values'] ) && is_array( $import_data[$ch]['values'] ) ) {
                                            foreach ( $import_data[$ch]['values'] as $mi => $v ) {
                                                if ( isset( $v['value'] ) ) { $values[] = esc_html( $v['value'] ); }
                                            }
                                        } else {
                                            foreach ( $import_data[$ch] as $mi => $v ) {
                                                if ( isset( $v['value'] ) ){ $values[] = esc_html( $v['value'] ); }
                                            }
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

                </tr>
            <?php } ?>

            </tbody>

        </table>

        <?php
        $total_data_rows = count( (array) $data );

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

                <?php
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

            <div class="error-summary-details">
                <?php echo esc_html( $err['error-count'] ); ?> out of <?php echo esc_html( $total_data_rows ); ?> rows contain invalid format.<br/>
            </div>
            <div style="clear:both;"></div>
        <?php }

        if ( count( $error_summary ) > 0 ) : ?>
            <div class="error-summary-details">Please fix these issues before importing.</div>
        <?php endif;

    }

    public function get_all_default_values() {
        $data           = array();
        $data['data']   = $this->post_settings['channels'];
        $data['fields'] = $this->post_field_settings;

        return $data;
    }

    private function validate_data( $field, $data ) {
        $err_count = 0;
        $cfs = $this->post_field_settings;

        if ( isset( $cfs[$field] ) ) {

            if ( isset( $cfs[$field]['type'] ) ) {
                $type = $cfs[$field]['type'];
                if ( $type == 'boolean' && !filter_var( $data, FILTER_VALIDATE_BOOLEAN ) ) {
                    $err_count++;
                } else if ( $type == 'date' && !( preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $data ) ) ) {
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
            $prefix   = sprintf( '%s_', strtolower( $this->post_label_singular ) );
            $ch       = str_replace( $prefix, '', $field );
            $channels = $this->post_settings['channels'];

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
