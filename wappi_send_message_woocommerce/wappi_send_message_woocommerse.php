<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
Plugin Name: Wappi
Plugin URI: https://wappi.pro/integrations/wordpress
Description: Whatsapp и Telegram уведомления о заказах WooCommerce через Wappi
Version: 1.0.8
Author: Wappi
Author URI: https://wappi.pro
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce
*/

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
		add_action( 'woocommerce_checkout_order_processed', array($_this,'status_changed'), 10, 1);
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
			'vendor_msg1' => wp_kses_post(get_option('wappi_vendor_msg1', 'Поступил заказ на сумму {SUM}. Номер заказа {NUM}')),
			'vendor_status2' => sanitize_text_field(get_option('wappi_vendor_status2', 'cancelled,failed')),
			'vendor_msg2' => wp_kses_post(get_option('wappi_vendor_msg2', 'Статус заказа изменился на {NEW_STATUS}. Номер заказа {NUM}')),
			'shopper_status1' => sanitize_text_field(get_option('wappi_shopper_status1', 'processing')),
			'shopper_msg1' => wp_kses_post(get_option('wappi_shopper_msg1', 'Ваш заказ на сумму {SUM} принят. Номер заказа {NUM}')),
			'shopper_status2' => sanitize_text_field(get_option('wappi_shopper_status2', 'completed')),
			'shopper_msg2' => wp_kses_post(get_option('wappi_shopper_msg2', 'Статус вашего заказа изменился на {NEW_STATUS}. Номер заказа {NUM}')),
			'shopper_status3' => sanitize_text_field(get_option('wappi_shopper_status3', '')),
			'shopper_msg3' => wp_kses_post(get_option('wappi_shopper_msg3', ''))
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
						if (is_string($_POST[$k])) {
							$v = wp_kses_post($_POST[$k]);
						} else if (is_array($_POST[$k])) {
							$v = implode(',', array_map('wp_kses_post', $_POST[$k]));
						}
					}
					update_option('wappi_' . $k, $v);
				}
				$p = $this->_get_parameters();

				if (!isset($_POST['test']) ) {
					wp_redirect(admin_url('admin.php?page=wappi_settings&status=updated'));
					return;
				}
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
		<div class="wrap woocommerce">

			<form method="post" id="mainform" action="<?php echo esc_attr(admin_url('admin.php?page=wappi_settings')) ?>">
				<?php wp_nonce_field('wappi_settings_nonce_action', 'wappi_settings_nonce_field'); ?>
				<h2>Whatsapp или Telegram оповещения о заказах через Wappi</h2>
				<img src="/../wp-content/plugins/wappi/images/logo.webp" alt="А где лого? (^._.^)~" style="max-width: 130px; margin-left: 10px;">
				<h3>Как пользоваться</h3>
				<ol>
					<li>Перейдите на <a href="https://wappi.pro/">wappi.pro</a> и зарегистрируйтесь</li>
					<li>Добавьте необходимый профиль и авторизуйте ваш номер, с которого будут отправляться оповещения</li>
					<li>В личном кабинете посмотрите токен API и ID профиля и возвращайтесь к настройкам</li>
					<li>Заполните необходимые поля и нажмите Сохранить</li>
					<li>Вы можете использовать каскадную рассылку Whatsapp/Telegram/SMS.<br>
						Для этого авторизуйте в Wappi.pro нужные профили, затем добавьте Каскад.<br>
						ID каскада вставьте на этой странице.</li>
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
					<tr><th><label for="sender">ID профиля/каскада</label></th><td><input required name="sender" id="sender" value="<?php echo esc_attr( $p['sender'] ) ?>" /><br/>
					<small>Узнать свой ID профиля или ID каскада можно в личном кабинете Wappi</td></tr>
					<tr><th><label for="vendor_phone">Номер продавца</label></th><td><input title="79008007060, 3759008007060" required name="vendor_phone" pattern="^\d{11-13}(, \d{11-13})*$" id="vendor_phone" value="<?php echo esc_attr( $p['vendor_phone'] )  ?>" size="50"/>
					<br/><small>Например, 79991112233, можно указать несколько через запятую.</small></td></tr>
				</table>
				<input type="submit" class="button-secondary" name="test" value="Отправить тестовое сообщение продавцу" />
				<table class="form-table">
					<?php foreach( $msg as $m) { ?>
					<tr>
						<th><label for="<?php echo esc_attr( $m[2] ) ?>"><?php echo esc_html( $m[0] ) ?></label></th>
						<td>
							<?php 
							// Вывод статусов
							$status_text = sprintf('Статус:&nbsp;&nbsp;%s', $this->_init_checkboxes($m[1], $p[$m[1]]));
							$allowed_html = array(
								'label' => array(),
								'input' => array(
									'type' => array(),
									'name' => array(),
									'value' => array(),
									'checked' => array()
								),
							);
							echo wp_kses($status_text, $allowed_html);
							?>
							<div style="display: flex; align-items: center;">
								<label for="<?php echo esc_attr( $m[2] ) ?>" style="margin-right: 10px;">Текст:</label>
								<textarea name="<?php echo esc_attr( $m[2] ) ?>" id="<?php echo esc_attr( $m[2] ) ?>" rows="5" cols="80"><?php echo esc_textarea( $p[ $m[2] ] ); ?></textarea>
							</div>

						</td>
					</tr>
						<?php }	?>
					
					<tr><th><label>Можно вставить переменные</label></th><td>
						<pre><code>{NUM} - номер заказа, {FNUM} - №номерзаказа, {SUM} - сумма заказа, {FSUM} - суммазаказа руб., {EMAIL} - эл.почта покупателя,
{PHONE} - телефон покупателя, {FIRSTNAME} - имя покупателя, {LASTNAME} - фамилия покупателя,
{CITY} - город доставки, {ADDRESS} - адрес доставки, {BLOGNAME} - название блога/магазина,
{OLD_STATUS} - старый статус, {NEW_STATUS} - новый статус, {ITEMS} - список заказанных товаров
{COMMENT} - комментарий покупателя к заказу
{SHIPPING_METHOD} - способ доставки, выбранный покупателем, {PAYMENT_METHOD} - способ оплаты, выбранный покупателем
{TRACKING_NUMBER} => Трекинговый номер, {TRACKING_URL} => URL для отслеживания
<strong>{Произвольное поле}</strong> - вставка значения произвольного поля, которое вы или плагины добавили к заказу, 
например, {post_tracking_number} или {ems_tracking_number} если установлен плагин 
. Чувствительно к регистру символов!</code></pre></td></tr>
				</table>
				<input type="submit" class="button-primary" value="Сохранить">
			</form>
		</div>
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
					'{TRACKING_URL}' => 'http://track.4px.com/query/',
					'{TRACKING_NUMBER}' => '1234567890',
					'{SHIPPING_METHOD}' => 'Доставка курьером',
					'{PAYMENT_METHOD}' => 'Оплата картой',
					'{' => '*',
					'}' => '*',
				);
				$test_message = "Заказ №{NUM} на сумму {SUM} ({FSUM}) от {FIRSTNAME} {LASTNAME}, город {CITY}, адрес {ADDRESS}. 
				Контактный email: {EMAIL}, телефон: {PHONE}. 
				Статус изменен с {OLD_STATUS} на {NEW_STATUS}.
				Товары: {ITEMS}. 
				Комментарий клиента: {COMMENT}. 
				Трек-номер: {TRACKING_NUMBER}. URL для отслеживания: {TRACKING_URL}.
				Магазин: {BLOGNAME}.
				Способ оплаты: {PAYMENT_METHOD}.";

				$message = str_replace( array_keys($data), array_values($data), sanitize_text_field( $test_message ) );
			}
			$profile_id = sanitize_text_field(get_option('wappi_sender'));

			if (strlen($profile_id) != 20) { 
				$data = $this->_get_profile_info($profile_id);	
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
			} else {
				$this->_post($p['vendor_phone'], $message, $profile_id);
				$this->_output_cascade($this->_get_cascade_info($profile_id));				
			}
		}
	}

	public function status_changed($order_id, $old_status = 'pending', $new_status = 'pending')
	{
		$p = $this->_get_parameters();

		if ( $p['apikey'] ) {

			$o = wc_get_order($order_id);

			// $p['vendor_msg1'] = str_replace("\n", "<br>", $p['vendor_msg1']);
			// $p['vendor_msg2'] = str_replace("\n", "<br>", $p['vendor_msg2']);
			// $p['shopper_msg1'] = str_replace("\n", "<br>", $p['shopper_msg1']);
			// $p['shopper_msg2'] = str_replace("\n", "<br>", $p['shopper_msg2']);
			// $p['shopper_msg3'] = str_replace("\n", "<br>", $p['shopper_msg3']);			
							

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
			$r .= '<label>'.esc_html($v).' <input type="checkbox" name="'.esc_attr($name).'[]"'.(in_array($k, $selected, true) ? ' checked="checked"' : '').' value="'.esc_attr($k).'" /></label>&nbsp;&nbsp;';
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
		global $wpdb;

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
			'{COMMENT}',
			'{SHIPPING_METHOD}',
			'{PAYMENT_METHOD}',
			'{TRACKING_NUMBER}',
			'{TRACKING_URL}'

		);

		$shipping_methods = $order->get_shipping_methods();
		$payment_method_title = $order->get_payment_method_title();

		$shipping_method = '';
		if (!empty($shipping_methods)) {
			$first_shipping = reset($shipping_methods);
			$shipping_method = $first_shipping->get_name() ?? 'Метод доставки не указан';
		} else {
			$shipping_method = 'Метод доставки не указан';
		}

		$payment_method = !empty($payment_method_title) ? $payment_method_title : 'Метод оплаты не указан';

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
			$order->get_customer_note(),
			$shipping_method,
			$payment_method
		);
		
		$tracking_number = '';
		$tracking_url = '';
		
		foreach ($order->get_items() as $item_id => $item) {
			$tracking_data = get_metadata('order_item', $item_id, '_vi_wot_order_item_tracking_data', true);
		
			if ($tracking_data) {
				$tracking_data = json_decode($tracking_data, true);
				if (is_array($tracking_data) && !empty($tracking_data)) {
					$tracking_number = $tracking_data[0]['tracking_number'] ?? 'Трекинговый номер не найден';
					$tracking_url = $tracking_data[0]['carrier_url'] ?? 'URL для отслеживания не найден';
					break;
				}
			}
		}
		
		$replace[] = $tracking_number;
		$replace[] = $tracking_url;
		
		if (strpos($message, '{ITEMS}') !== false) {
			$items = $order->get_items();
			$items_str = '';
			foreach ($items as $i) {
				$product = $i->get_product();
				$name = $i->get_name();
				if ($product && $product->get_sku()) {
					$name = $product->get_sku() . ' ' . $name;
				}
				$quantity = $i->get_quantity();
				$line_total = $i->get_total();
				$line_subtotal = $i->get_subtotal();
				
				$items_str .= "\n" . $name . ': ' . $quantity . 'x' . $line_total . '=' . $line_subtotal;
			}

			$sh = $order->get_shipping_methods();
			foreach ($sh as $shipping_item) {
				$items_str .= "\n" . __('Shipping', 'woocommerce') . ': ' . $shipping_item->get_name() . '=' . $shipping_item->get_total();
			}

			$items_str .= "\n";
			$search[] = '{ITEMS}';
			$replace[] = wp_strip_all_tags($items_str);
		}
		$order_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d",
				$order->get_id()
			),
			ARRAY_A
		);

		if ($order_meta) {
			foreach ($order_meta as $meta) {
				if ($meta['meta_key'][0] != "_") {
					$search[] = '{' . $meta['meta_key'] . '}';
					$replace[] = $meta['meta_value'];
				}
			}
		}
		if ($meta = get_post_meta($order->get_id())) {
			foreach ($meta as $k => $v) {
				if ($k[0] != "_") {
					$search[] = '{' . $k . '}';
					$replace[] = $v[0];
				}
			}
		}
		foreach ($replace as $k => $v) {
			$replace[$k] = html_entity_decode($v);
		}
		
		$message = str_replace($search, $replace, $message);
		$message = preg_replace('/\s?\{[^}]+\}/', '', $message);
		$message = trim($message);
		$message = mb_substr($message, 0, 5000);
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
	private function _get_profile_info($profile_id) {
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

	private function _output_cascade($data) {
		if (isset($data['cascade']) && isset($data['cascade']['order'])) {
			$cascade = $data['cascade'];
			$cascade_name = $cascade['name'] ?? 'Unknown';
			$order = $cascade['order'];
		
			$platforms = array_map(function ($item) {
				$platform = $item['platform'] ?? 'Unknown';
				$profile_uuid = $item['profile_uuid'] ?? 'Unknown';
		
				switch ($platform) {
					case 'wz':
						$platform_display = 'WhatsApp';
						break;
					case 'tg':
						$platform_display = 'Telegram';
						break;
					case 'sms':
						$platform_display = 'СМС';
						break;
					default:
						$platform_display = $platform;
						break;
				}				
		
				return "{$platform_display} {$profile_uuid}";
			}, $order);
		
			$platforms_list = implode(', ', $platforms);
		
			echo "Каскад \"{$cascade_name}\": {$platforms_list}";
		} else {
			echo "Нет данных для отображения каскада.";
		}
	}

	/**
	 * Getting cascade information through the Wappi API.
	 *
	 * @return array cascade info.
	 */
	private function _get_cascade_info($cascade_id) {
		$apikey = sanitize_text_field(get_option('wappi_apikey'));
		$url = esc_url_raw('https://wappi.pro/csender/cascade/get?cascade_id=' . urlencode($cascade_id));

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

			if (strlen($profile_id) != 20) {
				$url = esc_url_raw('https://wappi.pro/' . $platform . 'api/sync/message/send?profile_id=' . urlencode($profile_id));
			
				$message_json = json_encode(array(
					'recipient' => sanitize_text_field($phone),
					'body' => wp_kses_post($message)
				));
			} else {
				$url = esc_url_raw('https://wappi.pro/csender/cascade/send');

				$message_json = json_encode(array(
					'recipient' => sanitize_text_field($phone),
					'body' => wp_kses_post($message),
					'cascade_id' => $profile_id
				));
			}

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
