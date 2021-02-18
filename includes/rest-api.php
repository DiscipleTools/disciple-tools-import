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

        $public_namespace = 'dt-public/v1';
        $private_namespace = 'dt/v1';

        register_rest_route(
            $public_namespace, '/sample/public_endpoint', [
                [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'public_endpoint' ],
                ],
            ]
        );
        register_rest_route(
            $private_namespace, '/sample/private_endpoint', [
                [
                    'methods'  => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'private_endpoint' ],
                ],
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
        if ( !$this->has_permission() ){
            return new WP_Error( "private_endpoint", "Missing Permissions", [ 'status' => 400 ] );
        }

        // run your function here

        return true;
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
}
