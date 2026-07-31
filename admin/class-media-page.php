<?php
/**
 * Integración con la biblioteca de medios.
 *
 * @package WPAutoFirma
 */

namespace Erseco\WPAutoFirma;

use WP_Post;

/**
 * Añade la pantalla de firma y su acción en los PDF.
 */
final class Media_Page {

    /**
     * Sufijo de la pantalla registrada.
     *
     * @var string
     */
    private $hook_suffix = '';

    /**
     * Registra la página bajo el menú Medios.
     *
     * @return void
     */
    public function register_page() {
        $this->hook_suffix = (string) add_submenu_page(
            'upload.php',
            __( 'Firmar con AutoFirma', 'wp-autofirma' ),
            __( 'AutoFirma', 'wp-autofirma' ),
            'upload_files',
            'wp-autofirma-sign',
            array( $this, 'render_page' )
        );
    }

    /**
     * Añade la acción de firma a cada PDF.
     *
     * @param array<string, string> $actions Acciones existentes.
     * @param WP_Post               $post    Adjunto.
     * @return array<string, string>
     */
    public function add_media_action( $actions, $post ) {
        if ( 'application/pdf' !== $post->post_mime_type ) {
            return $actions;
        }

        $url = add_query_arg(
            array(
                'page'          => 'wp-autofirma-sign',
                'attachment_id' => $post->ID,
            ),
            admin_url( 'upload.php' )
        );

        $actions['wp_autofirma_sign'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url( $url ),
            esc_html__( 'Firmar con AutoFirma', 'wp-autofirma' )
        );

        return $actions;
    }

    /**
     * Carga recursos únicamente en la pantalla del plugin.
     *
     * @param string $hook_suffix Pantalla actual.
     * @return void
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( $this->hook_suffix !== $hook_suffix ) {
            return;
        }

        $attachment_id = $this->get_attachment_id();
        $autoscript_url = $this->get_autoscript_url();
        $dependencies   = array();

        if ( '' !== $autoscript_url ) {
            wp_enqueue_script(
                'wp-autofirma-autoscript',
                $autoscript_url,
                array(),
                '1.9',
                true
            );
            $dependencies[] = 'wp-autofirma-autoscript';
        }

        wp_enqueue_style(
            'wp-autofirma-admin',
            WP_AUTOFIRMA_URL . 'assets/css/admin.css',
            array(),
            WP_AUTOFIRMA_VERSION
        );
        wp_enqueue_script(
            'wp-autofirma-admin',
            WP_AUTOFIRMA_URL . 'build/admin.js',
            $dependencies,
            WP_AUTOFIRMA_VERSION,
            true
        );
        wp_localize_script(
            'wp-autofirma-admin',
            'wpAutoFirmaSettings',
            array(
                'attachmentId' => $attachment_id,
                'demoMode'     => $this->is_demo_mode(),
                'hasAutoScript' => '' !== $autoscript_url,
                'nonce'        => wp_create_nonce( 'wp_rest' ),
                'restUrl'      => esc_url_raw( rest_url( 'wp-autofirma/v1' ) ),
                'strings'      => array(
                    'cancelled'   => __( 'La operación se ha cancelado.', 'wp-autofirma' ),
                    'completed'   => __( 'El documento firmado se ha guardado como un adjunto nuevo.', 'wp-autofirma' ),
                    'loading'     => __( 'Cargando el documento…', 'wp-autofirma' ),
                    'missing'     => __( 'Configura la URL oficial de AutoScript antes de firmar.', 'wp-autofirma' ),
                    'saving'      => __( 'Guardando el documento firmado…', 'wp-autofirma' ),
                    'signing'     => __( 'Esperando a AutoFirma…', 'wp-autofirma' ),
                    'unknownError' => __( 'No se pudo completar la firma.', 'wp-autofirma' ),
                ),
            )
        );
    }

    /**
     * Muestra la pantalla de firma.
     *
     * @return void
     */
    public function render_page() {
        $attachment_id = $this->get_attachment_id();
        $attachment    = $attachment_id ? get_post( $attachment_id ) : null;
        ?>
        <div class="wrap wp-autofirma">
            <h1><?php esc_html_e( 'Firmar con AutoFirma', 'wp-autofirma' ); ?></h1>

            <?php if ( ! $attachment || 'application/pdf' !== $attachment->post_mime_type ) : ?>
                <div class="notice notice-info inline">
                    <p>
                        <?php esc_html_e( 'Selecciona un PDF en la biblioteca de medios y usa la acción «Firmar con AutoFirma».', 'wp-autofirma' ); ?>
                    </p>
                </div>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'upload.php?mode=list' ) ); ?>">
                        <?php esc_html_e( 'Abrir la biblioteca de medios', 'wp-autofirma' ); ?>
                    </a>
                </p>
            <?php else : ?>
                <?php $this->render_document( $attachment ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Muestra la tarjeta del documento.
     *
     * @param WP_Post $attachment Adjunto seleccionado.
     * @return void
     */
    private function render_document( WP_Post $attachment ) {
        ?>
        <div class="wp-autofirma__card">
            <h2><?php echo esc_html( get_the_title( $attachment ) ); ?></h2>
            <p>
                <?php esc_html_e( 'El original no se sobrescribirá. El resultado se guardará como un adjunto nuevo.', 'wp-autofirma' ); ?>
            </p>

            <?php if ( $this->is_demo_mode() ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e( 'Modo de demostración: se simula el resultado para probar el flujo de WordPress; no se crea una firma electrónica.', 'wp-autofirma' ); ?>
                    </p>
                </div>
            <?php elseif ( '' === $this->get_autoscript_url() ) : ?>
                <div class="notice notice-error inline">
                    <p>
                        <?php esc_html_e( 'AutoScript no está configurado. Consulta la documentación de instalación.', 'wp-autofirma' ); ?>
                    </p>
                </div>
            <?php endif; ?>

            <button
                type="button"
                class="button button-primary button-hero"
                id="wp-autofirma-sign"
                <?php disabled( ! $this->is_demo_mode() && '' === $this->get_autoscript_url() ); ?>
            >
                <?php esc_html_e( 'Firmar PDF', 'wp-autofirma' ); ?>
            </button>

            <p id="wp-autofirma-status" role="status" aria-live="polite"></p>
            <p id="wp-autofirma-result" hidden></p>
        </div>
        <?php
    }

    /**
     * Devuelve el adjunto solicitado o el creado por Playground.
     *
     * @return int
     */
    private function get_attachment_id() {
        $attachment_id = isset( $_GET['attachment_id'] )
            ? absint( wp_unslash( $_GET['attachment_id'] ) )
            : 0;

        if ( 0 === $attachment_id && $this->is_demo_mode() ) {
            $attachment_id = (int) get_option( 'wp_autofirma_demo_attachment_id' );
        }

        return $attachment_id;
    }

    /**
     * Obtiene la URL configurada para el fichero oficial.
     *
     * @return string
     */
    private function get_autoscript_url() {
        $url = defined( 'WP_AUTOFIRMA_AUTOSCRIPT_URL' )
            ? (string) WP_AUTOFIRMA_AUTOSCRIPT_URL
            : '';

        /**
         * Filtra la URL de AutoScript proporcionada por el sitio.
         *
         * @param string $url URL absoluta o vacía.
         */
        return esc_url_raw( (string) apply_filters( 'wp_autofirma_autoscript_url', $url ) );
    }

    /**
     * Indica si la instalación se usa como simulación de Playground.
     *
     * @return bool
     */
    private function is_demo_mode() {
        return defined( 'WP_AUTOFIRMA_DEMO_MODE' )
            && true === WP_AUTOFIRMA_DEMO_MODE;
    }
}
