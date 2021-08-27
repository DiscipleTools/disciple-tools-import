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
     * Public endpoints are for integrating with systems outside the Disciple Tools site. Connection to other sites
     * can be done using the Site_Link_System class found in /disciple-tools-theme/dt-core/admin/site-link-post-type.php
     *
     * Private endpoints can use the Wordpress nonce system and user login to verify connections. Private connections
     * are used for extending the disciple tools system and should be used for all plugin extensions, except those
     * integrating to outside systems.
     */
    public function add_api_routes() {

        // NOTE : To confirm with Trip
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
                    'callback'              => [ $this, 'private_endpoint'],
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

        $geocoderFunctions = [
        ];

        try {
            // check if geocoder apis exist
            if ( DT_Mapbox_API::get_key() || Disciple_Tools_Google_Geocode_API::get_key() ) {

                // validating address params if coordinates or just address
                $API_class = $params['geocoder'] === 'Google'
                    ? 'Disciple_Tools_Google_Geocode_API'
                    : 'DT_Mapbox_API';

                $address = $params['lookup'] === 'coordinates'
                    ? explode(",", preg_replace('/\s/', '', $params['address']))
                    : $params['address'];

                $result = $params['lookup'] === 'coordinates'
                    ? (
                        $params['geocoder'] === 'Google'
                            ? $API_class::query_google_api_reverse($address[0] . ',' . $address[1])
                            : $API_class::reverse_lookup($address[1], $address[0])
                    ) : (
                        $params['geocoder'] === 'Google'
                            ? $API_class::query_google_api($address, 'core')
                            : $API_class::forward_lookup($address)
                    );

                $lng = $params['lookup'] === 'coordinates'
                    ? $address[1]
                    : (
                        $params['geocoder'] === 'Google'
                            ? $result['lng']
                            : $API_class::parse_raw_result($result, 'lng', true)
                    );
                
                $lat = $params['lookup'] === 'coordinates'
                    ? $address[0]
                    : (
                        $params['geocoder'] === 'Google'
                            ? $result['lat']
                            : $API_class::parse_raw_result( $result, 'lat', true )
                    );
                
                // reformatting $address
                $address = $params['lookup'] === 'coordinates'
                    ? (
                        $params['geocoder'] === 'Google'
                            ? $API_class::parse_raw_result($result, 'formatted_address')
                            : $API_class::parse_raw_result($result, 'full_location_name', true)
                    )
                    : $params['address'];

                // if (DT_Mapbox_API::get_key()) { // if mapbox api
                    // $result = DT_Mapbox_API::forward_lookup( $params['address'] );

                    // setting coordinates
                    // $lng = DT_Mapbox_API::parse_raw_result( $result, 'lng', true );
                    // $lat = DT_Mapbox_API::parse_raw_result( $result, 'lat', true );
                // } else { // if google map api
                    // $result = Disciple_Tools_Google_Geocode_API::query_google_api($params['address'], 'coordinates_only');

                    // $lng = $result['lng'];
                    // $lat = $result['lat'];
                // }

                if ( false !== $result ) {
                    // setting location grid meta
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

                return json_encode(array(
                    'address' => $address,
                    'lookup' => $params['lookup'],
                    'result' => $result,
                    'lng' => $lng,
                    'lat' => $lat
                ));

                // returning value
                /*
                return json_encode(array(
                    'message' => 'Success Adding Location Grid Meta.',
                    'params' => $params,
                    'result' => $result,
                ));
                */
            } else {
                // returning info : no geocoders apis available.
                return json_encode(array(
                    'message' => 'No geocoder APIs available.'
                ));
            }
        } catch(Exception $e) {
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

    private function validateLatLong($address) {
        $split_address = explode(" ", $address);
        return preg_match('/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?),[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $split_address[0].','.$split_address[1]);
    }
}
