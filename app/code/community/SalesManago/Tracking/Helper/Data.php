<?php
class SalesManago_Tracking_Helper_Data extends Mage_Core_Helper_Abstract{

	public function setSalesmanagoCustomerSyncStatus($data = array()){
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $customersyncModel = Mage::getModel('tracking/customersync');
            $dateTime = new DateTime('NOW');

            $insert_data = array(
                'email' => $data['customerEmail'],
                'hash' => sha1($data['customerEmail']),
                'status' => $data['status'],
                'action' => $data['action'],
                'counter' => 1,
                'created_time' => $dateTime->format('c')
            );
            if (isset($data['entity_id']) && !empty($data['entity_id'])) {
                $insert_data['customer_id'] = $data['entity_id'];
            }

            if (isset($data['order_id']) && !empty($data['order_id'])) {
                $insert_data['order_id'] = $data['order_id'];
            }

            $customersyncModel->setData($insert_data);

            try {
                $customersyncModel->save();
            } catch (Exception $e) {
                Mage::log($e->getMessage());
            }
        }
	}
	/*
	* BK Changes to Sales Manago Module.
	* Redeem Coupon via API to SalesManago
	* @author: Carlos Alonso de Linaje Garcia
	*/
	public function salesmanagoRedeemCoupon($customerEmail,$couponCode)
	{
	    $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
	    if ($active == 1)
	    {
	        $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
	        $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
	        $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');
	        $apiKey = md5(time() . $apiSecret);
            $data_to_json = array(
                'apiKey' => $apiKey,
                'clientId' => $clientId,
                'email' => $customerEmail,
                'coupon' => $couponCode,
            );
            $json = json_encode($data_to_json);
            $result = $this->_doPostRequest('https://' . $endPoint . '/api/contact/useContactCoupon', $json);
            $r = json_decode($result, true);

            return $r;
	    }
	    return false;
	}
	/*
	* BK Changes to Sales Manago Module.
	* Calc Total Sales of Customer
	* @author: Carlos Alonso de Linaje Garcia
	*/
	protected function _calcTotal($fromDate = null,$toDate,$customerEmail)
	{
	    $orders = Mage::getModel('sales/order')->getCollection()
                ->addAttributeToFilter('status', array('neq' => Mage_Sales_Model_Order::STATE_CANCELED))
                ->addAttributeToFilter('customer_email', $customerEmail);
        if ($fromDate != null)
            $orders->addAttributeToFilter('created_at', array('from'=>$fromDate, 'to'=>$toDate));
        else
            $orders->addAttributeToFilter('created_at', array('to'=>$toDate));

        $totalSales = 0;
        foreach ($orders->getData() as $data)
        {
            $totalSales += $data['grand_total'];
        }
        return $totalSales;
	}
	/*
	* BK Changes to Sales Manago Module.
	* Send Sales Totals via API to SalesManago
	* @author: Carlos Alonso de Linaje Garcia
	*/
    public function salesmanagoSalesSync ($orderDetails)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {

            $customerEmail = $orderDetails->getCustomerEmail();
            $month = date("m", Mage::getModel('core/date')->timestamp(time()));
            $year = date("Y", Mage::getModel('core/date')->timestamp(time()));
            $toDate = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));

            //Getting Sales of the month
            $fromDate = date('Y-m-1 H:i:s', Mage::getModel('core/date')->timestamp(time()));
            $totalSales_month = $this->_calcTotal($fromDate,$toDate,$customerEmail);
            $JanuarySales = $this->_calcTotal(date($year.'-1-1'),$toDate,$customerEmail);
            $total_sales =$this->_calcTotal(null,$toDate,$customerEmail);

            //Getting Sales of the Q, T and Semester.
            switch ($month) {
                case 1:
                case 2:
                case 3:
                    $data_to_json = array(
                        'Latest_Purchase_date_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $toDate,
                        'Total_Sales_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $total_sales,
                        'Sales_'.$year.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $JanuarySales,
                        'Sales_'.$year.'_M'.$month.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $totalSales_month,
                        'Sales_'.$year.'_1Q_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                        'Sales_'.$year.'_1FMP_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                        'Sales_'.$year.'_1S_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                    );
                    break;
                case 4:
                    $AprilSales = $this->_calcTotal(date($year.'-4-1'),$toDate,$customerEmail);
                    $data_to_json = array(
                        'Latest_Purchase_date_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $toDate,
                        'Total_Sales_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $total_sales,
                        'Sales_'.$year.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                        'Sales_'.$year.'_M'.$month.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $totalSales_month,
                        'Sales_'.$year.'_2Q_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $AprilSales,
                        'Sales_'.$year.'_1FMP_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                        'Sales_'.$year.'_1S_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                    );
                    break;
                case 5:
                case 6:
                    $AprilSales = $this->_calcTotal(date($year.'-4-1'),$toDate,$customerEmail);
                    $MaySales = $this->_calcTotal(date($year.'-5-1'),$toDate,$customerEmail);
                    $data_to_json = array(
                        'Latest_Purchase_date_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $toDate,
                        'Total_Sales_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $total_sales,
                        'Sales_'.$year.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                        'Sales_'.$year.'_M'.$month.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $totalSales_month,
                        'Sales_'.$year.'_2Q_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $AprilSales,
                        'Sales_'.$year.'_2FMP_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $MaySales,
                        'Sales_'.$year.'_1S_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                    );
                    break;
                case 7:
                case 8:
                    $JulySales = $this->_calcTotal(date($year.'-7-1'),$toDate,$customerEmail);
                    $MaySales = $this->_calcTotal(date($year.'-5-1'),$toDate,$customerEmail);
                    $data_to_json = array(
                        'Latest_Purchase_date_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $toDate,
                        'Total_Sales_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $total_sales,
                        'Sales_'.$year.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                        'Sales_'.$year.'_M'.$month.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $totalSales_month,
                        'Sales_'.$year.'_3Q_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JulySales,
                        'Sales_'.$year.'_2FMP_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $MaySales,
                        'Sales_'.$year.'_2S_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JulySales,
                    );
                    break;
                case 9:
                    $JulySales = $this->_calcTotal(date($year.'-7-1'),$toDate,$customerEmail);
                    $SeptSales = $this->_calcTotal(date($year.'-9-1'),$toDate,$customerEmail);
                    $data_to_json = array(
                        'Latest_Purchase_date_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $toDate,
                        'Total_Sales_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $total_sales,
                        'Sales_'.$year.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                        'Sales_'.$year.'_M'.$month.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $totalSales_month,
                        'Sales_'.$year.'_3Q_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JulySales,
                        'Sales_'.$year.'_3FMP_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $SeptSales,
                        'Sales_'.$year.'_2S_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JulySales,
                    );
                    break;
                case 10:
                case 11:
                case 12:
                    $JulySales = $this->_calcTotal(date($year.'-7-1'),$toDate,$customerEmail);
                    $SeptSales = $this->_calcTotal(date($year.'-9-1'),$toDate,$customerEmail);
                    $OctSales = $this->_calcTotal(date($year.'-10-1'),$toDate,$customerEmail);
                    $data_to_json = array(
                        'Latest_Purchase_date_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $toDate,
                        'Total_Sales_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST))
                         => $total_sales,
                        'Sales_'.$year.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JanuarySales,
                        'Sales_'.$year.'_M'.$month.'_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $totalSales_month,
                        'Sales_'.$year.'_4Q_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $OctSales,
                        'Sales_'.$year.'_3FMP_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $SeptSales,
                        'Sales_'.$year.'_2S_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => $JulySales,
                    );
                    break;
            } //end Switch Months calculation
            $r = $this->_SendUpsertPropertiesJson($data_to_json,$customerEmail);

            return $r;

        }
    }
    /*
	* BK Changes to Sales Manago Module.
	* Send Upsert Api Call
	* @author: Carlos Alonso de Linaje Garcia
	*/
    protected function _SendUpsertPropertiesJson($data,$email)
    {
        $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
        $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
        $apiKey = md5(time() . $apiSecret);
        $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
        $email = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
        $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

        $data_to_json = array(
                'apiKey' => $apiKey,
                'clientId' => $clientId,
                'requestTime' => time(),
                'contact' => array(
                    'email' => $email,
                    'state' => 'CUSTOMER',
                ),
                'sha' => sha1($apiKey . $clientId . $apiSecret),
                'owner' => $ownerEmail,
        );
        $data_to_json['properties'] = $data;
        $json = json_encode($data_to_json);
        $result = $this->_doPostRequest('https://' . $endPoint . '/api/contact/upsert', $json);
        $r = json_decode($result, true);

        return $r;
    }
    /*
	* BK Changes to Sales Manago Module.
	* Send Event Review.
	* @author: Carlos Alonso de Linaje Garcia
	*/
    public function salesmanagoReviewSync($review)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            Mage::log('salesmanagoReviewSync');
            $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
            $apiKey = md5(time() . $apiSecret);
            $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
            $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
            $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');
            $dateTime = new DateTime('NOW');

            $customerEmail = Mage::getModel('customer/customer')->load($review['customer_id'])->getEmail();

						//Last Minute Change
						//22 December 2015
						//Adding Rating to Event


            $data_to_json = array(
                'apiKey' => $apiKey,
                'clientId' => $clientId,
                'requestTime' => time(),
                'sha' => sha1($apiKey . $clientId . $apiSecret),
                'owner' => $ownerEmail,
                'email' => $customerEmail,
                'contactEvent' => array(
                    'date' => $dateTime->format('c'),
                    'contactExtEventType' => 'OTHER',
                    'products' => $review['entity_pk_value'],
                    'detail1' => 'review',
										'detail2' => $review['title'],
										'detail3' => $review['detail']
                ),
            );
            Mage::log($data_to_json);
            $json = json_encode($data_to_json);
            $result = $this->_doPostRequest('https://' . $endPoint . '/api/contact/addContactExtEvent', $json);
            $r = json_decode($result, true);

            return $r;

        }
    }
	public function salesmanagoOrderSync($orderDetails){
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
            $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
            $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
            $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

            $items = $orderDetails->getAllVisibleItems();
            $itemsNamesList = array();
            foreach ($items as $item) {
                array_push($itemsNamesList, $item->getProduct()->getId());
            }

            $customerEmail = $orderDetails->getCustomerEmail();
            $customerFirstname = $orderDetails->getCustomerFirstname();
            $customerLastname = $orderDetails->getCustomerLastname();
            $grandTotal = $orderDetails->getBaseGrandTotal();
            $incrementOrderId = $orderDetails->getIncrementId();
            //BK Send Payment Method name, Shipping Method name and Shipping Cost on Event.
            $payment_name = $orderDetails->getPayment()->getMethodInstance()->getTitle();;
            $shipping_name = $orderDetails->getShippingDescription();
            $shippment_cost = $orderDetails->getShippingAmount();
            //BK Coupon Code.
            $couponCode = $orderDetails->coupon_code;

            $customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($customerEmail)->getData();


            $orderData = $orderDetails->getData();


            $customerDetails = $orderDetails->getBillingAddress();

            $data = array();
            $data['name'] = $customerDetails->getFirstname() . ' ' . $customerDetails->getLastname();
            $data['phone'] = $customerDetails->getTelephone();
            $data['company'] = $customerDetails->getCompany();
            $data['fax'] = $customerDetails->getFax();
            $data['address']['streetAddress'] = implode($customerDetails->getStreet(), ' ');
            $data['address']['zipCode'] = $customerDetails->getPostcode();
            $data['address']['city'] = $customerDetails->getCity();
            $data['address']['country'] = $customerDetails->getCountryId();

            $data['customerEmail'] = $customerEmail;


            if (isset($orderData['customer_dob']) && !empty($orderData['customer_dob'])) {
                $dataArray = date_parse($orderData['customer_dob']);
                $month = ($dataArray['month'] < 10) ? "0" . $dataArray['month'] : $dataArray['month'];
                $day = ($dataArray['day'] < 10) ? "0" . $dataArray['day'] : $dataArray['day'];
                $year = $dataArray['year'];
                $data['birthday'] = $year . $month . $day;
            }


            if (isset($customer['salesmanago_contact_id']) && !empty($customer['salesmanago_contact_id'])) {
                $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customerEmail);
                $status = $subscriber->isSubscribed();
                if ($status) $data['is_subscribed'] = true;
            } else {
                $data['is_subscribed'] = true; //after purchase
            }


            $r = $this->salesmanagoContactSync($data);
            if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                $data['status'] = 0;
                $data['action'] = 1; //rejestracja
                //$this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
            }


            if ($orderDetails->getCustomerIsGuest() && $r['success'] == true) {
                $period = time() + 3650 * 86400;
                $this->sm_create_cookie('smclient', $r['contactId'], $period);
            }


            $apiKey = md5(time() . $apiSecret);
            $dateTime = new DateTime('NOW');

            $data_to_json = array(
                'apiKey' => $apiKey,
                'clientId' => $clientId,
                'requestTime' => time(),
                'sha' => sha1($apiKey . $clientId . $apiSecret),
                'owner' => $ownerEmail,
                'email' => $customerEmail,
                'contactEvent' => array(
                    'date' => $dateTime->format('c'),
                    'products' => implode(',', $itemsNamesList),
                    'contactExtEventType' => 'PURCHASE',
                    'value' => $grandTotal,
                    //BK Sending Values on detail 1,2 & 3
                    'detail1' => $payment_name,
                    'detail2' => $shipping_name,
                    'detail3' => $shippment_cost,
                    'detail4' => $couponCode,
                    'externalId' => $incrementOrderId,
                ),
            );
            $json = json_encode($data_to_json);
            $result = $this->_doPostRequest('https://' . $endPoint . '/api/contact/addContactExtEvent', $json);
            $r = json_decode($result, true);

            //BK Redeem Coupon
            if ($couponCode)
            {
                Mage::log($couponCode);
                $j = $this->salesmanagoRedeemCoupon($customerEmail,$couponCode);
                Mage::log($j);

            }//BK

            return $r;
        }
	}

	/*
    * Upsert event execute when new user register, sign in and for new owner, which shopping without register
    */
	public function salesmanagoContactSync($data){
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
            $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
            $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
            $tags = Mage::getStoreConfig('salesmanago_tracking/general/tags');
            $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

            $apiKey = md5(time() . $apiSecret);

            $data_to_json = array(
                'apiKey' => $apiKey,
                'clientId' => $clientId,
                'requestTime' => time(),
                'sha' => sha1($apiKey . $clientId . $apiSecret),
                'contact' => array(
                    'email' => $data['customerEmail'],
                    'name' => $data['name'],
                    'fax' => $data['fax'],
                    'company' => $data['company'],
                    'phone' => $data['phone'],
                    'address' => array(
                        'streetAddress' => $data['address']['streetAddress'],
                        'zipCode' => $data['address']['zipCode'],
                        'city' => $data['address']['city'],
                        'country' => $data['address']['country'],
                    ),
                ),
                'owner' => $ownerEmail,
                'async' => false,
            );




            if (!isset($data['is_subscribed'])) {
                $data_to_json['forceOptOut'] = true;
                $data_to_json['forceOptIn'] = false;
            } else {
                $data_to_json['forceOptIn'] = true;
                $data_to_json['forceOptOut'] = false;
            }

            if (isset($data['birthday'])) {
                $data_to_json['birthday'] = $data['birthday'];
            }

            $customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($data['customerEmail'])->getData();

            //BK Change Add a new tag on customer_login
            //BK Change Add a new tag on newsletter subscribe
            $tags=$tags.',last_logged_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST));
            if ($data['is_subscribed'])
                $tags = $tags.',Newsletter_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST));

            if (!empty($tags)) {
                $tags = explode(",", $tags);
                if (is_array($tags) && count($tags) > 0) {
                    $tags = array_map('trim', $tags);
                    $data_to_json['tags'] = $tags;
                }
            }

            $json = json_encode($data_to_json);
            $result = $this->_doPostRequest('https://' . $endPoint . '/api/contact/upsert', $json);

            $r = json_decode($result, true);

            return $r;
        }
	}

	public function salesmanagoSubscriberSync(){

	}

	public function _setCustomerData($customer){
        $data = array();
		$subscription_status = 0;

		$data['customerEmail'] = $customer['email'];
		$data['entity_id'] = $customer['entity_id'];

		$subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer['email']);
		$subscription_status = $subscriber->isSubscribed();

        if(isset($customer['firstname']) && isset($customer['lastname'])){
            $data['name'] = $customer['firstname'].' '.$customer['lastname'];
        }

        if(isset($customer['is_subscribed']) || $subscription_status == 1){
            $data['is_subscribed'] = true;
        }

		if(isset($customer['dob'])){
			$dataArray = date_parse($customer['dob']);
			$month  = ($dataArray['month'] < 10) ? "0".$dataArray['month'] : $dataArray['month'];
			$day  = ($dataArray['day'] < 10) ? "0".$dataArray['day'] : $dataArray['day'];
			$year = $dataArray['year'];
			$data['birthday'] = $year . $month .  $day;
		}

        return $data;
    }

	public function _doPostRequest($url, $data) {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {

            $connection_timeout = Mage::getStoreConfig('salesmanago_tracking/general/connection_timeout');

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if(isset($connection_timeout) && !empty($connection_timeout)){
                curl_setopt($ch, CURLOPT_TIMEOUT_MS, $connection_timeout);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($data)));

            $result = curl_exec($ch);

            if(curl_errno($ch) > 0){
                if(curl_errno($ch)==28){
                    Mage::log("TIMEOUT ERROR NO: " . curl_errno($ch));
                } else{
                    Mage::log("ERROR NO: " . curl_errno($ch));
                }
                return false;
            }

            return $result;

        }
        else {

            return false;

        }
	}
	function sm_create_cookie($name, $value, $period){
		$url = parse_url(Mage::getBaseUrl());
		setcookie($name, $value, $period, '/', '.'.$this -> sm_get_domain($url['host']));

	}
	function sm_get_domain($domain, $debug = false){
		$original = $domain = strtolower($domain);

		if (filter_var($domain, FILTER_VALIDATE_IP)) { return $domain; }

		$debug ? Mage::Log('<strong style="color:green">&raquo;</strong> Parsing: '.$original, null, 'get_domain.log') : false;

		$arr = array_slice(array_filter(explode('.', $domain, 4), function($value){
			return $value !== 'www';
		}), 0); //rebuild array indexes

		if (count($arr) > 2)
		{
			$count = count($arr);
			$_sub = explode('.', $count === 4 ? $arr[3] : $arr[2]);

			$debug ? Mage::Log(" (parts count: {$count})", null, 'get_domain.log') : false;

			if (count($_sub) === 2) // two level TLD
			{
				$removed = array_shift($arr);
				if ($count === 4) // got a subdomain acting as a domain
				{
					$removed = array_shift($arr);
				}
				$debug ? Mage::Log("<br>\n" . '[*] Two level TLD: <strong>' . join('.', $_sub) . '</strong> ', null, 'get_domain.log') : false;
			}
			elseif (count($_sub) === 1) // one level TLD
			{
				$removed = array_shift($arr); //remove the subdomain

				if (strlen($_sub[0]) === 2 && $count === 3) // TLD domain must be 2 letters
				{
					array_unshift($arr, $removed);
				}
				else
				{
					// non country TLD according to IANA
					$tlds = array(
						'aero',
						'arpa',
						'asia',
						'biz',
						'cat',
						'com',
						'coop',
						'edu',
						'gov',
						'info',
						'jobs',
						'mil',
						'mobi',
						'museum',
						'name',
						'net',
						'org',
						'post',
						'pro',
						'tel',
						'travel',
						'xxx',
					);

					if (count($arr) > 2 && in_array($_sub[0], $tlds) !== false) //special TLD don't have a country
					{
						array_shift($arr);
					}
				}
				$debug ? Mage::Log("<br>\n" .'[*] One level TLD: <strong>'.join('.', $_sub).'</strong> ', null, 'get_domain.log') : false;
			}
			else // more than 3 levels, something is wrong
			{
				for ($i = count($_sub); $i > 1; $i--)
				{
					$removed = array_shift($arr);
				}
				$debug ? Mage::Log("<br>\n" . '[*] Three level TLD: <strong>' . join('.', $_sub) . '</strong> ', null, 'get_domain.log') : false;
			}
		}
		elseif (count($arr) === 2)
		{
			$arr0 = array_shift($arr);

			if (strpos(join('.', $arr), '.') === false
				&& in_array($arr[0], array('localhost','test','invalid')) === false) // not a reserved domain
			{
				$debug ? Mage::Log("<br>\n" .'Seems invalid domain: <strong>'.join('.', $arr).'</strong> re-adding: <strong>'.$arr0.'</strong> ', null, 'get_domain.log') : false;
				// seems invalid domain, restore it
				array_unshift($arr, $arr0);
			}
		}

		$debug ? Mage::Log("<br>\n".'<strong style="color:gray">&laquo;</strong> Done parsing: <span style="color:red">' . $original . '</span> as <span style="color:blue">'. join('.', $arr) ."</span><br>\n", null, 'get_domain.log') : false;

		return join('.', $arr);
	}
}
