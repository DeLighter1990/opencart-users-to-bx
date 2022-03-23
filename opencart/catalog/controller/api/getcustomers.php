<?php
// Скрипт выводит данные всех покупателей (для переноса на Битрикс)

class ControllerApiGetcustomers extends Controller {
	public function index() {
		$query = $this->db->query("SELECT c.*, a.address_1, a.address_2, a.city, a.postcode, a.country_id, z.name as region FROM " . DB_PREFIX . "customer as c LEFT JOIN " . DB_PREFIX . "address as a ON c.address_id = a.address_id AND c.customer_id = a.customer_id AND c.status > 0 JOIN " . DB_PREFIX . "zone as z ON z.zone_id = a.zone_id");

		$customers = array();
		foreach ($query->rows as $customer) {
            $ordersSumQuery = $this->db->query("SELECT SUM(o.total) as total FROM `" . DB_PREFIX . "order` o LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id) WHERE o.customer_id = '" . (int)$customer["customer_id"] . "' AND o.order_status_id = '20' AND os.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY o.order_id DESC");
            $customer["orders_total"] = $ordersSumQuery->row['total'];
            $customers[] = $customer;
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($customers));
	}
}