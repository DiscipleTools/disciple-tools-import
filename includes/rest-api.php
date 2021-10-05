<?php
/**
 * Rest API example class
 */


class Disciple_Tools_Import_Endpoints
{
    public $permissions = [ 'dt_all_access_contacts', 'view_project_metrics' ];

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function has_permission(){
        $pass = false;
        foreach ( $this->permissions as $permission ){
            if ( current_user_can( $permission ) ){
                $pass = true;
            }
        }
        return $pass;
    }

    /**
     * Public and Private endpoints.
     * Public endpoints are for integrating with systems outside the Disciple.Tools site. Connection to other sites
     * can be done using the Site_Link_System class found in /disciple-tools-theme/dt-core/admin/site-link-post-type.php
     *
     * Private endpoints can use the Wordpress nonce system and user login to verify connections. Private connections
     * are used for extending the Disciple.Tools system and should be used for all plugin extensions, except those
     * integrating to outside systems.
     */
    public function add_api_routes() {

        $namespace = 'dt_import/v1';

        $public_namespace = 'dt-public/v1';
        $private_namespace = 'dt/v1';

        register_rest_route(
            $public_namespace, '/sample/public_endpoint', [
                [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'public_endpoint' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
        register_rest_route(
            $private_namespace, '/sample/private_endpoint', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'private_endpoint' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        register_rest_route(
            $namespace, '/add_location_grid_meta', [
                [
                    'methods'               => WP_REST_Server::CREATABLE,
                    'callback'              => [ $this, 'private_endpoint' ],
                    'permission_callback'   => '__return_true',
                ]
            ]
        );
    }

    public function public_endpoint( WP_REST_Request $request ) {
        $params = $this->process_token( $request );
        if ( is_wp_error( $params ) ) {
            return $params;
        }

        // run your function here

        return true;

    }

    public function private_endpoint( WP_REST_Request $request ) {
        // check permission
        if ( !$this->has_permission() ){
            return new WP_Error( "private_endpoint", "Missing Permissions", [ 'status' => 400 ] );
        }

        $params = $request->get_params();
        $params = dt_recursive_sanitize_array( $params );

        try {
            // check if geocoder apis exist
            if ( DT_Mapbox_API::get_key() || Disciple_Tools_Google_Geocode_API::get_key() ) {

                // validating address params if coordinates or just address
                $api_class = $params['geocoder'] === 'Google'
                    ? 'Disciple_Tools_Google_Geocode_API'
                    : 'DT_Mapbox_API';

                $addresses = explode( ";", $params['address'] );
                $is_valid_address = false;
                $results = [];

                foreach ($addresses as $addr) {
                    $lookup = $this->validate_lat_long( $addr );

                    // checking if address or coordinates and if coordinates splitting into latitude and longitude
                    $address = $lookup === 'coordinates'
                        ? explode( ",", preg_replace( '/\s/', '', $addr ) )
                        : $addr;

                    // getting results
                    $result = $lookup === 'coordinates'
                        ? (
                            $params['geocoder'] === 'Google'
                                ? $api_class::query_google_api_reverse( $addr )
                                : $api_class::reverse_lookup( $address[1], $address[0] )
                        ) : (
                            $params['geocoder'] === 'Google'
                                ? $api_class::query_google_api( $address, 'core' )
                                : $api_class::forward_lookup( $address )
                        );

                    // getting longitude
                    $lng = $lookup === 'coordinates'
                        ? $address[1]
                        : (
                            $params['geocoder'] === 'Google'
                                ? $result['lng']
                                : $api_class::parse_raw_result( $result, 'lng', true )
                        );

                    // getting latitude
                    $lat = $lookup === 'coordinates'
                        ? $address[0]
                        : (
                            $params['geocoder'] === 'Google'
                                ? $result['lat']
                                : $api_class::parse_raw_result( $result, 'lat', true )
                        );

                    // reformatting $address
                    $address = $lookup === 'coordinates'
                        ? (
                            $params['geocoder'] === 'Google'
                                ? (
                                    $result
                                        ? $api_class::parse_raw_result( $result, 'formatted_address' )
                                        : $addr
                                )
                                : $api_class::parse_raw_result( $result, 'full_location_name', true )
                        )
                        : $addr;

                    if ( false !== $result ) {

                        $relevance = $params['geocoder'] === 'Google'
                            ? 0.6 // to change if there's any data equals to Mapbox's relevance
                            : $result['features']['relevance'];

                        $is_valid_address = $relevance >= 0.5 ? true : false; // setting as valid address

                        // inserting to location grid meta
                        $geocoder = new Location_Grid_Geocoder();
                        $grid_row = $geocoder->get_grid_id_by_lnglat( $lng, $lat );

                        $location_meta_grid = [
                            'post_id'   => $params['id'],
                            'post_type' => $params['post_type'],
                            'grid_id'   => $grid_row['grid_id'],
                            'lng'       => $lng,
                            'lat'       => $lat,
                            'level'     => '',
                            'label'     => $address
                        ];

                        // validating and adding to location grid meta
                        Location_Grid_Meta::validate_location_grid_meta( $location_meta_grid );
                        Location_Grid_Meta::add_location_grid_meta( $params['id'], $location_meta_grid );
                    }

                    // prepping for response
                    array_push(
                        $results,
                        [
                            'address' => $address,
                            'lookup' => $lookup,
                            'result' => $result,
                            'lat' => $lat,
                            'lng' => $lng
                        ]
                    );
                }

                return json_encode(array(
                    'address' => $params['address'],
                    'has_valid_address' => $is_valid_address,
                    'results' => $results,
                    'addresses' => $addresses
                ));
            } else {
                // returning info : no geocoders apis available.
                return json_encode(array(
                    'message' => 'No geocoder APIs available.'
                ));
            }
        } catch (Exception $e) {
            return json_encode(array(
                'message' => 'Error Adding Location Grid Meta.',
                'error' => $e
            ));
        }
    }

    /**
     * Process the standard security checks on a public api request.
     *
     * @see /disciple-tools-theme/dt-network/network-endpoints.php for an example of public endpoints using the
     *      site to site link system.
     *
     * @param \WP_REST_Request $request
     *
     * @return array|\WP_Error
     */
    public function process_token( WP_REST_Request $request ) {

        $params = $request->get_params();

        // required token parameter challenge
        if ( ! isset( $params['transfer_token'] ) ) {
            return new WP_Error( __METHOD__, 'Missing parameters.' );
        }

        $valid_token = Site_Link_System::verify_transfer_token( $params['transfer_token'] );

        // required valid token challenge
        if ( ! $valid_token ) {
            dt_write_log( $valid_token );
            return new WP_Error( __METHOD__, 'Invalid transfer token' );
        }

        // required permission challenge (that this token comes from an approved site link)
        //        if ( ! current_user_can( 'sample_capability' ) ) {
        //            return new WP_Error( __METHOD__, 'Network report permission error.' );
        //        }

        // Add post id for site to site link
        $decrypted_key = Site_Link_System::decrypt_transfer_token( $params['transfer_token'] );
        $keys = Site_Link_System::get_site_keys();
        $params['site_post_id'] = $keys[$decrypted_key]['post_id'];

        return $params;
    }

    private function validate_lat_long( $address) {
        $split_address = explode( ",", $address );
        return preg_match( '/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?),[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $split_address[0].','.$split_address[1] ) === 1 ? 'coordinates' : 'address';
    }
}
