<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
Plugin Name: Wappi
Plugin URI: https://wappi.pro/integrations/wordpress
Description: Whatsapp и Telegram уведомления о заказах WooCommerce через Wappi
Version: 1.0
Author: Wappi
Author URI: https://wappi.pro
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Подключение стилей
function wappi_enqueue_styles() {
    wp_register_style('wappi_styles', plugins_url('styles/style.css', __FILE__));
    wp_enqueue_style('wappi_styles');
}
add_action('admin_enqueue_scripts', 'wappi_enqueue_styles');

if (!is_callable('is_plugin_active')) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if (is_plugin_active('woocommerce/woocommerce.php')) {
	add_action('plugins_loaded', 'wappi_woocommerce::load');
}

register_activation_hook( __FILE__, 'wappi_woocommerce::activate' );

function wappi_send( $phone, $message ) 
{
    return (new wappi_woocommerce())->send( $phone, $message );
}

class wappi_woocommerce {

	public static function load() {
		$_this = new self();
		add_action( 'admin_menu', array($_this,'admin_menu'));
		add_action( 'woocommerce_new_order', array($_this,'status_changed'), 10, 1);
		add_action( 'woocommerce_order_status_changed', array($_this, 'status_changed'), 10, 3);
		return $_this;
	}

	public function admin_menu() {
		add_submenu_page('woocommerce', 'Whatsapp и Telegram уведомления через Wappi', 'Wappi', 'manage_woocommerce', 'wappi_settings', array(&$this,'options'));
	}

	public static function activate() {
		register_uninstall_hook( __FILE__, 'wappi_woocommerce::uninstall');
	}

	public static function uninstall() {
	    delete_option('wappi_apikey');
		delete_option('wappi_platform');
	    delete_option('wappi_sender');
	    delete_option('wappi_vendor_phone');
		delete_option('wappi_vendor_status1');
	    delete_option('wappi_vendor_msg1');
	    delete_option('wappi_vendor_status2');
		delete_option('wappi_vendor_msg2');
		delete_option('wappi_shopper_status1');
		delete_option('wappi_shopper_msg1');
		delete_option('wappi_shopper_status2');
		delete_option('wappi_shopper_msg2');
	}

	private function _get_parameters()
	{
		return array(
			'apikey' => sanitize_text_field(get_option('wappi_apikey')),
			'platform' => sanitize_text_field(get_option('wappi_platform')),
			'sender' => sanitize_text_field(get_option('wappi_sender')),
			'vendor_phone' => sanitize_text_field(get_option('wappi_vendor_phone')),
			'vendor_status1' => sanitize_text_field(get_option('wappi_vendor_status1', 'processing')),
			'vendor_msg1' => sanitize_text_field(get_option('wappi_vendor_msg1', 'Поступил заказ на сумму {SUM}. Номер заказа {NUM}')),
			'vendor_status2' => sanitize_text_field(get_option('wappi_vendor_status2', 'cancelled,failed')),
			'vendor_msg2' => sanitize_text_field(get_option('wappi_vendor_msg2', 'Статус заказа изменился на {NEW_STATUS}. Номер заказа {NUM}')),
			'shopper_status1' => sanitize_text_field(get_option('wappi_shopper_status1', 'processing')),
			'shopper_msg1' => sanitize_text_field(get_option('wappi_shopper_msg1', 'Ваш заказ на сумму {SUM} принят. Номер заказа {NUM}')),
			'shopper_status2' => sanitize_text_field(get_option('wappi_shopper_status2', 'completed')),
			'shopper_msg2' => sanitize_text_field(get_option('wappi_shopper_msg2', 'Статус вашего заказа изменился на {NEW_STATUS}. Номер заказа {NUM}')),
			'shopper_status3' => sanitize_text_field(get_option('wappi_shopper_status3', '')),
			'shopper_msg3' => sanitize_text_field(get_option('wappi_shopper_msg3', ''))
		);
	}

	public function options() {

		$p = $this->_get_parameters();
		$message = '';
		if (isset($_POST['wappi_settings_nonce_field']) && check_admin_referer('wappi_settings_nonce_action', 'wappi_settings_nonce_field')) {
			if ( isset($_POST['apikey']) ) {
				foreach( $p as $k => $vv ) {
					$v = '';
					if (isset($_POST[$k])) {
						if ( is_string($_POST[$k]) ) {
							$v = sanitize_text_field( $_POST[$k] );
						} else if ( is_array($_POST[$k]) ) {
							$v = sanitize_text_field( implode(',', array_map('sanitize_text_field', $_POST[$k])) );
						}
					}
					update_option('wappi_' . $k, $v);
				}

				if (!isset($_POST['test']) ) {
					wp_redirect(admin_url('admin.php?page=wappi_settings&status=updated'));
					return;
				}
				$p = $this->_get_parameters();
			}
		}

		/* pending Order received (unpaid)
		failed – Payment failed or was declined (unpaid). Note that this status may not show immediately and instead show as Pending until verified (i.e., PayPal)
		processing – Payment received and stock has been reduced – the order is awaiting fulfillment. All product orders require processing, except those that are Digital and Downloadable.
		completed – Order fulfilled and complete – requires no further action
		on-hold – Awaiting payment – stock is reduced, but you need to confirm payment
		cancelled – Cancelled by an admin or the customer – no further action required
		refunded – Refunded by an admin – no further action required */

		$msg = array(
			array( 'Оповещение продавцу о новом заказе', 'vendor_status1', 'vendor_msg1' ),
			array( 'Оповещение продавцу о смене статуса', 'vendor_status2', 'vendor_msg2' ),
			array( 'Оповещение покупателю о подтверждении заказа', 'shopper_status1', 'shopper_msg1' ),
			array( 'Оповещение покупателю о смене статуса', 'shopper_status2', 'shopper_msg2' ),
			array( 'Оповещение покупателю о смене статуса (дополнительно)', 'shopper_status3', 'shopper_msg3' )
		);
?>
		<html>
		<head>
			<style>
			li {
				font-size: 1.4em;
				padding-bottom: 5px;
			}
			</style>
		</head>
		<body>
			<div class="wrap woocommerce">

				<form method="post" id="mainform" action="<?php echo esc_attr(admin_url('admin.php?page=wappi_settings')) ?>">
					<?php wp_nonce_field('wappi_settings_nonce_action', 'wappi_settings_nonce_field'); ?>
					<h2>Whatsapp или Telegram оповещения о заказах через Wappi</h2>
					<img src="/../wp-content/plugins/wappi_send_message_woocommerce/images/logo.webp" alt="А где лого? (^._.^)~" style="max-width: 130px; margin-left: 10px;">
					<h3>Как пользоваться</h3>
					<ol>
						<li>Перейдите на <a href="https://wappi.pro/">wappi.pro</a> и зарегистрируйтесь</li>
						<li>Добавьте необходимый профиль и авторизуйте ваш номер, с которого будут отправляться оповещения</li>
						<li>В личном кабинете посмотрите токен API и ID профиля и возвращайтесь к настройкам</li>
						<li>Заполните необходимые поля и нажмите Сохранить</li>
						<li>Если возникли проблемы - воспользуйтесь <a href="https://wappi.pro/integrations/wordpress">инструкцией</a></li>
					</ol>

					<?php echo (isset($_GET['status']) && $_GET['status'] === 'updated' ) ? '<p style="color: green">Настройки обновлены</p>' : '' ?>
					<?php echo ( $last_e = get_option('wappi_last_error')) ? '<p>Последняя ошибка:<br/>'.esc_html( $last_e ).'</p>' : '' ?>
					<table class="form-table">
						<tr><th><label for="apikey">Токен API</label></th><td><input title="40-символьный ключ доступа к Wappi" 
						required name="apikey" pattern=".{40}" id="apikey" value="<?php echo esc_attr( $p['apikey'] ) ?>" size="43"/><br/>
						<small>Укажите ваш токен, найти можно <a href="https://wappi.pro/dashboard" target="_blank">здесь</a></small></td></tr>
						<tr><th><label>Статус приложения:</label></th><td><?php
								if ( $p['apikey']) {
									$this->_output_send_status($p, $message);
								} else
									echo '<span style="color: red">Нужно ввести API-ключ</span>';
								?></td></tr>
						<tr><th><label for="sender">ID профиля</label></th><td><input required name="sender" id="sender" value="<?php echo esc_attr( $p['sender'] ) ?>" /><br/>
						<small>Узнать свой ID можно в личном кабинете Wappi</td></tr>
						<tr><th><label for="vendor_phone">Номер продавца</label></th><td><input title="79008007060, 79008007060" required name="vendor_phone" pattern="^\d{11}(, \d{11})*$" id="vendor_phone" value="<?php echo esc_attr( $p['vendor_phone'] )  ?>" size="50"/>
						<br/><small>Например, 79991112233, можно указать несколько через запятую.</small></td></tr>
					</table>
					<input type="submit" class="button-secondary" name="test" value="Отправить тестовое сообщение продавцу" />
					<table class="form-table">
						<?php foreach( $msg as $m) { ?>
						<tr><th><label for="<?php echo esc_attr( $m[2] ) ?>"><?php echo esc_html( $m[0] ) ?></label></th><td>
						<?php 
						$status_text = sprintf('Статус: %s', $this->_init_checkboxes($m[1], $p[$m[1]]));
						$allowed_html = array(
							'label' => array(),
							'input' => array(
								'type' => array(),
								'name' => array(),
								'value' => array(),
								'checked' => array()
							),
						);
						echo esc_html('Статус: ') . wp_kses($status_text, $allowed_html);
						?><br/>
						Текст: <input name="<?php echo esc_attr( $m[2] ) ?>" id="<?php echo esc_attr( $m[2] ) ?>" value="<?php echo esc_attr( $p[ $m[2] ] )  ?>" size="80" />
						</td></tr>

							<?php }	?>
						
						<tr><th><label>Можно вставить переменные</label></th><td>
							<pre><code>{NUM} - номер заказа, {FNUM} - №номерзаказа, {SUM} - сумма заказа, {FSUM} - суммазаказа руб., {EMAIL} - эл.почта покупателя,
	{PHONE} - телефон покупателя, {FIRSTNAME} - имя покупателя, {LASTNAME} - фамилия покупателя,
	{CITY} - город доставки, {ADDRESS} - адрес доставки, {BLOGNAME} - название блога/магазина,
	{OLD_STATUS} - старый статус, {NEW_STATUS} - новый статус, {ITEMS} - список заказанных товаров
	{COMMENT} - комментарий покупателя к заказу
	<strong>{Произвольное поле}</strong> - вставка значения произвольного поля, которое вы или плагины добавили к заказу, 
	например, {post_tracking_number} или {ems_tracking_number} если установлен плагин 
	. Чувствительно к регистру символов!</code></pre></td></tr>
					</table>
					<input type="submit" class="button-primary" value="Сохранить">
				</form>
			</div>
		</body>
		</html>
<?php
	}

	private function _output_send_status ($p, $message) {
		if (isset($_POST['wappi_settings_nonce_field']) && check_admin_referer('wappi_settings_nonce_action', 'wappi_settings_nonce_field')) {
			if ( isset($_POST['test'])) {
				$data = array(
					'%s' => '1234.56',
					'%n' => '7890',
					'{SUM}' => '1234.56',
					'{FSUM}' => '1234.56 руб.',
					'{NUM}' => '7890',
					'{ITEMS}' => 'Название товара: 2x150=300',
					'{EMAIL}' => 'pokupatel@mail.ru',
					'{PHONE}' => '+79000000000',
					'{FIRSTNAME}' => 'Сергей',
					'{LASTNAME}' => 'Смирнов',
					'{CITY}' => 'г. Омск',
					'{ADDRESS}' => 'ул. Ленина, д. 1, кв. 2',
					'{BLOGNAME}' => get_bloginfo('name'),
					'{OLD_STATUS}' => 'Обработка',
					'{NEW_STATUS}' => 'Выполнен',
					'{COMMENT}' => 'Код домофона 123, после обеда',
					'{' => '*',
					'}' => '*',
				);
				$vendor_phone = sanitize_text_field( $_POST['vendor_phone'] );
				$message = str_replace( array_keys($data), array_values($data), sanitize_text_field( $_POST['vendor_msg1'] ) );
			}
			$data = $this->_get_profile_info();	
			if (sizeof($data) > 2) {
				$time_sub = new DateTime($data['payment_expired_at']);
				$time_curr = new DateTime;
				if ($time_sub < $time_curr)
					echo '<span style="color: red">Сообщение не отправлено</span>&nbsp;&nbsp;|&nbsp;&nbsp;Профиль не оплачен. <a href="https://wappi.pro/dashboard" target="_blank">Оплатите на сайте </a>';
				else {
					update_option('wappi_platform', $data['platform'] === 'tg'? 't' : '');
					$this->send($p['vendor_phone'], $message);
					$this->_save_info();
					$time_diff = $time_curr->diff($time_sub);
					$days_diff = $time_diff->days;
					$hours_diff = $time_diff->h;
					if (isset($_POST['test']))
						echo '<span style="color: green">Тестовое сообщение успешно отправлено продавцу';
					else 
						echo '<span style="color: green">Сообщение успешно отправлено';
					echo '</span>&nbsp;&nbsp;|&nbsp;&nbsp<span>' . esc_html('Профиль оплачен до: ' . $time_sub->format('Y-m-d') .', Подписка истекает через: ');
					$days_diff_last_num = $days_diff % 10;
					$hours_diff_last_num = $hours_diff % 10;

					if ($days_diff !== 0) {
						echo esc_html($days_diff);
						if ($days_diff_last_num > 4 || ($days_diff > 10 && $days_diff < 21))
							echo ' дней ';
						else if ($days_diff_last_num === 1 )
							echo ' день ';
						else
							echo ' дня ';
					}
					echo esc_html($hours_diff);
					if ($hours_diff_last_num > 4 || ($hours_diff > 10 && $hours_diff < 20) || $hours_diff_last_num === 0) 
						echo ' часов';	
					else if ($hours_diff_last_num === 1)
						echo ' час';
					else 
						echo ' часа';
					echo '</span>';	
				}		
			} else 
				echo '<span style="color: red">Ошибка отправки сообщения, скорее всего вы ввели неверный токен API или ID профиля</span>';
		}
	}

	public function status_changed($order_id, $old_status = 'pending', $new_status = 'pending')
	{
		$p = $this->_get_parameters();

		if ( $p['apikey'] ) {

			$o = new WC_Order($order_id);

			// SMS to sender
			if ( strpos($p['vendor_status1'], $new_status) !== false ) // new order
				$this->_send( $p['vendor_phone'], $p['vendor_msg1'], $o, $old_status, $new_status );
			else if ( strpos($p['vendor_status2'], $new_status) !== false ) // new status
				$this->_send( $p['vendor_phone'], $p['vendor_msg2'], $o, $old_status, $new_status );
			// SMS to shopper
			if ( strpos($p['shopper_status1'], $new_status) !== false ) // confirmed order
				$this->_send( $o->get_billing_phone(), $p['shopper_msg1'], $o, $old_status, $new_status );
			else if ( strpos($p['shopper_status2'], $new_status) !== false ) // new status
				$this->_send( $o->get_billing_phone(), $p['shopper_msg2'], $o, $old_status, $new_status );
			else if ( strpos($p['shopper_status3'], $new_status) !== false ) // new status alt
				$this->_send( $o->get_billing_phone(), $p['shopper_msg3'], $o, $old_status, $new_status );
		}
	}

	private function _init_checkboxes( $name, $selected )
	{  
		$selected = explode(',', $selected);
		$r = '';
		foreach( wc_get_order_statuses() as $k => $v ) {
			$k = substr($k, 3);
			$r .= '<label><input type="checkbox" name="'.esc_attr($name).'[]"'.(in_array($k, $selected, true) ? ' checked="checked"' : '').' value="'.esc_attr($k).'" /> '.esc_html($v).'</label>&nbsp;&nbsp;';
		}
		return $r;
	}

	/**
	 * Send message in whatsapp or telegram through the Wappi API.
	 *
	 * @param string $phone recipient phone number.
	 * @param string $message test message.
	 * @param WC_Order $order WooCommerce order object.
	 * @param string $old_status old order status.
	 * @param string $new_status new order status.
	 *
	 * @return void
	 */
	private function _send($phone, $message, $order, $old_status, $new_status)
	{
		$search = array(
			'{NUM}',
			'{FNUM}',
			'{SUM}',
			'{FSUM}',
			'{EMAIL}',
			'{PHONE}',
			'{FIRSTNAME}',
			'{LASTNAME}',
			'{CITY}',
			'{ADDRESS}',
			'{BLOGNAME}',
			'{OLD_STATUS}',
			'{NEW_STATUS}',
			'{COMMENT}'
		);

		$replace = array(
			$order->get_order_number(),
			'№' . $order->get_order_number(),
			$order->get_total(),
			wp_strip_all_tags($order->get_formatted_order_total(false, false)),
			$order->get_billing_email(),
			$order->get_billing_phone(),
			($s = $order->get_shipping_first_name()) ? $s : $order->get_billing_first_name(),
			($s = $order->get_shipping_last_name()) ? $s : $order->get_billing_last_name(),
			($s = $order->get_shipping_city()) ? $s : $order->get_billing_city(),
			trim(
				($s = $order->get_shipping_address_1()) ? $s : $order->get_billing_address_1())
				.' '
				.(($s = $order->get_shipping_address_2()) ? $s : $order->get_billing_address_2()
			),
			get_option('blogname'),
			wc_get_order_status_name($old_status),
			wc_get_order_status_name($new_status),
			$order->get_customer_note()
		);

		if (strpos($message, '{ITEMS}') !== false) {
			$items = $order->get_items();
			$items_str = '';
			foreach ($items as $i) {
				/* @var $i WC_Order_Item_Product */
				$name = $i['name'];
				if ($_p = $i->get_product() && $sku = $_p->get_sku()) {
					$name = $sku . ' ' . $name;
				}
				$items_str .= "\n" . $name . ': ' . $i['qty'] . 'x' . $order->get_item_total($i) . '=' . $order->get_line_total($i);
			}
			$sh = $order->get_shipping_methods();
			foreach ($sh as $i) {
				$items_str .= "\n" . __('Shipping', 'woocommerce') . ': ' . $i['name'] . '=' . $i['cost'];
			}
			$items_str .= "\n";
			$search[] = '{ITEMS}';
			$replace[] = wp_strip_all_tags($items_str);
		}

		if ($meta = get_post_meta($order->get_id())) {
			foreach ($meta as $k => $v) {
				$search[] = '{' . $k . '}';
				$replace[] = $v[0];
			}
		}

		foreach ($replace as $k => $v) {
			$replace[$k] = html_entity_decode($v);
		}
		$message = str_replace($search, $replace, $message);
		$message = preg_replace('/\s?\{[^}]+\}/', '', $message);
		$message = trim($message);
		$message = mb_substr($message, 0, 670);
		$this->send($phone, $message);
	}

	/**
	 * Send message in whatsapp or telegram through the Wappi API.
	 *
	 * @param string $phone recipient's phone number.
	 * @param string $message text message.
	 *
	 * @return void
	 */
	public function send($phone, $message) {
		$profile_id = get_option('wappi_sender');
		$this->_post($phone, $message, $profile_id);
	}

	/**
	 * Getting profile information through the Wappi API.
	 *
	 * @return array profile info.
	 */
	private function _get_profile_info() {
		$profile_id = sanitize_text_field(get_option('wappi_sender'));
		$apikey = sanitize_text_field(get_option('wappi_apikey'));
		$url = esc_url_raw('https://wappi.pro/api/sync/get/status?profile_id=' . urlencode($profile_id));

		$args = array(
			'method' => 'GET',
			'headers' => array(
				'Accept' => 'application/json',
				'Authorization' => $apikey,
				'Content-Type' => 'application/json',
			),
		);

		$response = wp_remote_get($url, $args);
		if (is_wp_error($response)) {
			echo '<span style="color: red">' . esc_html('Ошибка HTTP: ' . $response->get_error_message()) . '</span>';
			return "";
		} else {
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			// Проверка на ошибки JSON
			if (!(json_last_error() === JSON_ERROR_NONE)) {
				echo '<span style="color: red">' . esc_html('Ошибка JSON: ' . json_last_error_msg()) . '</span>';
			}
		}
		return $data;		
	}

	/**
	 * Save info (profile ID) about user from download statistic in Wappi API.
	 *
	 * @return void
	 */
	private function _save_info() {
		$apikey = sanitize_text_field(get_option('wappi_apikey'));
		$profile_id = sanitize_text_field(get_option('wappi_sender'));
		$message_json = json_encode(array(
			'url' => esc_url_raw($_SERVER['HTTP_REFERER']),
			'module' => 'wp',
			'profile_uuid' => $profile_id,
		));

		$url = esc_url_raw('https://dev.wappi.pro/tapi/addInstall?profile_id=' . urlencode($profile_id));

		$args = array(
			'body' => $message_json,
			'headers' => array(
				'Accept' => 'application/json',
				'Authorization' => $apikey,
				'Content-Type' => 'application/json',
			),
			'method' => 'POST',
			'data_format' => 'body',
		);

		$response = wp_remote_post($url, $args);

		if (is_wp_error($response)) {
			echo '<span style="color: red">' . esc_html('Error: ' . $response->get_error_message()) . '</span>';
		} else if (json_last_error() !== JSON_ERROR_NONE) {
			echo '<span style="color: red">' . esc_html('Ошибка JSON: ' . json_last_error_msg()) . '</span>';
		}
	}

	/**
	 * Send message in whatsapp or telegram through the Wappi API.
	 *
	 * @param string $phone recipient's phone number.
	 * @param string $message text message.
	 * @param string $profile_id profile ID in Wappi service.
	 */
	private function _post($phone, $message, $profile_id) 
	{
		$platform = sanitize_text_field(get_option('wappi_platform'));
		$apikey = sanitize_text_field(get_option('wappi_apikey'));
		$phone_array = explode(', ', sanitize_text_field($phone));
		
		foreach ($phone_array as $phone) {
			$message_json = json_encode(array(
				'recipient' => sanitize_text_field($phone),
				'body' => sanitize_text_field($message)
			));
		
			$url = esc_url_raw('https://wappi.pro/' . $platform . 'api/sync/message/send?profile_id=' . urlencode($profile_id));
		
			$args = array(
				'body' => $message_json,
				'headers' => array(
					'Accept' => 'application/json',
					'Authorization' => $apikey,
					'Content-Type' => 'application/json',
				),
				'method' => 'POST',
				'data_format' => 'body',
			);
		
			$response = wp_remote_post($url, $args);
		
			if (is_wp_error($response)) {
				echo '<span style="color: red">' . esc_html('Error: ' . $response->get_error_message()) . '</span>';
				break;
			} else if (json_last_error() !== JSON_ERROR_NONE) {
				echo '<span style="color: red">' . esc_html('Ошибка JSON: ' . json_last_error_msg()) . '</span>';
				break;
			}
		}
	}
}
