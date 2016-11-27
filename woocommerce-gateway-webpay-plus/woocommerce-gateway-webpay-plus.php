<?php

/**
 * Webpay Plus for WooCommerce
 *
 * Plugin Name:       Webpay Plus for WooCommerce
 * Plugin URI:        http://burbuja.cl/proyectos/woocommerce/
 * Description:       A Webpay Plus de Transbank Payment Gateway for WooCommerce. Sponsored by <a href="http://codingpandas.cl">Coding_Pandas</a>.
 * Version:           0.1
 * Author:            Rodrigo Sepúlveda Heerwagen
 * Author URI:        http://lox.cl/
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       tbkwebpayplus
 * Domain Path:       /lang
 */

if ( ! defined( 'WPINC' ) )
	die;

if ( ! defined( 'TBKWEBPAYPLUS__PLUGIN_DIR' ) )
	define( 'TBKWEBPAYPLUS__PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! defined( 'TBKWEBPAYPLUS__PLUGIN_URL' ) )
	define( 'TBKWEBPAYPLUS__PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );


/* Check if WooCommerce is active */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :

function init_tbk_webpay_plus_class() {
	class WC_Gateway_TBK_Webpay_Plus extends WC_Payment_Gateway {
		public function __construct() {
			$this->id = 'tbk_webpay_plus';
			$this->icon = ''; //Debe ir la ruta al ícono.
			$this->has_fields = true; // Define si uno quiere o no que los parámetros de pago se muestren en el pago.
			$this->method_title = __( 'Webpay Plus de Transbank', 'tbkwebpayplus' );
			$this->method_description = __( 'Add Webpay Plus de Transbank, a Chilean payment gateway.', 'tbkwebpayplus' );

			// Load the form fields
			$this->init_form_fields();

			// Load the settings
			$this->init_settings();

			// Get setting values
			$this->enabled = $this->get_option( 'enabled' );
			$this->title = $this->get_option( 'title' );
			$this->kcc_url = $this->get_option( 'kcc_url' );
			$this->kcc_path = $this->get_option( 'kcc_path' );

			// Hooks
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_api_' . $this->id, array( $this, 'return_handler' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		}

		public function init_form_fields() {
			$this->default_kcc_url = function_exists('home_url') ? home_url('/') . 'cgi-bin' : '';
			$this->default_kcc_path =  function_exists('get_home_path') ? get_home_path() . 'cgi-bin' : '';
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce' ),
					'label' => __( 'Enable Webpay Plus de Transbank', 'tbkwebpayplus' ),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Title', 'tbkwebpayplus' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' => __( 'Webpay Plus de Transbank', 'tbkwebpayplus' ),
					'desc_tip'      => true
				),
				'kcc_url' => array(
					'title' => __( 'KCC URL', 'tbkwebpayplus' ),
					'type' => 'text',
					'description' => __( 'URL where KCC provided by Transbank has been installed in this server.', 'tbkwebpayplus' ),
					'default' => $this->default_kcc_url,
					'desc_tip' => true
				),
				'kcc_path' => array(
					'title' => __( 'KCC Path', 'tbkwebpayplus' ),
					'type' => 'text',
					'description' => __( 'Path where KCC provided by Transbank has been installed in this server.', 'tbkwebpayplus' ),
					'default' => $this->default_kcc_path,
					'desc_tip' => true
				),
				'return_policy' => array(
					'title' => __( 'Return Policy', 'tbkwebpayplus' ),
					'type' => 'select',
					'default' => '0',
					'options' => $this->pages_array(),
					'desc_tip' => false
				)
			);
		}

		public function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
		}

		public function payment_fields() {
			echo '<p>'. __( 'Pay with a credit or debit card securely through Webpay Plus de Transbank.', 'tbkwebpayplus' ) . '</p>' . "\n";
		}

		public function receipt_page( $order_id ) {
			$order = wc_get_order( $order_id );

			$tbk = array(
				'tipo_transaccion' => 'TR_NORMAL',
				'monto' => $order->get_total() . '00',
				'orden_compra' => $order->id,
				'id_sesion' => date( 'Ymdhis' ),
				'url_fracaso' => $order->get_cancel_order_url(),
				'url_exito' => $this->get_return_url( $order ),
				//'monto_cuota' => '',
				//'numero_cuota' => ''
			);

			$log_file = fopen( TBKWEBPAYPLUS__PLUGIN_DIR . '/tbk_files/TBK_' . $tbk['id_sesion'] . '.log', 'w+' );
			fwrite ( $log_file, $tbk['monto'] . ';' . $tbk['orden_compra'] );
			fclose( $log_file );

			echo wpautop( wptexturize( wp_kses_post( __( 'Thank you for your order, please click the button below to pay with a credit or debit card using Webpay Plus de Transbank.', 'tbkwebpayplus' ) ) ) );
			echo "\t\t\t\t\t" . '<form action="' . $this->kcc_url . '/tbk_bp_pago.cgi" method="post">' . "\n";

			foreach ( $tbk as $key => $value ) {
				echo "\t\t\t\t\t\t" . '<input type="hidden" name="TBK_' . strtoupper( $key ) . '" value="' . $value . '" />' . "\n";
			}

			echo "\t\t\t\t\t\t" . '<button class="button alt" id="tbkwebpayplus-payment-button" >' . __( 'Pay Now', 'woocommerce' ) . '</button> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>' . "\n";
			echo "\t\t\t\t\t" . '</form>';
		}

		public function return_handler() {
			global $woocommerce;
			header( 'HTTP/1.1 200 OK' );
		
			/* Se llama desde: http://ejemplo.tld/wordpress/wc-api/tbk_webpay_plus/ o http://ejemplo.tld/wordpress/?wc-api=tbk_webpay_plus */
		
			if ( ! isset( $_POST['TBK_ORDEN_COMPRA'] ) || ! is_numeric( $_POST['TBK_ORDEN_COMPRA'] ) || ! isset( $_POST['TBK_ID_SESION'] ) || ! is_numeric( $_POST['TBK_ID_SESION'] ) || ! isset( $_POST['TBK_RESPUESTA'] ) )
				exit( 'RECHAZADO' );
		
			$order = new WC_Order( $_POST['TBK_ORDEN_COMPRA'] );
			//$order = wc_get_order( $_POST['TBK_ORDEN_COMPRA'] );
			$post = $order->post;
		
			if ( $post->post_type != 'shop_order' || $order->post_status == 'wc-processing' ) // Revisar
				exit( 'RECHAZADO' );
		
			$log_file = TBKWEBPAYPLUS__PLUGIN_DIR . '/tbk_files/TBK_' . $_POST['TBK_ID_SESION'] . '.log'; // Definir la ubicación del archivo de registro
			$cache_file = TBKWEBPAYPLUS__PLUGIN_DIR . '/tbk_files/TBK_' . $_POST['TBK_ID_SESION'] . '.txt'; // Definir la ubicación del archivo temporal
		
			switch ( $_POST['TBK_RESPUESTA'] ) {
				case 0:
					/* Verificar orden de compra única y si vienen o no todos los datos */
					//
					/* Verificar el MAC: Revisar si el archivo de registro existe */
					if ( file_exists( $log_file ) ) {
						$log = fopen( $log_file, 'r' );
						$log_array = explode(';', fgets( $log ) );
						fclose( $log );
					} else {
						$order->update_status( 'failed', 'Archivo de registro no se encuentra' );
						exit( 'RECHAZADO' );
					}
					/* Verificar el MAC: Revisar si el archivo cumple con los requisitos */
					if ( isset( $log_array ) && count( $log_array ) >= 1 ) {
						$log_amount = $log_array[0];
						$log_order_id = $log_array[1];
		
						$cache = fopen( $cache_file, 'w+' );
						foreach ( $_POST as $key => $value ) {
							fwrite( $cache, "$key=$value&");
						}
						fclose( $cache );
		
						exec( $this->kcc_path . '/tbk_check_mac.cgi ' . $cache_file, $result );
					} else {
						$order->update_status( 'failed', 'Archivo de registro no cumple con los requisitos' );
						exit( 'RECHAZADO' );
					}
					/* Verificar el MAC: Revisar el resultado de la ejecución del programa */
					if ( ! isset( $result[0] ) || ! $result[0] == 'CORRECTO' ) {
						$order->update_status( 'failed', 'MAC rechazado' );
						exit( 'RECHAZADO' );
					}
					/* Verificar la orden de compra */
					if ( $_POST['TBK_ORDEN_COMPRA'] != $log_order_id || $_POST['TBK_ORDEN_COMPRA'] != $order->id ) {
						$order->update_status( 'failed', 'Orden de compra no coincide' );
						exit( 'RECHAZADO' );
					}
					/* Verificar el monto */
					if ( $_POST['TBK_MONTO'] != $log_amount || $_POST['TBK_MONTO'] != $order->get_total() . '00' ) {
						$order->update_status( 'failed', 'Monto no coincide' );
						exit( 'RECHAZADO' );
					}
					/* Comprobación exitosa */
					$order->add_order_note( 'Transacción aprobada' );
					$order->payment_complete();
					exit( 'ACEPTADO' );
				break;
				case -1:
					$order->update_status( 'failed', 'Rechazo de transacción' );
				break;
				case -2:
					$order->update_status( 'failed', 'Transacción debe reintentarse' );
				break;
				case -3:
					$order->update_status( 'failed', 'Error en transacción' );
				break;
				case -4:
					$order->update_status( 'failed', 'Rechazo de transacción' );
				break;
				case -5:
					$order->update_status( 'failed', 'Rechazo por error de tasa' );
				break;
				case -6:
					$order->update_status( 'failed', 'Excede cupo máximo mensual' );
				break;
				case -7:
					$order->update_status( 'failed', 'Excede límite diario por transacción' );
				break;
				case -8:
					$order->update_status( 'failed', 'Rubro no autorizado' );
				break;
				default:
					$order->update_status( 'failed', 'Error desconocido' );
			}
			exit( 'ACEPTADO' );
		}

		public function thankyou_page( $order_id ) {
			if ( ! isset( $_POST['TBK_ORDEN_COMPRA'] ) || ! is_numeric( $_POST['TBK_ORDEN_COMPRA'] ) || ! isset( $_POST['TBK_ID_SESION'] ) || ! is_numeric( $_POST['TBK_ID_SESION'] ) )
			return;

			$cache_file = TBKWEBPAYPLUS__PLUGIN_DIR . '/tbk_files/TBK_' . $_POST['TBK_ID_SESION'] . '.txt'; // Definir la ubicación del archivo temporal

			if ( ! is_file( $cache_file ) )
			return;

			$cache = fopen( $cache_file, 'r' );
			$cache_string = fgets( $cache );
			fclose( $cache );

			$details = array_filter( explode( '&', $cache_string ) );

			foreach ( $details as $detail ) {
				list( $key, $value ) = explode( '=', $detail );
				$key = str_replace( 'tbk_', '', strtolower( $key ) );
				$tbk[$key] = $value;  
			}

			foreach ( $tbk as $key => $value ) {
				echo '<p>' . $key . ' = ' . $value . '</p>';
			}

			$content = '<header><h2>Detalles del pago</h2></header>';
			$content.= '<table class="shop_table ' . $this->id . '_payment_data">';
			$content.= '<tr>';
			$content.= '<th>Respuesta de la transacción:</th>';
			$content.= '<td>' . $TBK['respuesta'] . '</td>';
			$content.= '</tr>';
			$content.= '<tr>';
			$content.= '<th>Número de pedido:</th>';
			$content.= '<td>' . $TBK['orden_compra'] . '</td>';
			$content.= '</tr>';
			$content.= '<tr>';
			$content.= '<th>Código de autorización:</th>';
			$content.= '<td>' . $TBK['codigo_autorizacion'] . '</td>';
			$content.= '</tr>';
			$content.= '<tr>';
			$content.= '<th>Fecha de transacción:</th>';
			$content.= '<td>' . $TBK['fecha_transaccion'] . '</td>';
			$content.= '</tr>';
			$content.= '<tr>';
			$content.= '<th>Hora de transacción:</th>';
			$content.= '<td>' . $TBK['hora_transaccion'] . '</td>';
			$content.= '</tr>';
			$content.= '<tr>';
			$content.= '<th>Tarjeta de crédito:</th>';
			$content.= '<td>**** **** **** ' . $TBK['final_numero_tarjeta'] . '</td>';
			$content.= '</tr>';
			$content.= '<tr>';
			$content.= '<th>Tipo de pago</th>';
			$content.= '<td>' . $TBK['tipo_pago'] . '</td>';
			$content.= '</tr>';
			$content.= '<tr>';
			$content.= '<th>Monto de la compra:</th>';
			$content.= '<td>' . $TBK['monto'] / 100 . '</td>';
			$content.= '</tr>';
			$content.= '<tr>';
			$content.= '<th>Número de cuotas:</th>';
			$content.= '<td>' . $TBK['numero_cuotas'] . '</td>';
			$content.= '</tr>';
			$content.= '</table>';

			echo $content;
		}

		private function pages_array() {
			$pages = get_pages();

			$array = array();
			$array[0] = __( '&mdash; Select &mdash;' );

			foreach ( $pages as $page ) {
				$array[$page->ID] = $page->post_title;
			}

			return $array;
		}
	}
}
add_action( 'plugins_loaded', 'init_tbk_webpay_plus_class' );

function add_tbk_webpay_plus_class( $methods ) {
	$methods[] = 'WC_Gateway_TBK_Webpay_Plus';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_tbk_webpay_plus_class' );

if ( isset( $_POST['TBK_TOKEN'] ) && isset( $_POST['TBK_ID_SESION'] ) && isset( $_POST['TBK_ORDEN_COMPRA'] ) ) :

function tbk_webpay_plus_order_cancelled_notice() {
	$message = '<h2>Transacción fracasada</h2>';
	$message.= '<p>Número de pedido: ' . $_POST['TBK_ORDEN_COMPRA'] . '.</p>';
	$message.= '<p>Las posibles causas de este rechazo son:</p>';
	$message.= '<ul>';
	$message.= '<li>Error en el ingreso de los datos de su tarjeta de crédito o débito (fecha y/o código de seguridad).</li>';
	$message.= '<li>Su tarjeta de crédito o débito no cuenta con el cupo necesario para pagar la compra.</li>';
	$message.= '<li>Tarjeta aún no habilitada en el sistema financiero.</li>';
	$message.= '</ul>';

	return $message;
}
add_filter( 'woocommerce_order_cancelled_notice', 'tbk_webpay_plus_order_cancelled_notice' );

endif;

endif;
