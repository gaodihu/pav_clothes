<?php 
class ControllerCheckoutNicoCheckout extends Controller { 

	public function index() 
	{
		$data = array();


		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			 $this->response->redirect($this->url->link('checkout/cart'));
		}


		$this->login(false, $data);


		$this->validate($data);

		//$this->guest(false, $data);
		$this->checkout(false, $data);
		$this->shipping_address(false, $data);
		$this->shipping_method(false, $data);
		$this->payment_method(false, $data);
		//$this->payment_address(false, $data);
		$this->cart(false);
		$this->confirm(false, $data);
		//var_dump($data);
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/nicocheckout.tpl')) {
			$this->template  = $template =  $this->config->get('config_template') . '/template/checkout/nicocheckout.tpl';
		} else {
			$this->template  = $template = 'default/template/checkout/nicocheckout.tpl';
		}


		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/checkheader');

        
        if (isset($this->request->get['quickconfirm'])) {
            $data['quickconfirm'] = $this->request->get['quickconfirm'];
        }
				
		if ($this->customer->isLogged())
		{ 
			$data['firstname'] = $this->customer->getFirstName();
			$data['lastname'] = $this->customer->getLastName();
			$data['email'] = $this->customer->getEmail();
			$data['telephone'] = $this->customer->getTelephone();
			$data['payment_address_id'] = $this->customer->getAddressId();
			$data['address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
		}



		$this->session->data['_nicocheckout'] = $data;
		$this->response->setOutput($this->load->view($template, $data));

  	}
	
	public function validate($data  = array())
	{
		$json = array();
		if (!$this->customer->isLogged()) {
			if (isset($_REQUEST['register']) && !empty($_REQUEST['register'])) {
				$json = $this->register_validate($data);
			} else {
				if (!isset($this->session->data['customer_id'])) $json = $this->guest_validate($data);
			}
		}
		//if (!isset($json['error']) /*&& !$this->customer->isLogged()*/) $json = array_merge($json, $this->payment_address_validate());
		if (!isset($json['error'])) $json = array_merge($json, $this->shipping_address_validate());
		if (!isset($json['error'])) $json = array_merge($json, $this->shipping_method_validate());
		if (!isset($json['error'])) $json = array_merge($json, $this->payment_method_validate());
		
		$this->response->setOutput(json_encode($json));	
	}

	public function register_validate(&$data = array())
	{

		$this->load->language('checkout/nicocheckout');
		$this->load->model('account/customer');
		$this->load->model('account/address');

		$json = array();

		// Validate if customer is already logged out.
		if ($this->customer->isLogged()) {
			$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirments.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = $this->url->link('checkout/cart');

				break;
			}
		}

		if (!$json) {

			$this->load->model('account/customer');

			if (isset($this->request->post['shipping_firstname']) && ((utf8_strlen($this->request->post['shipping_firstname']) < 1) || (utf8_strlen($this->request->post['shipping_firstname']) > 32))) {
				$json['error']['shipping_firstname'] = $this->language->get('error_firstname');
			}

			if (isset($this->request->post['shipping_lastname']) && ((utf8_strlen($this->request->post['shipping_lastname']) < 1) || (utf8_strlen($this->request->post['shipping_lastname']) > 32))) {
				$json['error']['shipping_lastname'] = $this->language->get('error_lastname');
			}

			if (isset($this->request->post['email']) && ((utf8_strlen($this->request->post['email']) > 96) || !preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $this->request->post['email']))) {
				$json['error']['email'] = $this->language->get('error_email');
			}

			if (isset($this->request->post['email']) && ($this->model_account_customer->getTotalCustomersByEmail($this->request->post['email']))) {
				$json['error']['warning'] = $this->language->get('error_exists');
			}

			if (isset($this->request->post['shipping_telephone']) && ((utf8_strlen($this->request->post['shipping_telephone']) < 3) || (utf8_strlen($this->request->post['shipping_telephone']) > 32))) {
				$json['error']['shipping_telephone'] = $this->language->get('error_telephone');
			}


			if (isset($this->request->post['shipping_address_1']) && ((utf8_strlen($this->request->post['shipping_address_1']) < 3) || (utf8_strlen($this->request->post['shipping_address_1']) > 128))) {
				$json['error']['shipping_address_1'] = $this->language->get('error_address');
			}

			if (isset($this->request->post['shipping_city']) && ((utf8_strlen($this->request->post['shipping_city']) < 2) || (utf8_strlen($this->request->post['shipping_city']) > 128))) {
				$json['error']['shipping_city'] = $this->language->get('error_city');
			}

			$this->load->model('localisation/country');

			$country_info = $this->model_localisation_country->getCountry(isset($this->request->post['shipping_country_id']) ? $this->request->post['shipping_country_id'] : 0);

			if ($country_info) {
				if ($country_info['postcode_required'] && (utf8_strlen($this->request->post['shipping_postcode']) < 2) || (utf8_strlen($this->request->post['shipping_postcode']) > 10)) {
					$json['error']['shipping_postcode'] = $this->language->get('error_postcode');
				}


			}

			if (isset($this->request->post['shipping_country_id']) && empty($this->request->post['shipping_country_id'])) {
				$json['error']['shipping_country_id'] = $this->language->get('error_country');
			}

			if (isset($this->request->post['shipping_zone_id']) && ($this->request->post['shipping_zone_id'] === '' || $this->request->post['shipping_zone_id'] < 0)) {
				$json['error']['shipping_zone_id'] = $this->language->get('error_zone');
			}

			if (isset($this->request->post['register']) && ((utf8_strlen($this->request->post['password']) < 4) || (utf8_strlen($this->request->post['password']) > 20))) {
				$json['error']['password'] = $this->language->get('error_password');
			}

			if (isset($this->request->post['confirm']) && ($this->request->post['confirm'] != $this->request->post['password'])) {
				$json['error']['confirm'] = $this->language->get('error_confirm');
			}


		}

		if (!$json) {
			//uncomment this


			$this->session->data['account'] = 'register';


				if (!$this->customer->isLogged())
				{

					$this->load->model('account/customer');

					$this->session->data['nicocheckout_customer_id'] = $customer_id = $this->model_account_customer->addCustomer(array(
						'email' => $this->request->post['email'],
						'firstname' =>$this->request->post['shipping_firstname'],
						'lastname' =>$this->request->post['shipping_lastname'],
						'telephone' =>$this->request->post['shipping_telephone'],
						'password' =>$this->request->post['password'],
					));


					$this->session->data['nicocheckout_customer_id'] = true;

				}
			    $customer_group_id = 1;

				$this->load->model('account/customer_group');

				$customer_group = $this->model_account_customer_group->getCustomerGroup($customer_group_id);



			if ($customer_group && !$customer_group['approval']) {
				$this->customer->login($this->request->post['email'], $this->request->post['password']);



						// Default Payment Address
					$this->load->model('account/address');

					$this->session->data['payment_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());

					if (!empty($this->request->post['shipping_address'])) {
						//$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
					}

			} else {
				$json['redirect'] = $this->url->link('account/success');
			}

			unset($this->session->data['guest']);
			//unset($this->session->data['shipping_method']);
			//unset($this->session->data['shipping_methods']);
			//unset($this->session->data['payment_method']);
			//unset($this->session->data['payment_methods']);


				// Add to activity log
				$this->load->model('account/activity');

				$activity_data = array(
					'customer_id' => $customer_id,
					'name'        => $this->request->post['firstname'] . ' ' . $this->request->post['lastname']
				);

				$this->model_account_activity->addActivity('register', $activity_data);


		}

		return $json;
	}
	
	//validate
	
	public function guest_validate() {

		 $this->load->language('checkout/nicocheckout');


			$json = array();

			// Validate if customer is logged in.
			if ($this->customer->isLogged()) {
				$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
			}

			// Validate cart has products and has stock.
			if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
				$json['redirect'] = $this->url->link('checkout/cart');
			}

			// Check if guest checkout is available.
			if (!$this->config->get('config_checkout_guest') || $this->config->get('config_customer_price') || $this->cart->hasDownload()) {
				$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
			}

			if (!$json) {
				if (isset($this->request->post['shipping_firstname']) && ((utf8_strlen(trim($this->request->post['shipping_firstname'])) < 1) || (utf8_strlen(trim($this->request->post['shipping_firstname'])) > 32))) {
					$json['error']['shipping_firstname'] = $this->language->get('error_firstname');
				}

				if (isset($this->request->post['shipping_lastname']) && ((utf8_strlen(trim($this->request->post['shipping_lastname'])) < 1) || (utf8_strlen(trim($this->request->post['shipping_lastname'])) > 32))) {
					$json['error']['shipping_lastname'] = $this->language->get('error_lastname');
				}

				if (isset($this->request->post['email']) && ((utf8_strlen($this->request->post['email']) > 96) || !preg_match('/^[^\@]+@.*.[a-z]{2,15}$/i', $this->request->post['email']))) {
					$json['error']['email'] = $this->language->get('error_email');
				}

				if (isset($this->request->post['shipping_telephone']) && ((utf8_strlen($this->request->post['shipping_telephone']) < 3) || (utf8_strlen($this->request->post['shipping_telephone']) > 32))) {
					$json['error']['shipping_telephone'] = $this->language->get('error_telephone');
				}

				if (isset($this->request->post['shipping_address_1']) && ((utf8_strlen(trim($this->request->post['shipping_address_1'])) < 3) || (utf8_strlen(trim($this->request->post['shipping_address_1'])) > 128))) {
					$json['error']['shipping_address_1'] = $this->language->get('error_address_1');
				}

				if (isset($this->request->post['shipping_city']) && ((utf8_strlen(trim($this->request->post['shipping_city'])) < 2) || (utf8_strlen(trim($this->request->post['shipping_city'])) > 128))) {
					$json['error']['shipping_city'] = $this->language->get('error_city');
				}

				$this->load->model('localisation/country');
				$country_info = array();
				if (isset($this->request->post['shipping_country_id'])) $country_info = $this->model_localisation_country->getCountry($this->request->post['shipping_country_id']);

				if ($country_info && $country_info['postcode_required'] && (utf8_strlen(trim($this->request->post['shipping_postcode'])) < 2 || utf8_strlen(trim($this->request->post['shipping_postcode'])) > 10)) {
					$json['error']['shipping_postcode'] = $this->language->get('error_postcode');
				}

				if (isset($this->request->post['shipping_country_id']) && empty($this->request->post['shipping_country_id'])) {
					$json['error']['shipping_country'] = $this->language->get('error_country');
					$json['error']['shipping_country_id'] = $this->language->get('error_country');
				}

				if (isset($this->request->post['shipping_zone_id']) && ($this->request->post['shipping_zone_id'] === '' || $this->request->post['shipping_zone_id'] < 0)) {
					//$json['error']['zone'] = $this->language->get('error_zone');
					$json['error']['shipping_zone_id'] = $this->language->get('error_zone');
				}
				$customer_group_id = 1;
			}

			if (!$json && $this->request->post) {
				$this->session->data['account'] = 'guest';


				$this->session->data['guest']['email'] = $this->request->post['email'];




				$this->load->model('localisation/country');

				$country_info = '';
				if (isset($this->request->post['shipping_country_id']))
				$country_info = $this->model_localisation_country->getCountry($this->request->post['shipping_country_id']);




				$this->load->model('localisation/zone');

				$zone_info = '';
				if (isset($this->request->post['shipping_zone_id']))
				$zone_info = $this->model_localisation_zone->getZone($this->request->post['shipping_zone_id']);



				    $this->session->data['shipping_address']['telephone'] = $this->request->post['shipping_telephone'];
					$this->session->data['shipping_address']['firstname'] = $this->request->post['shipping_firstname'];
					$this->session->data['shipping_address']['lastname'] = $this->request->post['shipping_lastname'];
					$this->session->data['shipping_address']['company'] = $this->request->post['shipping_company'];
					$this->session->data['shipping_address']['address_1'] = $this->request->post['shipping_address_1'];
					$this->session->data['shipping_address']['address_2'] = isset($this->request->post['shipping_address_2'])?$this->request->post['shipping_address_2']:'';;
					$this->session->data['shipping_address']['postcode'] = isset($this->request->post['shipping_postcode'])?$this->request->post['shipping_postcode']:'';
					$this->session->data['shipping_address']['city'] = isset($this->request->post['shipping_city'])?$this->request->post['shipping_city']:'';
					$this->session->data['shipping_address']['country_id'] = isset($this->request->post['shipping_country_id'])?$this->request->post['shipping_country_id']:'';
					$this->session->data['shipping_address']['zone_id'] =isset($this->request->post['shipping_zone_id'])?$this->request->post['shipping_zone_id']:'';

					if ($country_info) {
						$this->session->data['shipping_address']['country'] = $country_info['name'];
						$this->session->data['shipping_address']['iso_code_2'] = $country_info['iso_code_2'];
						$this->session->data['shipping_address']['iso_code_3'] = $country_info['iso_code_3'];
						$this->session->data['shipping_address']['address_format'] = $country_info['address_format'];
					} else {
						$this->session->data['shipping_address']['country'] = '';
						$this->session->data['shipping_address']['iso_code_2'] = '';
						$this->session->data['shipping_address']['iso_code_3'] = '';
						$this->session->data['shipping_address']['address_format'] = '';
					}

					if ($zone_info) {
						$this->session->data['shipping_address']['zone'] = $zone_info['name'];
						$this->session->data['shipping_address']['zone_code'] = $zone_info['code'];
					} else {
						$this->session->data['shipping_address']['zone'] = '';
						$this->session->data['shipping_address']['zone_code'] = '';
					}

				}

	//			unset($this->session->data['shipping_method']);
	//			unset($this->session->data['shipping_methods']);
	//			unset($this->session->data['payment_method']);
	//			unset($this->session->data['payment_methods']);


			return $json;

	}

	public function shipping_address_validate(&$data = array()) {

		$this->load->language('checkout/nicocheckout');

		$json = array();

		// Validate if customer is logged in.
		if (!$this->customer->isLogged()) {
			//$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate if shipping is required. If not the customer should not have reached this page.
		if (!$data['shipping_required'] = $this->cart->hasShipping()) {
			//$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			//$json['redirect'] = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirments.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = $this->url->link('checkout/cart');

				break;
			}
		}

		if (!$json) {

			if (isset($this->request->post['shipping_address']) && $this->request->post['shipping_address'] == 'existing') {
				$this->load->model('account/address');

				if (empty($this->request->post['shipping_address_id'])) {
					$json['error']['warning'] = $this->language->get('error_address');
				} elseif (!in_array($this->request->post['shipping_address_id'], array_keys($this->model_account_address->getAddresses()))) {
					$json['error']['warning'] = $this->language->get('error_address');
				}

				if (!$json) {
					$this->session->data['shipping_address_id'] = $this->request->post['shipping_address_id'];

					// Default Shipping Address
					$this->load->model('account/address');

					$address_info = $this->model_account_address->getAddress($this->request->post['shipping_address_id']);

					if ($address_info) {
						$this->session->data['shipping_country_id'] = $address_info['country_id'];
						$this->session->data['shipping_zone_id'] = $address_info['zone_id'];
						$this->session->data['shipping_postcode'] = $address_info['postcode'];
					} else {
						unset($this->session->data['shipping_country_id']);
						unset($this->session->data['shipping_zone_id']);
						unset($this->session->data['shipping_postcode']);
					}

				}
			}

			if (!isset($this->request->post['shipping_address']) || (isset($this->request->post['shipping_address']) && $this->request->post['shipping_address'] == 'new')) {
				if ((utf8_strlen($this->request->post['shipping_firstname']) < 1) || (utf8_strlen($this->request->post['shipping_firstname']) > 32)) {
					$json['error']['shipping_firstname'] = $this->language->get('error_firstname');
				}

				if ((utf8_strlen($this->request->post['shipping_lastname']) < 1) || (utf8_strlen($this->request->post['shipping_lastname']) > 32)) {
					$json['error']['shipping_lastname'] = $this->language->get('error_lastname');
				}

				if ((utf8_strlen($this->request->post['shipping_address_1']) < 3) || (utf8_strlen($this->request->post['shipping_address_1']) > 128)) {
					$json['error']['shipping_address_1'] = $this->language->get('error_address_1');
				}

				if (isset($this->request->post['shipping_city']) && ((utf8_strlen($this->request->post['shipping_city']) < 2) || (utf8_strlen($this->request->post['shipping_city']) > 128))) {
					$json['error']['shipping_city'] = $this->language->get('error_city');
				}

				if (isset($this->request->post['shipping_telephone']) && ((utf8_strlen($this->request->post['shipping_telephone']) < 3) || (utf8_strlen($this->request->post['shipping_telephone']) > 32))) {
					$json['error']['shipping_telephone'] = $this->language->get('error_telephone');
				}

				$this->load->model('localisation/country');

				$country_info = $this->model_localisation_country->getCountry($this->request->post['shipping_country_id']);

				if ($country_info && $country_info['postcode_required'] && (utf8_strlen($this->request->post['shipping_postcode']) < 2) || (utf8_strlen($this->request->post['shipping_postcode']) > 10)) {
					$json['error']['shipping_postcode'] = $this->language->get('error_postcode');
				}

				if (isset($this->request->post['shipping_country_id']) && empty($this->request->post['shipping_country_id'])) {
					$json['error']['shipping_country'] = $this->language->get('error_country');
				}

				if (isset($this->request->post['shipping_zone_id']) && ($this->request->post['shipping_zone_id'] === '' || $this->request->post['shipping_zone_id'] < 0)) {
					$json['error']['shipping_zone'] = $this->language->get('error_zone');
				}

				if (!$json) {
					// Default Shipping Address
					$this->load->model('account/address');
					$_shipping_address = array();
					foreach($this->request->post as $key => $value)
					{
							if (strpos($key, 'shipping_') !== false) $_shipping_address[str_replace('shipping_','',$key)] = $value;
					}

					$_shipping_address = array_merge(array('address_2' => '', 'country_id' => '','zone_id' => '','city' => '','postcode' => ''),$_shipping_address);//avoid notices for empty fields


					//if (!isset($this->session->data['shipping_address_id'])) $this->session->data['shipping_address_id'] = $this->model_account_address->addAddress($_shipping_address);
					$this->session->data['shipping_country_id'] = $this->request->post['shipping_country_id'];
					$this->session->data['shipping_zone_id'] = $this->request->post['shipping_zone_id'];
					$this->session->data['shipping_postcode'] = $this->request->post['shipping_postcode'];


					if($this->customer->isLogged()){
						$this->load->model('localisation/country');

						$country_info = '';
						if (isset($this->request->post['shipping_country_id'])) {
							$country_info = $this->model_localisation_country->getCountry($this->request->post['shipping_country_id']);
						}

						$this->load->model('localisation/zone');

						$zone_info = '';
						if (isset($this->request->post['shipping_zone_id'])) {
							$zone_info = $this->model_localisation_zone->getZone($this->request->post['shipping_zone_id']);
						}

						$this->session->data['shipping_address']['telephone'] = $this->request->post['shipping_telephone'];
						$this->session->data['shipping_address']['firstname'] = $this->request->post['shipping_firstname'];
						$this->session->data['shipping_address']['lastname'] = $this->request->post['shipping_lastname'];
						$this->session->data['shipping_address']['company'] = $this->request->post['shipping_company'];
						$this->session->data['shipping_address']['address_1'] = $this->request->post['shipping_address_1'];
						$this->session->data['shipping_address']['address_2'] = isset($this->request->post['shipping_address_2'])?$this->request->post['shipping_address_2']:'';;
						$this->session->data['shipping_address']['postcode'] = isset($this->request->post['shipping_postcode'])?$this->request->post['shipping_postcode']:'';
						$this->session->data['shipping_address']['city'] = isset($this->request->post['shipping_city'])?$this->request->post['shipping_city']:'';
						$this->session->data['shipping_address']['country_id'] = isset($this->request->post['shipping_country_id'])?$this->request->post['shipping_country_id']:'';
						$this->session->data['shipping_address']['zone_id'] =isset($this->request->post['shipping_zone_id'])?$this->request->post['shipping_zone_id']:'';

						if ($country_info) {
							$this->session->data['shipping_address']['country'] = $country_info['name'];
							$this->session->data['shipping_address']['iso_code_2'] = $country_info['iso_code_2'];
							$this->session->data['shipping_address']['iso_code_3'] = $country_info['iso_code_3'];
							$this->session->data['shipping_address']['address_format'] = $country_info['address_format'];
						} else {
							$this->session->data['shipping_address']['country'] = '';
							$this->session->data['shipping_address']['iso_code_2'] = '';
							$this->session->data['shipping_address']['iso_code_3'] = '';
							$this->session->data['shipping_address']['address_format'] = '';
						}

						if ($zone_info) {
							$this->session->data['shipping_address']['zone'] = $zone_info['name'];
							$this->session->data['shipping_address']['zone_code'] = $zone_info['code'];
						} else {
							$this->session->data['shipping_address']['zone'] = '';
							$this->session->data['shipping_address']['zone_code'] = '';
						}
						$this->session->data['shipping_address']['default'] = 1;

						$this->session->data['shipping_address_id']  = $this->model_account_address->addAddress($this->session->data['shipping_address']);

					}

				}
			}
		}

		return $json;
	}

	public function shipping_method_validate(&$data  = array()) {

		 $this->load->language('checkout/nicocheckout');

		$json = array();

		// Validate if shipping is required. If not the customer should not have reached this page.
		if (!$data['shipping_required'] = $this->cart->hasShipping()) {
			//$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate if shipping address has been set.
		$this->load->model('account/address');

		if ($this->customer->isLogged() && isset($this->session->data['shipping_address_id'])) {
			$shipping_address = $this->model_account_address->getAddress($this->session->data['shipping_address_id']);
		} elseif (isset($this->session->data['guest'])) {
			$shipping_address = isset($this->session->data['guest']['shipping'])?$this->session->data['guest']['shipping']:'';
		}

		if (empty($shipping_address)) {
			//$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			//$json['redirect'] = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirments.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = $this->url->link('checkout/cart');

				break;
			}
		}

		if (!$json) {
			if ($data['shipping_required'] && !isset($this->request->post['shipping_method'])) {
				$json['error']['warning'] = $this->language->get('error_shipping');
			} else if ($data['shipping_required'])
			{
				$shipping = explode('.', $this->request->post['shipping_method']);
				if (!isset($shipping[0]) || !isset($shipping[1])/* || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])*/) {
					$json['error']['warning'] = $this->language->get('error_shipping');
				}
			}

			if (!$json && $data['shipping_required']) {
				$shipping = explode('.', $this->request->post['shipping_method']);

				if (isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]]))
				$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];

				$this->session->data['comment'] = (isset($this->request->post['comment']))?strip_tags($this->request->post['comment']):'';
			}
		}

		return $json;
	}
	
	public function payment_method_validate(&$data  = array()) {

		$this->load->language('checkout/nicocheckout');

		$json = array();

		// Validate if payment address has been set.
		$this->load->model('account/address');

		if ($this->customer->isLogged() && isset($this->session->data['payment_address_id'])) {
			$payment_address = $this->model_account_address->getAddress($this->session->data['payment_address_id']);
		} elseif (isset($this->session->data['guest']['payment'])) {
			$payment_address = $this->session->data['guest']['payment'];
		} else
		{
			$payment_address = $this->model_account_address->getAddress(0);
		}

		if (empty($payment_address)) {
			//$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			//$json['redirect'] = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirments.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = $this->url->link('checkout/cart');

				break;
			}
		}

		if (!$json) {
			if (!isset($this->request->post['payment_method'])) {
				$json['error']['warning'] = $this->language->get('error_payment');
			} elseif (!isset($this->session->data['payment_methods'][$this->request->post['payment_method']])) {
//				error_log(print_r($this->session->data['payment_methods'],1));
				$json['error']['warning'] = $this->language->get('error_payment');
			}

			if ($this->config->get('config_checkout_id')) {
				$this->load->model('catalog/information');

				$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

				if ($information_info && !isset($this->request->post['agree'])) {
					//$json['error']['warning'] = sprintf($this->language->get('error_agree'), $information_info['title']);
				}
			}

			if (!$json) {
				$this->session->data['payment_method'] = $this->session->data['payment_methods'][$this->request->post['payment_method']];

				$this->session->data['comment'] = (isset($this->request->post['comment']))?strip_tags($this->request->post['comment']):'';
			}
		}

		return $json;
	}
	
	public function login($render = false, &$data  = array()) {
		 $this->load->language('checkout/nicocheckout');

		$data['text_new_customer'] = $this->language->get('text_new_customer');
		$data['text_returning_customer'] = $this->language->get('text_returning_customer');
		$data['text_checkout'] = $this->language->get('text_checkout');
		$data['text_register'] = $this->language->get('text_register');
		$data['text_guest'] = $this->language->get('text_guest');
		$data['text_i_am_returning_customer'] = $this->language->get('text_i_am_returning_customer');
		$data['text_register_account'] = $this->language->get('text_register_account');
		$data['text_forgotten'] = $this->language->get('text_forgotten');

		$data['entry_email'] = $this->language->get('entry_email');
		$data['entry_password'] = $this->language->get('entry_password');
		$data['entry_confirm'] = $this->language->get('entry_confirm');

		$data['button_continue'] = $this->language->get('button_continue');
		$data['button_login'] = $this->language->get('button_login');

		$data['guest_checkout'] = ($this->config->get('config_guest_checkout') && !$this->config->get('config_customer_price') && !$this->cart->hasDownload());

		if (isset($this->session->data['account'])) {
			$data['account'] = $this->session->data['account'];
		} else {
			$data['account'] = 'register';
		}

		$data['forgotten'] = $this->url->link('account/forgotten', '', 'SSL');

		if ($render !== false)
		{
			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/login.tpl')) {
				$this->template = $this->config->get('config_template') . '/template/checkout/login.tpl';
			} else {
				$this->template = 'default/template/checkout/login.tpl';
			}


			$this->response->setOutput($this->render());
		}
	}
	
  	public function guest($render = false, &$data  = array()) {
    	 $this->load->language('checkout/nicocheckout');

		$data['text_select'] = $this->language->get('text_select');
		$data['text_none'] = $this->language->get('text_none');
		$data['text_your_details'] = $this->language->get('text_your_details');
		$data['text_your_account'] = $this->language->get('text_your_account');
		$data['text_your_address'] = $this->language->get('text_your_address');

		$data['entry_firstname'] = $this->language->get('entry_firstname');
		$data['entry_lastname'] = $this->language->get('entry_lastname');
		$data['entry_email'] = $this->language->get('entry_email');
		$data['entry_telephone'] = $this->language->get('entry_telephone');
		$data['entry_fax'] = $this->language->get('entry_fax');
		$data['entry_company'] = $this->language->get('entry_company');
		$data['entry_customer_group'] = $this->language->get('entry_customer_group');
		if ($this->language->get('entry_company_id') != 'entry_company_id') $data['entry_company_id'] = $this->language->get('entry_company_id');
		if ($this->language->get('entry_tax_id') != 'entry_tax_id') $data['entry_tax_id'] = $this->language->get('entry_tax_id');
		$data['entry_address_1'] = $this->language->get('entry_address_1');
		$data['entry_address_2'] = $this->language->get('entry_address_2');
		$data['entry_postcode'] = $this->language->get('entry_postcode');
		$data['entry_city'] = $this->language->get('entry_city');
		$data['entry_country'] = $this->language->get('entry_country');
		$data['entry_zone'] = $this->language->get('entry_zone');
		$data['entry_shipping'] = $this->language->get('entry_shipping');

		$data['button_continue'] = $this->language->get('button_continue');

		if (isset($this->session->data['guest']['firstname'])) {
			$data['firstname'] = $this->session->data['guest']['firstname'];
		} else {
			$data['firstname'] = '';
		}

		if (isset($this->session->data['guest']['lastname'])) {
			$data['lastname'] = $this->session->data['guest']['lastname'];
		} else {
			$data['lastname'] = '';
		}

		if (isset($this->session->data['guest']['email'])) {
			$data['email'] = $this->session->data['guest']['email'];
		} else {
			$data['email'] = '';
		}

		if (isset($this->session->data['guest']['telephone'])) {
			$data['telephone'] = $this->session->data['guest']['telephone'];
		} else {
			$data['telephone'] = '';
		}

		if (isset($this->session->data['guest']['fax'])) {
			$data['fax'] = $this->session->data['guest']['fax'];
		} else {
			$data['fax'] = '';
		}

		if (isset($this->session->data['guest']['payment']['company'])) {
			$data['company'] = $this->session->data['guest']['payment']['company'];
		} else {
			$data['company'] = '';
		}

		$this->load->model('account/customer_group');

		$data['customer_groups'] = array();

		if (is_array($this->config->get('config_customer_group_display'))) {
			$customer_groups = $this->model_account_customer_group->getCustomerGroups();

			foreach ($customer_groups as $customer_group) {
				if (in_array($customer_group['customer_group_id'], $this->config->get('config_customer_group_display'))) {
					$data['customer_groups'][] = $customer_group;
				}
			}
		}

		if (isset($this->session->data['guest']['customer_group_id'])) {
    		$data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
		} else {
			$data['customer_group_id'] = $this->config->get('config_customer_group_id');
			if (!isset($customer_group_id) || !$customer_group_id) $customer_group_id = 1;
		}

		// Company ID
		if (isset($this->session->data['guest']['payment']['company_id'])) {
			$data['company_id'] = $this->session->data['guest']['payment']['company_id'];
		} else {
			$data['company_id'] = '';
		}

		// Tax ID
		if (isset($this->session->data['guest']['payment']['tax_id'])) {
			$data['tax_id'] = $this->session->data['guest']['payment']['tax_id'];
		} else {
			$data['tax_id'] = '';
		}

		if (isset($this->session->data['guest']['payment']['address_1'])) {
			$data['address_1'] = $this->session->data['guest']['payment']['address_1'];
		} else {
			$data['address_1'] = '';
		}

		if (isset($this->session->data['guest']['payment']['address_2'])) {
			$data['address_2'] = $this->session->data['guest']['payment']['address_2'];
		} else {
			$data['address_2'] = '';
		}

		if (isset($this->session->data['guest']['payment']['postcode'])) {
			$data['postcode'] = $this->session->data['guest']['payment']['postcode'];
		} elseif (isset($this->session->data['shipping_postcode'])) {
			$data['postcode'] = $this->session->data['shipping_postcode'];
		} else {
			$data['postcode'] = '';
		}

		if (isset($this->session->data['guest']['payment']['city'])) {
			$data['city'] = $this->session->data['guest']['payment']['city'];
		} else {
			$data['city'] = '';
		}

		if (isset($this->session->data['guest']['payment']['country_id']) && $this->session->data['guest']['payment']['country_id']) {
			$data['country_id'] = $this->session->data['guest']['payment']['country_id'];
		} elseif (isset($this->session->data['shipping_country_id']) && $this->session->data['shipping_country_id']) {
			$data['country_id'] = $this->session->data['shipping_country_id'];
		} else {

			if ($this->config->get('config_tax_default') == 'payment' || $this->config->get('config_tax_default') == 'shipping')
			$data['country_id'] = $this->config->get('config_country_id');
			else $data['country_id'] = '';
		}

		if (isset($this->session->data['guest']['payment']['zone_id'])) {
			$data['zone_id'] = $this->session->data['guest']['payment']['zone_id'];
		} elseif (isset($this->session->data['shipping_zone_id'])) {
			$data['zone_id'] = $this->session->data['shipping_zone_id'];
		} else {
			$data['zone_id'] = '';
		}

		$this->load->model('localisation/country');

		$data['countries'] = $this->model_localisation_country->getCountries();

		$data['shipping_required'] = $this->cart->hasShipping();

		if (isset($this->session->data['guest']['shipping_address'])) {
			$data['shipping_address'] = $this->session->data['guest']['shipping_address'];
		} else {
			$data['shipping_address'] = true;
		}

		if ($render !== false)
		{
			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/guest.tpl')) {
				$this->template = $this->config->get('config_template') . '/template/checkout/guest.tpl';
			} else {
				$this->template = 'default/template/checkout/guest.tpl';
			}


			$this->response->setOutput($this->render());
		}
  	}
	
	public function checkout($render = true, &$data  = array())
	{
		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
	  		 $this->response->redirect($this->url->link('checkout/cart'));
    	}

		// Validate minimum quantity requirments.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				 $this->response->redirect($this->url->link('checkout/cart'));
			}
		}

		$this->load->language('checkout/nicocheckout');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addScript('catalog/view/javascript/jquery/colorbox/jquery.colorbox-min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/colorbox/colorbox.css');

		$data['breadcrumbs'] = array();

      	$data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home'),
        	'separator' => false
      	);

      	$data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('text_cart'),
			'href'      => $this->url->link('checkout/cart'),
        	'separator' => $this->language->get('text_separator')
      	);

      	$data['breadcrumbs'][] = array(
        	'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('checkout/nicocheckout', '', 'SSL'),
        	'separator' => $this->language->get('text_separator')
      	);

	    $data['heading_title'] = $this->language->get('heading_title');

		$data['text_checkout_option'] = $this->language->get('text_checkout_option');
		$data['text_checkout_account'] = $this->language->get('text_checkout_account');
		$data['text_checkout_payment_address'] = $this->language->get('text_checkout_payment_address');
		$data['text_checkout_shipping_address'] = $this->language->get('text_checkout_shipping_address');
		$data['text_checkout_shipping_method'] = $this->language->get('text_checkout_shipping_method');
		$data['text_checkout_payment_method'] = $this->language->get('text_checkout_payment_method');
		$data['text_checkout_confirm'] = $this->language->get('text_checkout_confirm');
		$data['text_modify'] = $this->language->get('text_modify');

		$data['text_billing_address'] = $this->language->get('text_billing_address');
		$data['text_shipping_address'] = $this->language->get('text_shipping_address');

		$data['text_shipping_method'] = $this->language->get('text_shipping_method');

		$data['text_payment_method'] = $this->language->get('text_payment_method');
		$data['text_review_your_order'] = $this->language->get('text_review_your_order');
		$data['text_place_order_now'] = $this->language->get('text_place_order_now');

		$data['logged'] = $this->customer->isLogged();
		$data['shipping_required'] = $this->cart->hasShipping();

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/checkout.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/checkout/checkout.tpl';
		} else {
			$this->template = 'default/template/checkout/checkout.tpl';
		}


		if ($render !== false)
		{
			$this->children = array(
				'common/column_left',
				'common/column_right',
				'common/content_top',
				'common/content_bottom',
				'common/footer',
				'common/header'
			);

			if (isset($this->request->get['quickconfirm'])) {
				$data['quickconfirm'] = $this->request->get['quickconfirm'];
			}


			$this->response->setOutput($this->render());
		}
  	}
	
	public function shipping_address($render = true, &$data  = array())
	{

		$this->load->language('checkout/nicocheckout');

		$data['text_address_existing'] = $this->language->get('text_address_existing');
		$data['text_address_new'] = $this->language->get('text_address_new');
		$data['text_select'] = $this->language->get('text_select');
		$data['text_none'] = $this->language->get('text_none');

		$data['entry_firstname'] = $this->language->get('entry_firstname');
		$data['entry_lastname'] = $this->language->get('entry_lastname');
		$data['entry_company'] = $this->language->get('entry_company');
		$data['entry_address_1'] = $this->language->get('entry_address_1');
		$data['entry_address_2'] = $this->language->get('entry_address_2');
		$data['entry_postcode'] = $this->language->get('entry_postcode');
		$data['entry_city'] = $this->language->get('entry_city');
		$data['entry_country'] = $this->language->get('entry_country');
		$data['entry_zone'] = $this->language->get('entry_zone');
		$data['entry_telephone'] = $this->language->get('entry_telephone');

		$data['button_continue'] = $this->language->get('button_continue');

		$pp_express = isset($this->session->data['paypal']['code']) && $this->session->data['paypal']['code'] != 'pp_exoress';
		$data['pp_express'] = $pp_express;
		if ($pp_express && isset($this->session->data['pp_express_address_id'])) {
			$pp_express_address_id = $this->session->data['pp_express_address_id'];
			$data['pp_express_address_id'] = $pp_express_address_id;
		}

		if ($this->customer->isLogged()) {
			if($pp_express ){
				if(isset($this->session->data['pp_express_address_id'])) {
					$data['shipping_address_id'] = $this->session->data['pp_express_address_id'];
				}else{

				}
			}else{
				if (isset($this->session->data['shipping_address']) && isset($this->session->data['shipping_address']['address_id'])) {
					$data['shipping_address_id'] = $this->session->data['shipping_address']['address_id'];
				} else {
					$data['shipping_address_id'] = $this->customer->getAddressId();
				}
			}

			$this->load->model('account/address');
			$data['addresses'] = $this->model_account_address->getAddresses();
		}else{

			$data['paypal_express_email'] = $this->session->data['paypal_express_email'];

		}


		if(!$this->session->data['shipping_address']['address_id']) {


			if (isset($this->session->data['shipping_address']['firstname'])) {
				$data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
			} else {
				$data['shipping_firstname'] = '';
			}

			if (isset($this->session->data['shipping_address']['lastname'])) {
				$data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
			} else {
				$data['shipping_lastname'] = '';
			}
			if (isset($this->session->data['shipping_address']['address_1'])) {
				$data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
			} else {
				$data['shipping_address_1'] = '';
			}
			if (isset($this->session->data['shipping_address']['address_2'])) {
				$data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
			} else {
				$data['shipping_address_2'] = '';
			}
			if (isset($this->session->data['shipping_address']['telephone'])) {
				$data['shipping_telephone'] = $this->session->data['shipping_address']['telephone'];
			} else {
				$data['shipping_telephone'] = '';
			}
			if (isset($this->session->data['shipping_address']['city'])) {
				$data['shipping_city'] = $this->session->data['shipping_address']['city'];
			} else {
				$data['shipping_city'] = '';
			}
			if (isset($this->session->data['shipping_address']['postcode'])) {
				$data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
			} else {
				$data['shipping_postcode'] = '';
			}

			if (isset($this->session->data['shipping_address']['country_id'])) {
				$data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
			} else {
				if ($this->config->get('config_tax_default') == 'shipping') {
					$data['shipping_country_id'] = $this->config->get('config_country_id');
				} else {
					$data['shipping_country_id'] = '';
				}
			}


			if (isset($this->session->data['shipping_address']['zone_id'])) {
				$data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
			} else {
				$data['shipping_zone_id'] = '';
			}


			$this->load->model('localisation/country');

			$data['countries'] = $this->model_localisation_country->getCountries();

			if ($data['shipping_country_id']) {
				$this->load->model('localisation/zone');
				$zones = $this->model_localisation_zone->getZonesByCountryId($data['shipping_country_id']);
				$data['zones'] = $zones;
			}
		}


		if ($render !== false)
		{
			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/shipping_address.tpl')) {
				$this->template = $this->config->get('config_template') . '/template/checkout/shipping_address.tpl';
			} else {
				$this->template = 'default/template/checkout/shipping_address.tpl';
			}


			$this->response->setOutput($this->render());
		}
  	}

	public function shipping_method($render = true, &$data = array())
	{

		$this->load->language('checkout/nicocheckout');

		$this->load->model('account/address');
		$data['shipping_required'] = $this->cart->hasShipping();

		$shipping_address = array('country_id' => 0, 'zone_id' => 0, 'city' => '', 'postcode' => '', 'firstname' => '', 'lastname' => '', 'company' => '', 'address_1' => '', 'telephone' => '');


		$country_id = '';
		$zone_id = '';
		$city = '';
		$postcode = '';

		if (isset($this->request->post['shipping_address']))
		{
			if ($this->request->post['shipping_address'] == 'new')
			{
				$country_id = '';
				if (isset($this->request->post['shipping_country_id']) && $this->request->post['shipping_country_id']) $country_id = $this->request->post['shipping_zone_id'];
				if (isset($this->request->post['shipping_zone_id']) && $this->request->post['shipping_zone_id']) $zone_id = $this->request->post['shipping_zone_id'];
				if (isset($this->request->post['shipping_city']) && $this->request->post['shipping_city']) $city = $this->request->post['shipping_city'];
				if (isset($this->request->post['shipping_postcode']) && $this->request->post['shipping_postcode']) $postcode = $this->request->post['shipping_postcode'];
			}
		} else if (isset($this->request->post['shipping_country_id']))
		{
				$country_id = '';
				if (isset($this->request->post['shipping_country_id']) && $this->request->post['shipping_country_id']) $country_id = $this->request->post['shipping_country_id'];
				if (isset($this->request->post['shipping_zone_id']) && $this->request->post['shipping_zone_id']) $zone_id = $this->request->post['shipping_zone_id'];
				if (isset($this->request->post['shipping_city']) && $this->request->post['shipping_city']) $city = $this->request->post['shipping_city'];
				if (isset($this->request->post['shipping_postcode']) && $this->request->post['shipping_postcode']) $postcode = $this->request->post['shipping_postcode'];
		}

		if (isset($country_id) && $country_id)
		{
			$this->session->data['shipping_address'] = array_merge($shipping_address, isset($this->session->data['shipping_address'])?$this->session->data['shipping_address']:array(),
														array('country_id' => $country_id,
															  'zone_id' => $zone_id,
															  'city' => $city,
															  'postcode' => $postcode));

			$this->load->model('localisation/country');

			$country_info = $this->model_localisation_country->getCountry($country_id);

			if ($country_info) {
				$this->session->data['shipping_address']['country'] = $country_info['name'];
				$this->session->data['shipping_address']['iso_code_2'] = $country_info['iso_code_2'];
				$this->session->data['shipping_address']['iso_code_3'] = $country_info['iso_code_3'];
				$this->session->data['shipping_address']['address_format'] = $country_info['address_format'];
			} else {
				$this->session->data['shipping_address']['country'] = '';
				$this->session->data['shipping_address']['iso_code_2'] = '';
				$this->session->data['shipping_address']['iso_code_3'] = '';
				$this->session->data['shipping_address']['address_format'] = '';
			}

			$this->load->model('localisation/zone');

			$zone_info = $this->model_localisation_zone->getZone($zone_id);

			if ($zone_info) {
				$this->session->data['shipping_address']['zone'] = $zone_info['name'];
				$this->session->data['shipping_address']['zone_code'] = $zone_info['code'];
			} else {
				$this->session->data['shipping_address']['zone'] = '';
				$this->session->data['shipping_address']['zone_code'] = '';
			}

			$this->session->data['shipping_country_id'] = $country_id;
			$this->session->data['shipping_zone_id'] = $zone_id;
			$this->session->data['shipping_postcode'] = $postcode;

			$shipping_address = $this->session->data['shipping_address'];

		} elseif ($this->customer->isLogged())
		{

			if (isset($this->request->post['shipping_address_id']))
			{
				$shipping_address_id = $this->request->post['shipping_address_id'];
			} else if (isset($this->session->data['shipping_address_id']))
			{
				$shipping_address_id = $this->session->data['shipping_address_id'];
			}


			if ($shipping_address_id)
			{
				$shipping_address = $this->model_account_address->getAddress($shipping_address_id);
				$data['shipping_address_id'] = $this->session->data['shipping_address_id'] = $shipping_address_id;
			}
		} elseif (isset($this->session->data['shipping_address'])) {
			$shipping_address = array_merge($shipping_address, $this->session->data['shipping_address']);
		}


			if (isset($shipping_address)) {

				$this->session->data['shipping_address'] = $shipping_address;

				$this->tax->setShippingAddress($shipping_address['country_id'], $shipping_address['zone_id']);

				// Shipping Methods
				$method_data = array();

				$this->load->model('extension/extension');

				$results = $this->model_extension_extension->getExtensions('shipping');

				foreach ($results as $result) {
					if ($this->config->get($result['code'] . '_status')) {
						$this->load->model('shipping/' . $result['code']);

						$quote = $this->{'model_shipping_' . $result['code']}->getQuote($shipping_address);

						if ($quote) {
							$method_data[$result['code']] = array(
								'title'      => $quote['title'],
								'quote'      => $quote['quote'],
								'sort_order' => $quote['sort_order'],
								'error'      => $render?$quote['error']:''
							);
						}
					}
				}

				$sort_order = array();

				foreach ($method_data as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $method_data);

				$this->session->data['shipping_methods'] = $method_data;
			}

			$data['text_shipping_method'] = $this->language->get('text_shipping_method');
			$data['text_comments'] = $this->language->get('text_comments');
			$data['text_loading'] = $this->language->get('text_loading');

			$data['button_continue'] = $this->language->get('button_continue');

			if (empty($this->session->data['shipping_methods'])) {
				$data['error_warning'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact'));
			} else {
				$data['error_warning'] = '';
			}

			if (isset($this->session->data['shipping_methods'])) {
				$data['shipping_methods'] = $this->session->data['shipping_methods'];
			} else {
				$data['shipping_methods'] = array();
			}

			if (isset($this->session->data['shipping_method']['code'])) {
				$data['code'] = $this->session->data['shipping_method']['code'];
			} else {
				$data['code'] = '';
			}

			if (isset($this->session->data['comment'])) {
				$data['comment'] = $this->session->data['comment'];
			} else {
				$data['comment'] = '';
			}
		if ($render !== false)
		{
			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/nico_shipping_method.tpl')) {
				$template = $this->template = $this->config->get('config_template') . '/template/checkout/nico_shipping_method.tpl';
			} else {
				$template = $this->template = 'default/template/checkout/nico_shipping_method.tpl';
			}


				$this->response->setOutput($this->load->view($template, $data));

		}

  	}
  	
  	public function payment_method($render = true, &$data = array()) {

		$this->load->language('checkout/nicocheckout');

		$this->load->model('account/address');


		$this->session->data['payment_address'] = $this->session->data['shipping_address'];
		$payment_address = $this->session->data['shipping_address'];


		$this->tax->setPaymentAddress($payment_address['country_id'], $payment_address['zone_id']);

		//if ($payment_address) {
			// Totals
			$total_data = array();
			$total = 0;
			$taxes = $this->cart->getTaxes();

				$this->load->model('extension/extension');
				$results = $this->model_extension_extension->getExtensions('total');


			$sort_order = array();


			foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
			}

			array_multisort($sort_order, SORT_ASC, $results);

			foreach ($results as $result) {
				if ($this->config->get($result['code'] . '_status')) {
					$this->load->model('total/' . $result['code']);

					$this->{'model_total_' . $result['code']}->getTotal($total_data, $total, $taxes);
				}
			}

			// Payment Methods
			$method_data = array();


				$this->load->model('extension/extension');
				$results = $this->model_extension_extension->getExtensions('payment');



            $cart_has_recurring = (method_exists($this->cart, 'hasRecurringProducts') && $this->cart->hasRecurringProducts());

			foreach ($results as $result) {
				if ($this->config->get($result['code'] . '_status')) {
					$this->load->model('payment/' . $result['code']);


				    $method = $this->{'model_payment_' . $result['code']}->getMethod($payment_address, $total);


					if ($method) {
                        if($cart_has_recurring > 0){
                            if (method_exists($this->{'model_payment_' . $result['code']},'recurringPayments')) {
                                if($this->{'model_payment_' . $result['code']}->recurringPayments() == true){
                                    $method_data[$result['code']] = $method;
                                }
                            }
                        } else {
                            $method_data[$result['code']] = $method;
                        }
					}
				}
			}

			$sort_order = array();

			foreach ($method_data as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}

			array_multisort($sort_order, SORT_ASC, $method_data);

			$this->session->data['payment_methods'] = $method_data;

		//}

		$data['text_payment_method'] = $this->language->get('text_payment_method');
		$data['text_comments'] = $this->language->get('text_comments');

		$data['button_continue'] = $this->language->get('button_continue');

		if (empty($this->session->data['payment_methods'])) {
			$data['error_warning'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact'));
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['payment_methods'])) {
			$data['payment_methods'] = $this->session->data['payment_methods'];
		} else {
			$data['payment_methods'] = array();
		}

		if (isset($this->session->data['payment_method']['code'])) {
			$data['code'] = $this->session->data['payment_method']['code'];
		} else {
			$data['code'] = '';
		}

		if (isset($this->session->data['comment'])) {
			$data['comment'] = $this->session->data['comment'];
		} else {
			$data['comment'] = '';
		}

		if ($this->config->get('config_checkout_id')) {
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

			if ($information_info) {

					$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_checkout_id'), 'SSL'), $information_info['title'], $information_info['title']);

			} else {
				$data['text_agree'] = '';
			}
		} else {
			$data['text_agree'] = '';
		}

		if (isset($this->session->data['agree'])) {
			$data['agree'] = $this->session->data['agree'];
		} else {
			$data['agree'] = '';
		}

		if ($render !== false)
		{

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/nico_payment_method.tpl'))
			{
				$template =  $this->template = $this->config->get('config_template') . '/template/checkout/nico_payment_method.tpl';
			} else {
				$template =  $this->template = 'default/template/checkout/nico_payment_method.tpl';
			}

			$this->response->setOutput($this->load->view($template, $data));

		}
  	}

	public function payment_address($render = true, &$data  = array()) {

		$this->load->language('checkout/nicocheckout');

		$data['text_address_existing'] = $this->language->get('text_address_existing');
		$data['text_address_new'] = $this->language->get('text_address_new');
		$data['text_select'] = $this->language->get('text_select');
		$data['text_none'] = $this->language->get('text_none');

		$data['entry_firstname'] = $this->language->get('entry_firstname');
		$data['entry_lastname'] = $this->language->get('entry_lastname');
		$data['entry_company'] = $this->language->get('entry_company');
		if ($this->language->get('entry_company_id') != 'entry_company_id') $data['entry_company_id'] = $this->language->get('entry_company_id');
		if ($this->language->get('entry_tax_id') != 'entry_tax_id') $data['entry_tax_id'] = $this->language->get('entry_tax_id');
		$data['entry_address_1'] = $this->language->get('entry_address_1');
		$data['entry_address_2'] = $this->language->get('entry_address_2');
		$data['entry_postcode'] = $this->language->get('entry_postcode');
		$data['entry_city'] = $this->language->get('entry_city');
		$data['entry_country'] = $this->language->get('entry_country');
		$data['entry_zone'] = $this->language->get('entry_zone');

		$data['button_continue'] = $this->language->get('button_continue');


			if (isset($this->session->data['payment_address']['address_id'])) {
				$data['payment_address_id'] = $this->session->data['payment_address']['address_id'];
			} else {
				$data['payment_address_id'] = $this->customer->getAddressId();
			}

		$data['addresses'] = array();

		$this->load->model('account/address');

		$data['addresses'] = $this->model_account_address->getAddresses();

		$this->load->model('account/customer_group');

			if (isset($this->session->data['payment_address']['country_id'])) {
				$data['country_id'] = $this->session->data['payment_address']['country_id'];
			} else {
				if ($this->config->get('config_tax_default') == 'payment')
				$data['country_id'] = $this->config->get('config_country_id');
				else $data['country_id'] = '';
			}

			if (isset($this->session->data['payment_address']['zone_id'])) {
				$data['zone_id'] = $this->session->data['payment_address']['zone_id'];
			} else {
				$data['zone_id'] = '';
			}

			$this->load->model('localisation/country');

			$data['countries'] = $this->model_localisation_country->getCountries();

			// Custom Fields
			$this->load->model('account/custom_field');

			$data['custom_fields'] = $this->model_account_custom_field->getCustomFields(array('filter_customer_group_id' => $this->config->get('config_customer_group_id')));

			if (isset($this->session->data['payment_address']['custom_field'])) {
				$data['payment_address_custom_field'] = $this->session->data['payment_address']['custom_field'];
			} else {
				$data['payment_address_custom_field'] = array();
			}

		if ($render !== false)
		{
			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/payment_address.tpl')) {
				$template =  $this->template = $this->config->get('config_template') . '/template/checkout/payment_address.tpl';
			} else {
				$template =  $this->template = 'default/template/checkout/payment_address.tpl';
			}


				$this->response->setOutput($this->load->view($template, $data));

		}
  	}
  	
	public function confirm($render = true, &$data = array()) {

		$redirect = '';
		//var_dump($this->session->data['shipping_method']);
		$data['payment'] = '';
		$data['products'] = '';

		$redirect = '';
		$points = $this->customer->getRewardPoints();


		$this->load->language('checkout/voucher');
		$data['voucher_title'] = $this->language->get('heading_title');
		$this->load->language('checkout/coupon');
		$data['coupon_title'] = $this->language->get('heading_title');
		$this->load->language('checkout/reward');
		$data['reward_title'] = sprintf($this->language->get('heading_title'), $points);
		$this->load->language('checkout/cart');
		$this->load->language('checkout/nicocheckout');



		$data['text_cart'] = $this->language->get('text_cart');
		$data['entry_coupon'] = $this->language->get('entry_coupon');
		$data['entry_voucher'] = $this->language->get('entry_voucher');
		$data['entry_reward'] =  sprintf($this->language->get('entry_reward'), $points);
		$data['entry_country'] = $this->language->get('entry_country');
		$data['entry_zone'] = $this->language->get('entry_zone');
		$data['entry_postcode'] = $this->language->get('entry_postcode');

		$data['button_update'] = $this->language->get('button_update');
		$data['button_remove'] = $this->language->get('button_remove');
		$data['button_coupon'] = $this->language->get('button_coupon');
		$data['button_voucher'] = $this->language->get('button_voucher');
		$data['button_reward'] = $this->language->get('button_reward');
		$data['button_quote'] = $this->language->get('button_quote');


		$data['coupon_status'] = $this->config->get('coupon_status');

		if (isset($this->request->post['coupon'])) {
			$data['coupon'] = $this->request->post['coupon'];
		} elseif (isset($this->session->data['coupon'])) {
			$data['coupon'] = $this->session->data['coupon'];
		} else {
			$data['coupon'] = '';
		}

		$data['voucher_status'] = $this->config->get('voucher_status');

		if (isset($this->request->post['voucher'])) {
			$data['voucher'] = $this->request->post['voucher'];
		} elseif (isset($this->session->data['voucher'])) {
			$data['voucher'] = $this->session->data['voucher'];
		} else {
			$data['voucher'] = '';
		}

		//$data['reward_status'] = ($points && $points_total && $this->config->get('reward_status'));

		if (isset($this->request->post['reward'])) {
			$data['reward'] = $this->request->post['reward'];
		} elseif (isset($this->session->data['reward'])) {
			$data['reward'] = $this->session->data['reward'];
		} else {
			$data['reward'] = '';
		}


		if ($this->cart->hasShipping()) {
			// Validate if shipping address has been set.
			if (!isset($this->session->data['shipping_address'])) {
				$redirect = $this->url->link('checkout/nicocheckout', '', 'SSL');
			}

			// Validate if shipping method has been set.
			if (!isset($this->session->data['shipping_method'])) {
				$redirect = $this->url->link('checkout/nicocheckout', '', 'SSL');
			}
		} else {
			//unset($this->session->data['shipping_address']);
			//unset($this->session->data['shipping_method']);
			//unset($this->session->data['shipping_methods']);
		}

		// Validate if payment address has been set.
		if (!isset($this->session->data['payment_address'])) {
			$redirect = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate if payment method has been set.
		if (!isset($this->session->data['payment_method'])) {
			$redirect = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$redirect = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$redirect = $this->url->link('checkout/cart');

				break;
			}
		}

		$order_data = array();

		$order_data['totals'] = array();
		$total = 0;
		$taxes = $this->cart->getTaxes();

		$this->load->model('extension/extension');

		$sort_order = array();

		$results = $this->model_extension_extension->getExtensions('total');

		foreach ($results as $key => $value) {
			$sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
		}

		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get($result['code'] . '_status')) {
				$this->load->model('total/' . $result['code']);

				$this->{'model_total_' . $result['code']}->getTotal($order_data['totals'], $total, $taxes);
			}
		}

		$sort_order = array();

		foreach ($order_data['totals'] as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $order_data['totals']);

		$order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
		$order_data['store_id'] = $this->config->get('config_store_id');
		$order_data['store_name'] = $this->config->get('config_name');

		if ($order_data['store_id']) {
			$order_data['store_url'] = $this->config->get('config_url');
		} else {
			$order_data['store_url'] = HTTP_SERVER;
		}

		if (isset($_POST) && !empty($_POST))
		{
			if ($this->customer->isLogged()) {
				$this->load->model('account/customer');

				$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

				$order_data['customer_id'] = $this->customer->getId();
				$order_data['customer_group_id'] = $customer_info['customer_group_id'];
				$order_data['firstname'] = $customer_info['firstname'];
				$order_data['lastname'] = $customer_info['lastname'];
				$order_data['email'] = $customer_info['email'];
				$order_data['telephone'] = $customer_info['telephone'];
				$order_data['fax'] = $customer_info['fax'];
				$order_data['custom_field'] = unserialize($customer_info['custom_field']);
			} elseif (isset($this->session->data['guest'])) {
				$order_data['customer_id'] = 0;
				$order_data['customer_group_id'] = isset($this->session->data['guest']['customer_group_id'])?$this->session->data['guest']['customer_group_id']:$this->config->get('config_customer_group_id');;
				$order_data['firstname'] = isset($this->request->post['shipping_firstname'])?$this->request->post['shipping_firstname']:'';
				$order_data['lastname'] = isset($this->request->post['shipping_lastname'])?$this->request->post['shipping_lastname']:'';
				$order_data['email'] = isset($this->request->post['email'])?$this->request->post['email']:'';
				$order_data['telephone'] = isset($this->request->post['shipping_telephone'])?$this->request->post['shipping_telephone']:'';
				$order_data['fax'] = isset($this->session->data['guest']['fax'])?$this->session->data['guest']['fax']:'';
				$order_data['custom_field'] = isset($this->session->data['guest']['custom_field'])?$this->session->data['guest']['custom_field']:'';
			}


			if (isset($this->session->data['payment_method']['title'])) {
				$order_data['payment_method'] = $this->session->data['payment_method']['title'];
			} else {
				$order_data['payment_method'] = '';
			}

			if (isset($this->session->data['payment_method']['code'])) {
				$order_data['payment_code'] = $this->session->data['payment_method']['code'];
			} else {
				$order_data['payment_code'] = '';
			}

			if ($this->cart->hasShipping()) {
				if (isset($this->request->post['shipping_firstname']) && !empty($this->request->post['shipping_firstname']))
				{
					$order_data['shipping_firstname'] = $this->request->post['shipping_firstname'];
					$order_data['shipping_lastname'] = $this->request->post['shipping_lastname'];
					$order_data['shipping_company'] = $this->request->post['shipping_company'];
					$order_data['shipping_address_1'] = $this->request->post['shipping_address_1'];
					$order_data['shipping_address_2'] = $this->request->post['shipping_address_2'];
					$order_data['shipping_city'] = $this->request->post['shipping_city'];
					$order_data['shipping_postcode'] = $this->request->post['shipping_postcode'];
					$order_data['shipping_zone'] = '';//$this->request->post['shipping_zone'];
					$order_data['shipping_zone_id'] = $this->request->post['shipping_zone_id'];
					$order_data['shipping_country'] = '';//$this->request->post['shipping_country'];
					$order_data['shipping_country_id'] = $this->request->post['shipping_country_id'];
					$order_data['shipping_address_format'] = '';//$this->request->post['shipping_address_format'];
					$order_data['shipping_method'] = $this->request->post['_shipping_method'];
					$order_data['shipping_code'] = $this->request->post['shipping_method'];
					$order_data['shipping_telephone'] = $this->request->post['shipping_telephone'];

					$order_data['telephone'] = $this->request->post['shipping_telephone'];
					$order_data['firstname'] = $this->request->post['shipping_firstname'];
					$order_data['lastname'] = $this->request->post['shipping_lastname'];

					$this->load->model('localisation/country');
					$this->load->model('localisation/zone');

					$order_data['shipping_country'] = '';
					$order_data['shipping_country_code'] = '';
					if (isset($this->request->post['shipping_country_id']) && $country_info = $this->model_localisation_country->getCountry($this->request->post['shipping_country_id']))
					{
						$order_data['shipping_country'] =  $country_info['name'];//$this->request->post['shipping_country'];
						$order_data['shipping_country_code'] =  $country_info['iso_code_2'];
					}

					$order_data['shipping_zone'] = '';
					$order_data['shipping_zone_code'] = '';
					if (isset($this->request->post['shipping_zone_id']) && $this->request->post['shipping_zone_id'])
					{
						$zone_info = $this->model_localisation_zone->getZone($this->request->post['shipping_zone_id']);
						if ($zone_info){
							$order_data['shipping_zone'] = $zone_info['name'];
							$order_data['shipping_zone_code'] = $zone_info['code'];
						};//$this->model_localisation_zone->getZone($this->request->post['shipping_zone_id']);
					}
				}
				 else if ($this->customer->isLogged() && (isset($this->request->post['shipping_address_id']) || isset($this->session->data['shipping_address_id'])))
				{
					$this->load->model('account/address');

					if (isset($this->request->post['shipping_address_id']))
					$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->request->post['shipping_address_id']);
					else if (!isset($this->session->data['shipping_address']))
					$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->session->data['shipping_address_id']);

					$order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
					$order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
					$order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
					$order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
					$order_data['shipping_address_2'] = (isset($this->session->data['shipping_address']['address_2']))?$this->session->data['shipping_address']['address_2']:'';
					$order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
					$order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
					$order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
					$order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
					$order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
					$order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
					$order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
					$order_data['shipping_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array());
					$order_data['shipping_country_code'] =  $this->session->data['shipping_address']['iso_code_2'];
					$order_data['shipping_zone_code'] =  $this->session->data['shipping_address']['zone_code'];
					$order_data['shipping_telephone'] =  $this->session->data['shipping_address']['telephone'];
					$order_data['telephone'] = $this->session->data['shipping_address']['shipping_telephone'];
					$order_data['firstname'] = $this->session->data['shipping_address']['shipping_firstname'];
					$order_data['lastname'] = $this->session->data['shipping_address']['shipping_lastname'];
				} else
				{
					$order_data['shipping_firstname'] = $this->request->post['shipping_firstname'];
					$order_data['shipping_lastname'] = $this->request->post['shipping_lastname'];
					$order_data['shipping_company'] = $this->request->post['shipping_company'];
					$order_data['shipping_address_1'] = $this->request->post['shipping_address_1'];
					$order_data['shipping_address_2'] = $this->request->post['shipping_address_2'];
					$order_data['shipping_city'] = $this->request->post['shipping_city'];
					$order_data['shipping_postcode'] = $this->request->post['shipping_postcode'];
					$order_data['shipping_zone'] = '';//$this->request->post['shipping_zone'];
					$order_data['shipping_zone_id'] = $this->request->post['shipping_zone_id'];
					$order_data['shipping_country'] = '';//$this->request->post['shipping_country'];
					$order_data['shipping_country_id'] = $this->request->post['shipping_country_id'];
					$order_data['shipping_address_format'] = '';//$this->request->post['shipping_address_format'];
					$order_data['shipping_method'] = $this->request->post['_shipping_method'];
					$order_data['shipping_code'] = $this->request->post['shipping_method'];
					$order_data['shipping_telephone'] = $this->request->post['shipping_telephone'];
					$order_data['telephone'] = $this->request->post['shipping_telephone'];
					$order_data['firstname'] = $this->request->post['shipping_firstname'];
					$order_data['lastname'] = $this->request->post['shipping_lastname'];


					$this->load->model('localisation/country');
					$this->load->model('localisation/zone');

					$order_data['shipping_country'] = '';
					$order_data['shipping_country_code'] = '';
					if (isset($this->request->post['shipping_country_id']) && $country_info = $this->model_localisation_country->getCountry($this->request->post['shipping_country_id']))
					{
						$order_data['shipping_country'] =  $country_info['name'];//$this->request->post['shipping_country'];
						$order_data['shipping_country_code'] =  $country_info['iso_code_2'];
					}

					$order_data['shipping_zone'] = '';
					$order_data['shipping_zone_code'] = '';
					if (isset($this->request->post['shipping_zone_id']) && $this->request->post['shipping_zone_id'])
					{
						$zone_info = $this->model_localisation_zone->getZone($this->request->post['shipping_zone_id']);
						if ($zone_info){
							$order_data['shipping_zone'] = $zone_info['name'];
							$order_data['shipping_zone_code'] = $zone_info['code'];
						};//$this->model_localisation_zone->getZone($this->request->post['shipping_zone_id']);
					}
				}

				if (isset($this->session->data['shipping_method']['title'])) {
					$order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
				} else {
					$order_data['shipping_method'] = '';
				}

				if (isset($this->session->data['shipping_method']['code'])) {
					$order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
				} else {
					$order_data['shipping_code'] = '';
				}
			} else {
				$order_data['shipping_firstname'] = '';
				$order_data['shipping_lastname'] = '';
				$order_data['shipping_company'] = '';
				$order_data['shipping_address_1'] = '';
				$order_data['shipping_address_2'] = '';
				$order_data['shipping_city'] = '';
				$order_data['shipping_postcode'] = '';
				$order_data['shipping_zone'] = '';
				$order_data['shipping_zone_id'] = '';
				$order_data['shipping_country'] = '';
				$order_data['shipping_country_id'] = '';
				$order_data['shipping_address_format'] = '';
				$order_data['shipping_custom_field'] = array();
				$order_data['shipping_method'] = '';
				$order_data['shipping_code'] = '';
				$order_data['shipping_country_code'] = '';
				$order_data['shipping_zone_code'] = '';
				$order_data['shipping_telephone'] = '';

			}

			$order_data['products'] = array();

			foreach ($this->cart->getProducts() as $product) {
				$option_data = array();

				foreach ($product['option'] as $option) {
					$option_data[] = array(
						'product_option_id'       => $option['product_option_id'],
						'product_option_value_id' => $option['product_option_value_id'],
						'option_id'               => $option['option_id'],
						'option_value_id'         => $option['option_value_id'],
						'name'                    => $option['name'],
						'value'                   => $option['value'],
						'type'                    => $option['type']
					);
				}

				$order_data['products'][] = array(
					'product_id' => $product['product_id'],
					'name'       => $product['name'],
					'model'      => $product['model'],
					'option'     => $option_data,
					'download'   => $product['download'],
					'quantity'   => $product['quantity'],
					'subtract'   => $product['subtract'],
					'price'      => $product['price'],
					'total'      => $product['total'],
					'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
					'reward'     => $product['reward']
				);
			}

			// Gift Voucher
			$order_data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $voucher) {
					$order_data['vouchers'][] = array(
						'description'      => $voucher['description'],
						'code'             => substr(md5(mt_rand()), 0, 10),
						'to_name'          => $voucher['to_name'],
						'to_email'         => $voucher['to_email'],
						'from_name'        => $voucher['from_name'],
						'from_email'       => $voucher['from_email'],
						'voucher_theme_id' => $voucher['voucher_theme_id'],
						'message'          => $voucher['message'],
						'amount'           => $voucher['amount']
					);
				}
			}

			$order_data['comment'] = $this->session->data['comment'];
			$order_data['total'] = $total;

			if (isset($this->request->cookie['tracking'])) {
				$order_data['tracking'] = $this->request->cookie['tracking'];

				$subtotal = $this->cart->getSubTotal();

				// Affiliate
				$this->load->model('affiliate/affiliate');

				$affiliate_info = $this->model_affiliate_affiliate->getAffiliateByCode($this->request->cookie['tracking']);

				if ($affiliate_info) {
					$order_data['affiliate_id'] = $affiliate_info['affiliate_id'];
					$order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
				} else {
					$order_data['affiliate_id'] = 0;
					$order_data['commission'] = 0;
				}

				// Marketing
				$this->load->model('checkout/marketing');

				$marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

				if ($marketing_info) {
					$order_data['marketing_id'] = $marketing_info['marketing_id'];
				} else {
					$order_data['marketing_id'] = 0;
				}
			} else {
				$order_data['affiliate_id'] = 0;
				$order_data['commission'] = 0;
				$order_data['marketing_id'] = 0;
				$order_data['tracking'] = '';
			}

			$order_data['language_id'] = $this->config->get('config_language_id');
			$order_data['currency_id'] = $this->currency->getId();
			$order_data['currency_code'] = $this->currency->getCode();
			$order_data['currency_value'] = $this->currency->getValue($this->currency->getCode());
			$order_data['ip'] = $this->request->server['REMOTE_ADDR'];

			if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
				$order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
			} elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
				$order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
			} else {
				$order_data['forwarded_ip'] = '';
			}

			if (isset($this->request->server['HTTP_USER_AGENT'])) {
				$order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
			} else {
				$order_data['user_agent'] = '';
			}

			if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
				$order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
			} else {
				$order_data['accept_language'] = '';
			}

			$this->load->model('checkout/order');
			$this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);

			$data['text_recurring_item'] = $this->language->get('text_recurring_item');
			$data['text_payment_recurring'] = $this->language->get('text_payment_recurring');
		}
		$data['column_name'] = $this->language->get('column_name');
		$data['column_model'] = $this->language->get('column_model');
		$data['column_quantity'] = $this->language->get('column_quantity');
		$data['column_price'] = $this->language->get('column_price');
		$data['column_total'] = $this->language->get('column_total');

		$this->load->model('tool/upload');

		$data['products'] = array();

		foreach ($this->cart->getProducts() as $product) {
			$option_data = array();

			foreach ($product['option'] as $option) {
				if ($option['type'] != 'file') {
					$value = $option['value'];
				} else {
					$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

					if ($upload_info) {
						$value = $upload_info['name'];
					} else {
						$value = '';
					}
				}

				$option_data[] = array(
					'name'  => $option['name'],
					'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
				);
			}

			$recurring = '';

			if ($product['recurring']) {
				$frequencies = array(
					'day'        => $this->language->get('text_day'),
					'week'       => $this->language->get('text_week'),
					'semi_month' => $this->language->get('text_semi_month'),
					'month'      => $this->language->get('text_month'),
					'year'       => $this->language->get('text_year'),
				);

				if ($product['recurring']['trial']) {
					$recurring = sprintf($this->language->get('text_trial_description'), $this->currency->format($this->tax->calculate($product['recurring']['trial_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax'))), $product['recurring']['trial_cycle'], $frequencies[$product['recurring']['trial_frequency']], $product['recurring']['trial_duration']) . ' ';
				}

				if ($product['recurring']['duration']) {
					$recurring .= sprintf($this->language->get('text_payment_description'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax'))), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
				} else {
					$recurring .= sprintf($this->language->get('text_payment_cancel'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax'))), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
				}
			}

			$data['products'][] = array(
				'key'        => $product['key'],
				'product_id' => $product['product_id'],
				'name'       => $product['name'],
				'model'      => $product['model'],
				'option'     => $option_data,
				'recurring'  => $recurring,
				'quantity'   => $product['quantity'],
				'subtract'   => $product['subtract'],
				'price'      => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'))),
				'total'      => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity']),
				'href'       => $this->url->link('product/product', 'product_id=' . $product['product_id']),
			);
		}

		// Gift Voucher
		$data['vouchers'] = array();

		if (!empty($this->session->data['vouchers'])) {
			foreach ($this->session->data['vouchers'] as $voucher) {
				$data['vouchers'][] = array(
					'description' => $voucher['description'],
					'amount'      => $this->currency->format($voucher['amount'])
				);
			}
		}

		$data['totals'] = array();

		foreach ($order_data['totals'] as $total) {
			$data['totals'][] = array(
				'title' => $total['title'],
				'text'  => $this->currency->format($total['value']),
			);
		}
		if ($render !== false)
		{
			//$this->response->redirect($this->url->link('payment/' . $this->session->data['payment_method']['code']));
			//echo $data['payment'] = $this->load->controller('payment/' . $this->session->data['payment_method']['code']);
			$json['redirect'] = $this->url->link('payment/' . $this->session->data['payment_method']['code'], '', 'SSL');


			$this->response->setOutput(json_encode($json));

		}

  	}
  	
	public function payment_address_validate(&$data = array())
	{

		$this->load->language('checkout/nicocheckout');

		$json = array();

		// Validate if customer is logged in.
		if (!$this->customer->isLogged()) {
			//$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
//			$json['redirect'] = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirments.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = $this->url->link('checkout/cart');

				break;
			}
		}


				if (!$json)
				{
					if (isset($this->request->post['payment_address']) && $this->request->post['payment_address'] == 'existing') {
						$this->load->model('account/address');

						if (empty($this->request->post['payment_address_id'])) {
							$json['error']['warning'] = $this->language->get('error_address');
						} elseif (!in_array($this->request->post['payment_address_id'], array_keys($this->model_account_address->getAddresses()))) {
							$json['error']['warning'] = $this->language->get('error_address');
						}

						if (!$json) {
							// Default Payment Address
							$this->load->model('account/address');

							$this->session->data['payment_address'] = $this->model_account_address->getAddress($this->request->post['payment_address_id']);

							//unset($this->session->data['payment_method']);
							//unset($this->session->data['payment_methods']);
						}
					} else {
						if (!isset($this->request->post['firstname']) || ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 32))) {
							$json['error']['firstname'] = $this->language->get('error_firstname');
						}

						if (!isset($this->request->post['lastname']) || ( (utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 32))) {
							$json['error']['lastname'] = $this->language->get('error_lastname');
						}

						if (!isset($this->request->post['address_1']) || ( (utf8_strlen(trim($this->request->post['address_1'])) < 3) || (utf8_strlen(trim($this->request->post['address_1'])) > 128))) {
							$json['error']['address_1'] = $this->language->get('error_address_1');
						}

						if (isset($this->request->post['city']) && ( (utf8_strlen($this->request->post['city']) < 2) || (utf8_strlen($this->request->post['city']) > 32))) {
							$json['error']['city'] = $this->language->get('error_city');
						}

						$this->load->model('localisation/country');

						if (isset($this->request->post['country_id'])) $country_info = $this->model_localisation_country->getCountry($this->request->post['country_id']);

						if (isset($country_info) && $country_info['postcode_required'] && (utf8_strlen(trim($this->request->post['postcode'])) < 2 || utf8_strlen(trim($this->request->post['postcode'])) > 10)) {
							$json['error']['postcode'] = $this->language->get('error_postcode');
						}

						if (isset($this->request->post['country_id']) && empty($this->request->post['country_id'])) {
							$json['error']['country_id'] = $this->language->get('error_country');
						}

						if (isset($this->request->post['zone_id']) && ($this->request->post['zone_id'] === '' || $this->request->post['zone_id'] < 0)) {
							$json['error']['zone_id'] = $this->language->get('error_zone');
						}

						// Custom field validation
						$this->load->model('account/custom_field');

						$custom_fields = $this->model_account_custom_field->getCustomFields(array('filter_customer_group_id' => $this->config->get('config_customer_group_id')));

						foreach ($custom_fields as $custom_field) {
							if (($custom_field['location'] == 'address') && $custom_field['required'] && empty($this->request->post['custom_field'][$custom_field['custom_field_id']])) {
								$json['error']['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
							}
						}

						if (!$json) {
							// Default Payment Address
							$this->load->model('account/address');

							$this->request->post = array_merge(array('address_2' => '', 'country_id' => '','zone_id' => '','city' => '','postcode' => ''),$this->request->post);//avoid notices for empty fields

							$address_id = $this->model_account_address->addAddress($this->request->post);

							$this->session->data['payment_address'] = $this->model_account_address->getAddress($address_id);

							//unset($this->session->data['payment_method']);
							//unset($this->session->data['payment_methods']);
							/*
							$activity_data = array(
								'customer_id' => $this->customer->getId(),
								'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName()
							);

							$this->model_account_activity->addActivity('address_add', $activity_data);					*/
						}
					}
				}

		return $json;
	}
	  	
	public function country($data = array()) {
		$json = array();

		$this->load->model('localisation/country');

    	$country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

		if ($country_info) {
			$this->load->model('localisation/zone');

			$json = array(
				'country_id'        => $country_info['country_id'],
				'name'              => $country_info['name'],
				'iso_code_2'        => $country_info['iso_code_2'],
				'iso_code_3'        => $country_info['iso_code_3'],
				'address_format'    => $country_info['address_format'],
				'postcode_required' => $country_info['postcode_required'],
				'zone'              => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
				'status'            => $country_info['status']
			);
		}

		$this->response->setOutput(json_encode($json));
	}
	
	public function login_validate($data = array()) {

		 $this->load->language('checkout/nicocheckout');

		$json = array();

		if ($this->customer->isLogged()) {
			$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!$json) {
			if (!$this->customer->login($this->request->post['email'], $this->request->post['password'])) {
				$json['error']['warning'] = $this->language->get('error_login');
			}

			$this->load->model('account/customer');

			$customer_info = $this->model_account_customer->getCustomerByEmail($this->request->post['email']);

			if ($customer_info && !$customer_info['approved']) {
				$json['error']['warning'] = $this->language->get('error_approved');
			}
		}

		if (!$json) {
			unset($this->session->data['guest']);

			// Default Addresses
			$this->load->model('account/address');

			$address_info = $this->model_account_address->getAddress($this->customer->getAddressId());

			if ($address_info)
			{

					if ($this->config->get('config_tax_customer') == 'payment') {
						$this->session->data['payment_addess'] = $this->model_account_address->getAddress($this->customer->getAddressId());
					}

					if ($this->config->get('config_tax_customer') == 'shipping') {
						$this->session->data['shipping_addess'] = $this->model_account_address->getAddress($this->customer->getAddressId());
					}

			} else {
				unset($this->session->data['shipping_country_id']);
				unset($this->session->data['shipping_zone_id']);
				unset($this->session->data['shipping_postcode']);
				unset($this->session->data['payment_country_id']);
				unset($this->session->data['payment_zone_id']);
			}

			$json['redirect'] = $this->url->link('checkout/nicocheckout', '', 'SSL');
		}

		$this->response->setOutput(json_encode($json));
	}
	
	public function cart($render = true, &$data = array())
	{

		$this->shipping_method_validate();

		$this->load->language('checkout/cart');

		if (!isset($this->session->data['vouchers'])) {
			$this->session->data['vouchers'] = array();
		}


      	$data['breadcrumbs'] = array();

      	$data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('common/home'),
        	'text'      => $this->language->get('text_home'),
        	'separator' => false
      	);

      	$data['breadcrumbs'][] = array(
        	'href'      => $this->url->link('checkout/cart'),
        	'text'      => $this->language->get('heading_title'),
        	'separator' => $this->language->get('text_separator')
      	);

			$points = $this->customer->getRewardPoints();

			$points_total = 0;

			foreach ($this->cart->getProducts() as $product) {
				if ($product['points']) {
					$points_total += $product['points'];
				}
			}

      		$data['heading_title'] = $this->language->get('heading_title');

			$data['text_next'] = $this->language->get('text_next');
			$data['text_next_choice'] = $this->language->get('text_next_choice');
     		$data['text_use_coupon'] = $this->language->get('text_use_coupon');
			$data['text_use_voucher'] = $this->language->get('text_use_voucher');
			$data['text_use_reward'] = sprintf($this->language->get('text_use_reward'), $points);
			$data['text_shipping_estimate'] = $this->language->get('text_shipping_estimate');
			$data['text_shipping_detail'] = $this->language->get('text_shipping_detail');
			$data['text_shipping_method'] = $this->language->get('text_shipping_method');
			$data['text_select'] = $this->language->get('text_select');
			$data['text_none'] = $this->language->get('text_none');
			$data['text_until_cancelled'] = $this->language->get('text_until_cancelled');
			$data['text_freq_day'] = $this->language->get('text_freq_day');
			$data['text_freq_week'] = $this->language->get('text_freq_week');
			$data['text_freq_month'] = $this->language->get('text_freq_month');
			$data['text_freq_bi_month'] = $this->language->get('text_freq_bi_month');
			$data['text_freq_year'] = $this->language->get('text_freq_year');

			$data['column_image'] = $this->language->get('column_image');
      		$data['column_name'] = $this->language->get('column_name');
      		$data['column_model'] = $this->language->get('column_model');
      		$data['column_quantity'] = $this->language->get('column_quantity');
			$data['column_price'] = $this->language->get('column_price');
      		$data['column_total'] = $this->language->get('column_total');

			$data['entry_coupon'] = $this->language->get('entry_coupon');
			$data['entry_voucher'] = $this->language->get('entry_voucher');
			$data['entry_reward'] = sprintf($this->language->get('entry_reward'), $points_total);
			$data['entry_country'] = $this->language->get('entry_country');
			$data['entry_zone'] = $this->language->get('entry_zone');
			$data['entry_postcode'] = $this->language->get('entry_postcode');

			$data['button_update'] = $this->language->get('button_update');
			$data['button_remove'] = $this->language->get('button_remove');
			$data['button_coupon'] = $this->language->get('button_coupon');
			$data['button_voucher'] = $this->language->get('button_voucher');
			$data['button_reward'] = $this->language->get('button_reward');
			$data['button_quote'] = $this->language->get('button_quote');
			$data['button_shipping'] = $this->language->get('button_shipping');
      		$data['button_shopping'] = $this->language->get('button_shopping');
      		$data['button_checkout'] = $this->language->get('button_checkout');

      		$data['text_trial'] = $this->language->get('text_trial');
      		$data['text_recurring'] = $this->language->get('text_recurring');
      		$data['text_length'] = $this->language->get('text_length');
      		$data['text_recurring_item'] = $this->language->get('text_recurring_item');
      		$data['text_payment_profile'] = $this->language->get('text_payment_profile');
			$data['text_cart'] = $this->language->get('text_cart');

			if (isset($this->error['warning'])) {
				$data['error_warning'] = $this->error['warning'];
			} elseif (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
      			$data['error_warning'] = $this->language->get('error_stock');
			} else {
				$data['error_warning'] = '';
			}

			if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
				$data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
			} else {
				$data['attention'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];

				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}

			$data['action'] = $this->url->link('checkout/cart');


			if (isset($this->request->post['zone_id'])) {
				$data['zone_id'] = $this->request->post['zone_id'];
			} elseif (isset($this->session->data['shipping_zone_id'])) {
				$data['zone_id'] = $this->session->data['shipping_zone_id'];
			} else {
				$data['zone_id'] = '';
			}

			if (isset($this->request->post['postcode'])) {
				$data['postcode'] = $this->request->post['postcode'];
			} elseif (isset($this->session->data['shipping_postcode'])) {
				$data['postcode'] = $this->session->data['shipping_postcode'];
			} else {
				$data['postcode'] = '';
			}

			if (isset($this->request->post['shipping_method'])) {
				$data['shipping_method'] = $this->request->post['shipping_method'];
			} else
			if (isset($this->session->data['shipping_method'])) {
				$data['shipping_method'] = $this->session->data['shipping_method']['code'];
			} else {
				$data['shipping_method'] = '';
			}

			if ($this->config->get('config_cart_weight')) {
				$data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
			} else {
				$data['weight'] = '';
			}

			$this->load->model('tool/image');

            $data['products'] = array();

            $products = $this->cart->getProducts();

            foreach ($products as $product) {
                $product_total = 0;

                foreach ($products as $product_2) {
                    if ($product_2['product_id'] == $product['product_id']) {
                        $product_total += $product_2['quantity'];
                    }
                }

                if ($product['minimum'] > $product_total) {
                    $data['error_warning'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);
                }

                if ($product['image']) {
                    $image = $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height'));
                } else {
                    $image = '';
                }

                $option_data = array();

                foreach ($product['option'] as $option) {
                    if ($option['type'] != 'file') {
						if (isset($option['option_value']))
						{
							$value = $option['option_value'];
						} else if (isset($option['value']))
						{
							$value = $option['value'];
						} else
						{
							$value = '';
						}
                    } else {
                        $filename = $this->encryption->decrypt(isset($option['option_value'])?$option['option_value']:isset($option['value'])?$option['value']:'');

                        $value = utf8_substr($filename, 0, utf8_strrpos($filename, '.'));
                    }

                    $option_data[] = array(
                        'name'  => $option['name'],
                        'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
                    );
                }

                // Display prices
                if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
                    $price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')));
                } else {
                    $price = false;
                }

                // Display prices
                if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
                    $total = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity']);
                } else {
                    $total = false;
                }

                $profile_description = '';

                if (isset($product['recurring']) && $product['recurring']) {
                    $frequencies = array(
                        'day' => $this->language->get('text_day'),
                        'week' => $this->language->get('text_week'),
                        'semi_month' => $this->language->get('text_semi_month'),
                        'month' => $this->language->get('text_month'),
                        'year' => $this->language->get('text_year'),
                    );

                    if (isset($product['recurring_trial']) && $product['recurring_trial']) {
                        $recurring_price = $this->currency->format($this->tax->calculate($product['recurring_trial_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')));
                        $profile_description = sprintf($this->language->get('text_trial_description'), $recurring_price, $product['recurring_trial_cycle'], $frequencies[$product['recurring_trial_frequency']], $product['recurring_trial_duration']) . ' ';
                    }

                    $recurring_price = $this->currency->format($this->tax->calculate($product['recurring_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')));

                    if ($product['recurring_duration']) {
                        $profile_description .= sprintf($this->language->get('text_payment_description'), $recurring_price, $product['recurring_cycle'], $frequencies[$product['recurring_frequency']], $product['recurring_duration']);
                    } else {
                        $profile_description .= sprintf($this->language->get('text_payment_until_canceled_description'), $recurring_price, $product['recurring_cycle'], $frequencies[$product['recurring_frequency']], $product['recurring_duration']);
                    }
                }

                $data['products'][] = array(
                    'key'                 => $product['key'],
                    'thumb'               => $image,
                    'name'                => $product['name'],
                    'model'               => $product['model'],
                    'option'              => $option_data,
                    'quantity'            => $product['quantity'],
                    'stock'               => $product['stock'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
                    'reward'              => ($product['reward'] ? sprintf($this->language->get('text_points'), $product['reward']) : ''),
                    'price'               => $price,
                    'total'               => $total,
                    'href'                => $this->url->link('product/product', 'product_id=' . $product['product_id']),
                    'remove'              => $this->url->link('checkout/cart', 'remove=' . $product['key']),
                    'recurring'           => isset($product['recurring'])?$product['recurring']:'',
                    'profile_name'        => isset($product['profile_name'])?$product['profile_name']:'',
                    'profile_description' => $profile_description,
                );
            }


            $data['products_recurring'] = array();

			// Gift Voucher
			$data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $key => $voucher) {
					$data['vouchers'][] = array(
						'key'         => $key,
						'description' => $voucher['description'],
						'amount'      => $this->currency->format($voucher['amount']),
						'remove'      => $this->url->link('checkout/cart', 'remove=' . $key)
					);
				}
			}

			if (isset($this->request->post['next'])) {
				$data['next'] = $this->request->post['next'];
			} else {
				$data['next'] = '';
			}

			$data['coupon_status'] = $this->config->get('coupon_status');

			if (isset($this->request->post['coupon'])) {
				$data['coupon'] = $this->request->post['coupon'];
			} elseif (isset($this->session->data['coupon'])) {
				$data['coupon'] = $this->session->data['coupon'];
			} else {
				$data['coupon'] = '';
			}

			$data['voucher_status'] = $this->config->get('voucher_status');

			if (isset($this->request->post['voucher'])) {
				$data['voucher'] = $this->request->post['voucher'];
			} elseif (isset($this->session->data['voucher'])) {
				$data['voucher'] = $this->session->data['voucher'];
			} else {
				$data['voucher'] = '';
			}

			$data['reward_status'] = ($points && $points_total && $this->config->get('reward_status'));

			if (isset($this->request->post['reward'])) {
				$data['reward'] = $this->request->post['reward'];
			} elseif (isset($this->session->data['reward'])) {
				$data['reward'] = $this->session->data['reward'];
			} else {
				$data['reward'] = '';
			}

			$data['shipping_status'] = $this->config->get('shipping_status') && $this->config->get('shipping_estimator') && $this->cart->hasShipping();

			if (isset($this->request->post['country_id']) && $this->request->post['country_id']) {
				$data['country_id'] = $this->request->post['country_id'];
			} elseif (isset($this->session->data['shipping_country_id']) && $this->session->data['shipping_country_id']) {
				$data['country_id'] = $this->session->data['shipping_country_id'];
			} else {
				if ($this->config->get('config_tax_default') == 'payment' || $this->config->get('config_tax_default') == 'shipping')
				$data['country_id'] = $this->config->get('config_country_id');
				else $data['country_id'] = '';
			}

			$this->load->model('localisation/country');

			$data['countries'] = $this->model_localisation_country->getCountries();

			// Totals

			$this->load->model('extension/extension');


			$total_data = array();
			$total = 0;
			$taxes = $this->cart->getTaxes();


				$this->load->model('extension/extension');
				$results = $this->model_extension_extension->getExtensions('total');

			$sort_order = array();

			if ($results)
			{
				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get($value['code'] . '_sort_order');
				}

				array_multisort($sort_order, SORT_ASC, $results);

				foreach ($results as $result) {
					if ($this->config->get($result['code'] . '_status')) {
						$this->load->model('total/' . $result['code']);
						$this->{'model_total_' . $result['code']}->getTotal($data['totals'], $total, $taxes);


	//										if ($result['code'] == 'shipping')

					}
				}

				$sort_order = array();
				foreach ($data['totals'] as $key => &$value) {
					if (!isset($value['text'])) $value['text']  = $this->currency->format($value['value']);
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $data['totals']);
			}

			$data['continue'] = $this->url->link('common/home');

			$data['checkout'] = $this->url->link('checkout/nicocheckout', '', 'SSL');


			$this->load->model('extension/extension');


            $data['checkout_buttons'] = array();


			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/checkout/nico_cart.tpl')) {
				$template =  $this->template = $this->config->get('config_template') . '/template/checkout/nico_cart.tpl';
			} else {
				$template =  $this->template = 'default/template/checkout/nico_cart.tpl';
			}




				$this->response->setOutput($this->load->view($template, $data));

	}
  	
	public function voucher() 
	{

		

		$this->load->language('checkout/voucher');
		$error_empty = $this->language->get('error_empty');
		$error_voucher = $this->language->get('error_voucher');
		$text_success = $this->language->get('text_success');


		$json = array();

		$this->load->model('checkout/voucher');

		if (isset($this->request->post['voucher'])) {
			$voucher = $this->request->post['voucher'];
		} else {
			$voucher = '';
		}

		$voucher_info = $this->model_checkout_voucher->getVoucher($voucher);

		if (empty($this->request->post['voucher'])) {
			$json['error'] = $error_empty;
		} elseif ($voucher_info) {
			$this->session->data['voucher'] = $this->request->post['voucher'];

			$this->session->data['success'] = $text_success;

			$json['redirect'] = $this->url->link('checkout/cart');
		} else {
			$json['error'] = $error_voucher;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	  	
	public function coupon() 
	{

		

		$this->load->language('checkout/coupon');
		$error_empty = $this->language->get('error_empty');
		$error_coupon = $this->language->get('error_coupon');
		$text_success = $this->language->get('text_success');


		$json = array();

		$this->load->model('checkout/coupon');

		if (isset($this->request->post['coupon'])) {
			$coupon = $this->request->post['coupon'];
		} else {
			$coupon = '';
		}

		$coupon_info = $this->model_checkout_coupon->getCoupon($coupon);

		if (empty($this->request->post['coupon'])) {
			$json['error'] = $error_empty;
			
			unset($this->session->data['coupon']);
		} elseif ($coupon_info) {
			$this->session->data['coupon'] = $this->request->post['coupon'];

			$this->session->data['success'] = $text_success;

			$json['redirect'] = $this->url->link('checkout/cart');
		} else {
			$json['error'] = $error_coupon;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	public function reward() 
	{

		

		$this->load->language('checkout/reward');
		$error_empty = $this->language->get('error_empty');
		$error_reward = $this->language->get('error_reward');
		$error_points = $error_empty = $this->language->get('error_points');
		$error_maximum = $error_empty = $this->language->get('error_maximum');
		$text_success = $this->language->get('text_success');


		$json = array();

		$points = $this->customer->getRewardPoints();

		$points_total = 0;

		foreach ($this->cart->getProducts() as $product) {
			if ($product['points']) {
				$points_total += $product['points'];
			}
		}

		if (empty($this->request->post['reward'])) {
			$json['error'] = $error_reward;
		}

		if ($this->request->post['reward'] > $points) {
			$json['error'] = sprintf($error_points, $this->request->post['reward']);
		}

		if ($this->request->post['reward'] > $points_total) {
			$json['error'] = sprintf($error_maximum, $points_total);
		}

		if (!$json) {
			$this->session->data['reward'] = abs($this->request->post['reward']);

			$this->session->data['success'] = $text_success;

			if (isset($this->request->post['redirect'])) {
				$json['redirect'] = $this->url->link($this->request->post['redirect']);
			} else {
				$json['redirect'] = $this->url->link('checkout/cart');	
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

    public function place_order(){
		$data = array();
		$json = array();
		if (!$this->customer->isLogged()) {
			if (isset($_REQUEST['register']) && !empty($_REQUEST['register'])) {
				$json = $this->register_validate($data);
			} else {
				if (!isset($this->session->data['customer_id'])) $json = $this->guest_validate($data);
			}
		}
		//if (!isset($json['error']) /*&& !$this->customer->isLogged()*/) $json = array_merge($json, $this->payment_address_validate());
		if (!isset($json['error'])) $json = array_merge($json, $this->shipping_address_validate());
		if (!isset($json['error'])) $json = array_merge($json, $this->shipping_method_validate());
		if (!isset($json['error'])) $json = array_merge($json, $this->payment_method_validate());
		if($json['error']){
			$this->response->setOutput(json_encode($json));
		}else{
			$this->confirm();
		}
	}

}
