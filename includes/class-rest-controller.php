<?php
/**
 * API REST del plugin.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Expone lectura y guardado con comprobaciones de capacidad.
 */
final class Rest_Controller {

    /**
     * Servicio de documentos.
     *
     * @var Document_Service
     */
    private $documents;

    /**
     * Repositorio de firmas.
     *
     * @var Signature_Repository
     */
    private $signatures;

    /**
     * Inicializa el controlador.
     *
     * @param Document_Service     $documents  Servicio de documentos.
     * @param Signature_Repository $signatures Repositorio de firmas.
     */
    public function __construct( Document_Service $documents, Signature_Repository $signatures ) {
        $this->documents  = $documents;
        $this->signatures = $signatures;
    }

    /**
     * Registra las rutas REST.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            'wp-autofirma/v1',
            '/documents/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_document' ),
                'permission_callback' => array( $this, 'can_read_document' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        register_rest_route(
            'wp-autofirma/v1',
            '/signatures',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_signature' ),
                'permission_callback' => array( $this, 'can_upload_signature' ),
            )
        );
    }

    /**
     * Comprueba el acceso al documento.
     *
     * @param WP_REST_Request $request Petición REST.
     * @return bool
     */
    public function can_read_document( WP_REST_Request $request ) {
        return current_user_can( 'read_post', (int) $request['id'] );
    }

    /**
     * Comprueba la capacidad para guardar un adjunto.
     *
     * @param WP_REST_Request $request Petición REST.
     * @return bool
     */
    public function can_upload_signature( WP_REST_Request $request ) {
        $original_id = (int) $request->get_param( 'originalAttachmentId' );

        return current_user_can( 'upload_files' )
            && current_user_can( 'read_post', $original_id );
    }

    /**
     * Obtiene el documento solicitado.
     *
     * @param WP_REST_Request $request Petición REST.
     * @return WP_REST_Response|WP_Error
     */
    public function get_document( WP_REST_Request $request ) {
        try {
            return new WP_REST_Response(
                $this->documents->get_document( (int) $request['id'] )
            );
        } catch ( Exception $exception ) {
            return new WP_Error(
                'wp_autofirma_document_error',
                $exception->getMessage(),
                array( 'status' => 400 )
            );
        }
    }

    /**
     * Guarda el resultado de una firma.
     *
     * @param WP_REST_Request $request Petición REST.
     * @return WP_REST_Response|WP_Error
     */
    public function create_signature( WP_REST_Request $request ) {
        $params      = (array) $request->get_json_params();
        $original_id = isset( $params['originalAttachmentId'] )
            ? absint( $params['originalAttachmentId'] )
            : 0;
        $filename    = isset( $params['filename'] )
            ? sanitize_file_name( $params['filename'] )
            : 'documento.pdf';
        $signature   = isset( $params['signature'] )
            ? (string) $params['signature']
            : '';

        if ( 0 === $original_id || '' === $signature ) {
            return new WP_Error(
                'wp_autofirma_invalid_request',
                __( 'Faltan datos obligatorios de la firma.', 'wp-autofirma' ),
                array( 'status' => 400 )
            );
        }

        try {
            $attachment_id = $this->signatures->save(
                $original_id,
                $filename,
                $signature
            );

            return new WP_REST_Response(
                array(
                    'attachmentId' => $attachment_id,
                    'editUrl'      => get_edit_post_link( $attachment_id, 'raw' ),
                    'url'          => wp_get_attachment_url( $attachment_id ),
                ),
                201
            );
        } catch ( Exception $exception ) {
            return new WP_Error(
                'wp_autofirma_signature_error',
                $exception->getMessage(),
                array( 'status' => 400 )
            );
        }
    }
}
