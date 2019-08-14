<?php

class SalesManago_Tracking_Model_Observer
{

    protected function _getHelper()
    {
        return Mage::helper('tracking');
    }

    public function customer_login($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            $customer = $observer->getCustomer();

            if (!empty($customer)) {
                if (!isset($customer['salesmanago_contact_id']) || empty($customer['salesmanago_contact_id'])) {
                    $data = $this->_getHelper()->_setCustomerData($customer);

                    $r = $this->_getHelper()->salesmanagoContactSync($data);

                    if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                        $data['status'] = 0;
                        $data['action'] = 2; //logowanie
                        $this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
                    }

                    if (isset($r['contactId']) && !empty($r['contactId'])) {
                        try {
                            $observer->getCustomer()->setData('salesmanago_contact_id', $r['contactId'])->save();
                        } catch (Exception $e) {
                            Mage::log($e->getMessage());
                        }
                    }
                }
            }
        }
        if (!isset($_COOKIE['smclient']) || empty($_COOKIE['smclient'])) {
            $period = time() + 36500 * 86400;
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $contactId = $customerData->getSalesmanagoContactId();
            $this->_getHelper()->sm_create_cookie('smclient', $contactId, $period);
        }
    }

    public function customer_register_success($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            $customer = $observer->getCustomer();

            if (!empty($customer) && is_array($customer->getData())) {
                $data = $this->_getHelper()->_setCustomerData($customer);
                $data['tags'] = explode(",", Mage::getStoreConfig('salesmanago_tracking/general/tagsRegistration'));

                $r = $this->_getHelper()->salesmanagoContactSync($data);

                if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                    $data['status'] = 0;
                    $data['action'] = 1; //rejestracja
                    $this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
                }

                if (isset($r['contactId']) && !empty($r['contactId'])) {
                    try {
                        $observer->getCustomer()->setData('salesmanago_contact_id', $r['contactId'])->save();
                    } catch (Exception $e) {
                        Mage::log($e->getMessage());
                    }
                }
            }
        }
        if (!isset($_COOKIE['smclient']) || empty($_COOKIE['smclient'])) {
            $period = time() + 36500 * 86400;
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $contactId = $customerData->getSalesmanagoContactId();
            $this->_getHelper()->sm_create_cookie('smclient', $contactId, $period);
        }

    }

    /*
    * Dodanie zdarzenia addContactExtEvent na podsumowaniu zam�wienia z typem PURCHASE
    */
    public function checkout_onepage_controller_success_action($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
            $orderDetails = Mage::getModel('sales/order')->load($orderId);

            $r = $this->_getHelper()->salesmanagoOrderSync($orderDetails);

            if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                $data = array(
                    'customerEmail' => $orderDetails->getCustomerEmail(),
                    'entity_id' => $orderDetails->getCustomerId(),
                    'order_id' => $orderDetails->getEntityId(),
                    'status' => 0,
                    'action' => 3, //zlozenie zamowienia: addContactExtEvent - PURCHASE
                );
                $this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
            }

            $eventId = Mage::getSingleton('core/session')->getEventId();
            if (isset($eventId) && !empty($eventId)) {
                Mage::getSingleton('core/session')->unsEventId();
            }
        }
    }

    /*
    * Dodanie (oraz na biezaco modyfikowanie) zdarzenia w koszyku addContactExtEvent z typem CART
    */
    public function checkout_cart_save_after($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            $cartHelper = Mage::getModel('checkout/cart')->getQuote();
            $productsIdsList = array();
            $productsNamesList = array();

            $items = $cartHelper->getAllItems();
            foreach ($items as $item) {
                array_push($productsIdsList, $item->getProduct()->getId());
                array_push($productsNamesList, $item->getProduct()->getName());

            }


            $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
            $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
            $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
            $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

            $customerEmail = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
            $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
            //if (!empty($customerEmail)) {
            $apiKey = md5(time() . $apiSecret);
            $dateTime = new DateTime('NOW');


            $data_to_json = array(
                'apiKey' => $apiKey,
                'clientId' => $clientId,
                'requestTime' => time(),
                'sha' => sha1($apiKey . $clientId . $apiSecret),
                'owner' => $ownerEmail,
                'contactEvent' => array(
                    'date' => $dateTime->format('c'),
                    'contactExtEventType' => 'CART',
                    'products' => implode(',', $productsIdsList),
                    'value' => $cartHelper->getGrandTotal(),
                    'detail1' => implode(",", $productsNamesList),
                ),
            );

            $eventId = Mage::getSingleton('core/session')->getEventId();

            if (isset($eventId) && !empty($eventId)) {
                $data_to_json['contactEvent']['eventId'] = $eventId;
                $json = json_encode($data_to_json);
                $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/updateContactExtEvent', $json);
            } else {
                if ($isLoggedIn) {
                    $data_to_json['email'] = $customerEmail;
                } else {
                    if (!empty($_COOKIE['smclient']))
                        $data_to_json['contactId'] = $_COOKIE['smclient'];
                }

                Mage::log(serialize($data_to_json), null, 'mylogfile.log');

                $json = json_encode($data_to_json);
                $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/addContactExtEvent', $json);
            }

            $r = json_decode($result, true);

            if (!isset($eventId) && isset($r['eventId'])) {
                Mage::getSingleton('core/session')->setEventId($r['eventId']);
            }
            //}
        }
    }

    public function newsletter_subscriber_save_before($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            $request = Mage::app()->getRequest();
            $moduleName = $request->getModuleName();
            $controllerName = $request->getControllerName();
            $actionName = $request->getActionName();

            if (($moduleName == 'newsletter' && $controllerName == 'manage' && $actionName == 'save') ||
                ($moduleName == 'newsletter' && $controllerName == 'subscribe' && $actionName == 'unsubscribe') ||
                ($moduleName == 'newsletter' && $controllerName == 'subscriber' && $actionName == 'new') ||
                ($moduleName == 'newsletter' && $controllerName == 'subscriber' && $actionName == 'unsubscribe') ||
                ($moduleName == 'admin' && $controllerName == 'newsletter_subscriber' && $actionName == 'massUnsubscribe')
            ) {

                $isAdmin = false;
                if ($moduleName == 'admin') $isAdmin = true;

                $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
                $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
                $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
                $newsletterTags = Mage::getStoreConfig('salesmanago_tracking/general/tagsNewsletter');
                $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');


                $subscriber = $observer->getEvent()->getDataObject();
                $data = $subscriber->getData();
                $email = $data['subscriber_email'];
                $statusChange = $subscriber->getIsStatusChanged();

                $id = (int)Mage::app()->getFrontController()->getRequest()->getParam('id');
                $code = (string)Mage::app()->getFrontController()->getRequest()->getParam('code');

                $apiKey = md5(time() . $apiSecret);

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

                if (!empty($newsletterTags)) {
                    $newsletterTags = explode(",", $newsletterTags);
                    if (is_array($newsletterTags) && count($newsletterTags) > 0) {
                        $newsletterTags = array_map('trim', $newsletterTags);

                    }
                }

                if ($data['subscriber_status'] == "1" && ($statusChange == true || ($id && $code) || $isAdmin)) {
                    $data_to_json['forceOptIn'] = true;
                    $data_to_json['tags'] = $newsletterTags;
                } elseif ($data['subscriber_status'] == "3" && ($statusChange == true || ($id && $code) || $isAdmin)) {
                    $data_to_json['forceOptOut'] = true;
                    $data_to_json['removeTags'] = $newsletterTags;
                } elseif ($actionName == 'massDelete') {
                    $data_to_json['forceOptOut'] = true;
                    $data_to_json['removeTags'] = $newsletterTags;
                }

                $json = json_encode($data_to_json);
                $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/upsert', $json);

                $r = json_decode($result, true);

                if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                    $data['customerEmail'] = $email;
                    $data['status'] = 0;
                    $data['action'] = 4; //zapis / wypis z newslettera
                    $this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
                }

                if ($moduleName == 'newsletter' && $controllerName == 'subscriber' && $actionName == 'new') {
                    $period = time() + 3650 * 86400;
                    $this->_getHelper()->sm_create_cookie('smclient', $r['contactId'], $period);
                }

                return $r;
            }
        }
    }

    public function newsletter_subscriber_delete_after($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            $request = Mage::app()->getRequest();
            $moduleName = $request->getModuleName();
            $controllerName = $request->getControllerName();
            $actionName = $request->getActionName();

            if (($moduleName == 'admin' && $controllerName == 'newsletter_subscriber' && $actionName == 'massDelete')) {

                $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
                $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
                $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
                $tags = Mage::getStoreConfig('salesmanago_tracking/general/tags');
                $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

                $subscriber = $observer->getEvent()->getDataObject();
                $data = $subscriber->getData();
                $email = $data['subscriber_email'];
                $statusChange = $subscriber->getIsStatusChanged();

                $apiKey = md5(time() . $apiSecret);

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
                    'forceOptOut' => true,
                );

                $json = json_encode($data_to_json);
                $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/upsert', $json);

                $r = json_decode($result, true);

                return $r;
            }
        }
    }

    public function customer_data_edit($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $customer = Mage::getSingleton('customer/session')->getCustomer();

                if (isset($customer['salesmanago_contact_id']) && !empty($customer['salesmanago_contact_id'])) {

                    $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
                    $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
                    $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
                    $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');
                    $apiKey = md5(time() . $apiSecret);
                    $contactId = $customer->getSalesmanagoContactId();

                    foreach ($customer->getAddresses() as $address) {
                        $data = $address->toArray();
                    }

                    $street = $address->getStreet(1) . ' ' . $address->getStreet(2);

                    if (isset($data['firstname']) && isset($data['lastname']) && isset($data['middlename'])) {
                        $data['name'] = $data['firstname'] . ' ' . $data['middlename'] . ' ' . $data['lastname'];
                    } else {
                        $data['name'] = $data['firstname'] . ' ' . $data['lastname'];
                    }

                    $data_to_json = array(
                        'apiKey' => $apiKey,
                        'clientId' => $clientId,
                        'requestTime' => time(),
                        'sha' => sha1($apiKey . $clientId . $apiSecret),
                        'contactId' => $contactId,
                        'contact' => array(
                            'name' => $data['name'],
                            'fax' => $data['fax'],
                            'company' => $data['company'],
                            'phone' => $data['telephone'],
                            'address' => array(
                                'streetAddress' => $street,
                                'zipCode' => $data['postcode'],
                                'city' => $data['city'],
                                'country' => $data['country_id'],
                            ),
                        ),
                        'owner' => $ownerEmail,
                        'async' => false,
                    );

                    $json = json_encode($data_to_json);
                    $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/update', $json);
                }
            }
        }
    }

    public function adminSystemConfigChangedSectionSalesmanago_tracking($observer)
    {
        $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
        $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
        $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
        $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');
        $apiKey = md5(time() . $apiSecret);
        $version = Mage::getVersion();

        $data_to_json = array(
            'apiKey' => $apiKey,
            'clientId' => $clientId,
            'requestTime' => time(),
            'sha' => sha1($apiKey . $clientId . $apiSecret),
            'owner' => $ownerEmail,
            'tags' => ["MAGENTO_1"],
            'properties' => array("platform"=>"Magento", "version"=>$version),
        );

        $json = json_encode($data_to_json);
        $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/upsertVendorToSupport', $json);
    }

    public function wishlist_event($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
            if ($isLoggedIn) {
                $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
                $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
                $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
                $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');
                $apiKey = md5(time() . $apiSecret);
                $dateTime = new DateTime('NOW');
                $customer = Mage::getSingleton('customer/session')->getCustomer();
                $product = $observer->getEvent()->getItems()[0]->getProduct();
                $productId = $product->getId();
                $productName = $product->getName();
                $productValue = round($product->getPrice(), 2);


                $data_to_json = array(
                    'apiKey' => $apiKey,
                    'clientId' => $clientId,
                    'requestTime' => time(),
                    'sha' => sha1($apiKey . $clientId . $apiSecret),
                    'owner' => $ownerEmail,
                    'contactEvent' => array(
                        'date' => $dateTime->format('c'),
                        'contactExtEventType' => 'OTHER',
                        'products' => $productId,
                        'value' => $productValue,
                        'detail1' => $productName,
                        'description' => 'WISHLIST',
                    ),
                );


                if ($isLoggedIn) {
                    $data_to_json['email'] = $customer->getEmail();
                    $json = json_encode($data_to_json);
                } else {
                    if (!isset($_COOKIE['smclient']) || empty($_COOKIE['smclient'])){
                        $data_to_json['contactId'] = $_COOKIE['smclient'];
                        $json = json_encode($data_to_json);
                    }
                }

                if(!empty($json))
                    $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/upsertVendorToSupport', $json);
            }
        }
    }
}

