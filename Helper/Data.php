<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace DphInteg\Webhook\Helper;

use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Information;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use DphInteg\DPHShipping\Helper\ShippingMethodData;
use DphInteg\DPHCustomShipping\Helper\CustomShippingData;
use Zend_Http_Response;

/**
 * Class Data
 * @package DphInteg\Webhook\Helper
 */
class Data extends AbstractHelper
{

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var CurlFactory
     */
    protected $curlFactory;
    
     /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;


    /**
     * @var CustomerRepositoryInterface
     */
    protected $customer;
   
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderDetails;
    
    /**
     * @var StoreInformation
     */
    protected $storeInfo;
   
    /**
     * @var TimezoneInterface
     */
    protected $timezone;
   
     /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * @var ShippingMethodData
     */
    protected $dphShipping;
    
    /**
     * @var CustomShippingData
     */
    protected $dphCustomShipping;
    
    /**
     * Base path to DPH Integ Webhooks configuration values
     */
    
     const XML_PATH_WEBHOOKS = 'system/dphinteg_webhook';
     const XML_WEBHOOKS_ENABLED = self::XML_PATH_WEBHOOKS . '/enable_webhooks';
     const XML_WEBHOOKS_STACK_TRACE_ENABLED = self::XML_PATH_WEBHOOKS . '/enable_stack_trace';
     const XML_WEBHOOKS_WEBHOOK_URL = self::XML_PATH_WEBHOOKS . '/webhook_url';
     const XML_WEBHOOKS_PASSWORD = self::XML_PATH_WEBHOOKS . '/webhook_password';
     const XML_WEBHOOKS_USER = self::XML_PATH_WEBHOOKS . '/webhook_user';
     const XML_WEBHOOKS_PRODUCT_INFO_URL = self::XML_PATH_WEBHOOKS . '/product_info_url';
     const XML_WEBHOOKS_AUTH_KEY = self::XML_PATH_WEBHOOKS . '/auth_key';
    
    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param CurlFactory $curlFactory
     * @param CustomerRepositoryInterface $customer
     * @param OrderRepositoryInterface $orderDetails
     * @param Information $storeInfo
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        CurlFactory $curlFactory,
        CustomerRepositoryInterface $customer,
        OrderRepositoryInterface $orderDetails,
        Information $storeInfo,
        TimezoneInterface $timezone,
        ScopeConfigInterface $scopeConfig,
        ShippingMethodData $shipping,
        CustomShippingData $customShipping    
    ) {
        $this->curlFactory      = $curlFactory;
        $this->customer         = $customer;
        $this->orderDetails     = $orderDetails;
        $this->storeInfo        = $storeInfo;
        $this->timezone         = $timezone;
        $this->scopeConfig      = $scopeConfig;
        $this->objectManager    = $objectManager;
        $this->storeManager     = $storeManager;
        $this->dphShipping      = $shipping;
        $this->dphCustomShipping = $customShipping;

        parent::__construct($context);
    }

     /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_WEBHOOKS_ENABLED);
    }
 
    /**
     * @return bool
     */
    public function isStackTraceEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_WEBHOOKS_STACK_TRACE_ENABLED);
    }
 
    /**
     * @return mixed
     */
    public function getWebhookURL()
    {
        return $this->scopeConfig->getValue(self::XML_WEBHOOKS_WEBHOOK_URL);
    }
 
    /**
     * @return mixed
     */
    public function getWebhookPassword()
    {
        return $this->scopeConfig->getValue(self::XML_WEBHOOKS_PASSWORD);
    }
 
    /**
     * @return mixed
     */
    public function getWebhookUser()
    {
        return $this->scopeConfig->getValue(self::XML_WEBHOOKS_USER);
    }
 
    /**
     * @return mixed
     */
    public function getProductInfoUrl()
    {
        return $this->scopeConfig->getValue(self::XML_WEBHOOKS_PRODUCT_INFO_URL);
    }
    
     /**
     * @return mixed
     */
    public function getAuthorizationKey()
    {
        return $this->scopeConfig->getValue(self::XML_WEBHOOKS_AUTH_KEY);
    }

    /**
     * @param $item
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getItemStore($item)
    {
        return $item->getData('store_id') ?: $this->storeManager->getStore()->getId();
    }

    /**
     * @param $item
     * @param $hookType
     *
     * @throws NoSuchEntityException
     */
    public function send($item)
    {
        
        try {
                $result = $this->sendHttpRequestFromHook($item);
            } catch (Exception $e) {
                $result = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
    }

    /**
     * @param $hook
     * @param bool $item
     * @param bool $log
     *
     * @return array
     */
    public function sendHttpRequestFromHook($item = false, $log = false)
    {
        $url            = '';
        $authentication = '';
        $method         = 'POST';
        $body        = '';
        $headers     = [[]];
        $shippingInfo = $this->getShippingMethodConfiguration($item);

        if(isset($shippingInfo)){
            $result = $this ->getUserCredentials($shippingInfo);
        
            if( $result['success']===true){
                $headers =$result['headers'];
                $url = $result['url'];
                $body = $this ->getOrderInfo($item, $shippingInfo);
            
            }
                else{ return '';
            }
        }
        else{ return '';
        }          

        $contentType = 'application/json';
        return $this->sendHttpRequest($headers, $authentication, $contentType, $url, $body, $method);
    }

    /**
     * @param $headers
     * @param $authentication
     * @param $contentType
     * @param $url
     * @param $body
     * @param $method
     *
     * @return array
     */
    public function sendHttpRequest($headers, $authentication, $contentType, $url, $body, $method)
    {
        if (!$method) {
            $method = 'GET';
        }
        if ($headers && !is_array($headers)) {
            $headers = $this::jsonDecode($headers);
        }
        $headersConfig = [];

        foreach ($headers as $header) {
            $key             = $header['name'];
            $value           = $header['value'];
            $headersConfig[] = trim($key) . ': ' . trim($value);
        }
        
        if ($authentication) {
            $headersConfig[] = 'Authorization: ' . $authentication;
        }

        if ($contentType) {
            $headersConfig[] = 'Content-Type: ' . $contentType;
        }

        $curl = $this->curlFactory->create();
        $curl->write($method, $url, '1.1', $headersConfig, $body);

        $result = ['success' => false];

        try {
            $resultCurl         = $curl->read();
            $result['response'] = $resultCurl;
            if (!empty($resultCurl)) {
                $result['status'] = Zend_Http_Response::extractCode($resultCurl);
                if (isset($result['status']) && in_array($result['status'], [200, 201])) {
                    $result['success'] = true;
                } else {
                    $result['response'] = Zend_Http_Response::extractBody($result['response']);
                    $result['data'] = json_decode($result['response'],true);
                    $result['msg'] = $result['data']['message'];
                    error_log(" error message: {$result['msg']} , body: {$body} ");
                    
                    $result['message'] = __('Cannot connect to server. Please try again later.');
                }
            } else {
                $result['message'] = __('Cannot connect to server. Please try again later.');
            }
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
        }
        $curl->close();

        return $result;
    }

    /**
     * @param $item
     * @param $hookType
     *
     * @throws NoSuchEntityException
     */
    public function sendObserver($item)
    {
        if (!$this->isEnabled()) {
            return;
        }

         try {
                    $result = $this->sendHttpRequestFromHook($item);
                } catch (Exception $e) {
                    $result = [
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                }
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @param $classPath
     * 
     * @return mixed
     */
    public function getObjectClass($classPath)
    {
        return $this->objectManager->create($classPath);
    }

     /**
     * @param $baseUrl
     *
     * @return mixed
     */
     public function getAuthenticationToken($baseUrl)
    {
        $url            = "{$baseUrl}/login"; 
        $method         = 'POST';
        $email          = $this->getWebhookUser();      //'api@sandbox.magento';
        $password       = $this->getWebhookPassword();  //'ku7xdpfi';
        
        $body        = "{\"email\":\"{$email}\",\"password\":\"{$password}\"}";
        $headers     = [];

        
        $authentication = '';
        $contentType = 'application/json';
        
        $result = $this->sendHttpRequest($headers, $authentication, $contentType, $url, $body, $method);
        $result['response'] =  Zend_Http_Response::extractBody($result['response']);
        
        if($result['success'] === true){
                $result['data'] = json_decode($result['response'],true);
                $result['data'] = $result['data']['results'];
        }

        return $result;
    }

    /**
     * @param $hook
     *
     * @return mixed
     */
     public function getPartners($baseUrl)
    {
        $authInfo          = $this->getAuthenticationToken($baseUrl);
        
        if($authInfo['success'] === true){             
           $apiKey = $authInfo['data']['apiKey'];
           $token = $authInfo['data']['sessionToken'];
        }        
        
        $url            = "{$baseUrl}/getPartners?apikey={$apiKey}"; 
        $method         = 'GET';
        
        $headers     = [["name"=> 'Auth-Token',"value"=> $token]];

        
        $authentication = $hook->getAuthentication();
        $contentType = $hook->getContentType();
        $body = false;
        
        $result = $this->sendHttpRequest($headers, $authentication, $contentType, $url, $body, $method);
        $result['response'] =  Zend_Http_Response::extractBody($result['response']);
        
        if($result['success'] === true){
                $result['data'] = json_decode($result['response'],true);
                $result['data'] = $result['data']['results'];
        }

        return $result;
    }

    /**
     * @param $baseUrl
     * @param $authInfo
     * @return mixed
     */
     public function getPartnersWithAuthInfo($baseUrl, $authInfo)
    {
        $apiKey = $authInfo['apiKey'];
        $token  = $authInfo['token'];      
        
        $url            = "{$baseUrl}/getPartners?apiKey={$apiKey}"; 
        $method         = 'GET';
        
        $headers     = [["name"=> 'Auth-Token',"value"=> $token]];

        
        $authentication = '';
        $contentType = 'application/json';
        $body = '';
        
        $result = $this->sendHttpRequest($headers, $authentication, $contentType, $url, $body, $method);
        $result['response'] =  Zend_Http_Response::extractBody($result['response']);
        
        if($result['success'] === true){
                $result['data'] = json_decode($result['response'],true);
                $result['data'] = $result['data']['results'];
        }

        return $result;
    }
    /**
     * @param $baseUrl
     * @param $authInfo
     * @return mixed
     */
    public function getProductInfoList($partnerCode)
    {    
        
        $url            = "{$this->getProductInfoUrl()}/product?client_id={$partnerCode}"; 
        $method         = 'GET';
        
        $headers     = [["name"=> 'Authorization',"value"=> $this->getAuthorizationKey()]];

        
        $authentication = '';
        $contentType = 'application/json';
        $body = '';
        
        $result = $this->sendHttpRequest($headers, $authentication, $contentType, $url, $body, $method);
        $result['response'] =  Zend_Http_Response::extractBody($result['response']);
        // if($result['success'] === true){
        //         $result['data'] = json_decode($result['response'],true);
        // }

        return $result;
    }
    /**
     * @param $hook
     * @return mixed
     */
     public function getUserCredentials($shippingInfo)
    {
         $baseUrl           = $this->getWebhookURL();
         $result            = ['success' => false];
         $authInfo          = $this->getAuthenticationToken($baseUrl);

        if($authInfo['success'] === true){
            
           $apiKey = $authInfo['data']['apiKey'];
           $token = $authInfo['data']['sessionToken'];
           
           $headers     = [["name"=> 'Auth-Token',"value"=> $token]];
           $credentials = ["apiKey"=>$apiKey,"token"=>$token];
           
           $partnerId = $shippingInfo['partnerId'];
           
           /*
            * $partnerInfo          = $this->getPartnersWithAuthInfo($baseUrl, $credentials);        
                if($partnerInfo['success'] === true){ 
                    $partnerId = $partnerInfo['data'][0]['id'];
                }
            * 
            */
            error_log("api-key: {$apiKey}, partnerId: {$partnerId}");
            
            if(isset($apiKey) && isset($partnerId)){
                $url            = "{$baseUrl}/v3/createPost?apiKey={$apiKey}&partnerId={$partnerId}"; 
                
                $result['headers'] = $headers;
                $result['url'] =  $url;
                $result['success'] = true;
            }
        }  

        return $result;
    }

    /**
     * @param $shippingMethod
     * @return mixed
     */
     public function getDphConfiguration($shippingMethod)
    {
        $baseUrl           = $this->getWebhookURL();
        $authInfo          = $this->getAuthenticationToken($baseUrl);
        
        $apiKey = $authInfo['apiKey'];
        $token  = $authInfo['token'];      
        
        $url            = "{$baseUrl}/getShippingInfo?apiKey={$apiKey}&shippingMethod={$shippingMethod}"; 
        $method         = 'GET';
        
        $headers     = [["name"=> 'Auth-Token',"value"=> $token]];

        
        $authentication = '';
        $contentType = 'application/json';
        $body = '';
        
        $result = $this->sendHttpRequest($headers, $authentication, $contentType, $url, $body, $method);
        $result['response'] =  Zend_Http_Response::extractBody($result['response']);
        
        if($result['success'] === true){
                $result['data'] = json_decode($result['response'],true);
                $result['data'] = $result['data']['results'];
        }

        return $result;
    }

    /**
     * @param $item
     * @return mixed
     */
     public function getOrderInfo($item, $shippingInfo)
    {
         $orderId = $item ->getEntityId();
         $orderInfo = $this-> orderDetails->get($orderId);     
         
         // Get Store Information
         $storeId = $item ->getStoreId();
         $store = $this-> storeManager->getStore($storeId);
         $storeInfo = $this->storeInfo->getStoreInformationObject($store);
         
         $storeName = $storeInfo->getName();
         $phone = $storeInfo->getPhone();
         $city = $storeInfo->getCity();
         $region = $storeInfo->getRegionId();
         $postcode = $storeInfo->getPostcode();
         $stLine1 = $storeInfo->getData('street_line1');
         $stLine2 = $storeInfo->getData('street_line2');
         
         $referenceNo =  $orderInfo->getIncrementId();
         $createdDate = $this->timezone->formatDateTime($orderInfo->getCreatedAt());
         $pickupDate = $shippingInfo['pickupDate'];
         $deliveryDate = $shippingInfo['deliveryDate'];
         $packagingSize = $shippingInfo['packagingSize'];
         
         if ($shippingInfo['serviceType']=== 'On-Demand'){
              $payLoad['isDraft'] = true;
         }
         
         $payLoad['refNo'] = $referenceNo;
         $payLoad['source'] = 'Magento';
         
         $payLoad['pickupDetails'] =['senderName'=> $storeName,
             'contactNumber'=> $phone,'pickupDateTime'=>$pickupDate,
             'pickupAddress'=> "{$stLine1} {$stLine2}",
             'pickupCity' => $city,'province'=>$region,
             'postalCode'=>$postcode];
         
            // get customer details
            $custLastName = $orderInfo->getCustomerLastname();
            $custFirstName = $orderInfo->getCustomerFirstname();

            // get shipping details      
            $shippingAddress = $item->getShippingAddress();
            $shippingCity = $shippingAddress->getCity();
            $shippingStreet = $shippingAddress->getStreet();
            $shippingPostcode = $shippingAddress->getPostcode();      
            $shippingTelephone = $shippingAddress->getTelephone();
            $shippingState = $shippingAddress->getData('region');

            $grandTotal = floatval($orderInfo->getGrandTotal());
            $subTotal = floatval($orderInfo->getSubtotal());
            
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $cartObj = $objectManager->get('\Magento\Checkout\Model\Cart');
            $shippingAddressInfo = $cartObj->getQuote()->getShippingAddress();
            $shippingBrgy = $shippingAddressInfo->getData('barangay');
            
             $payLoad['deliveryDetails'] =['recipientName'=> "$custFirstName $custLastName",
             'contactNumber'=> $shippingTelephone,'deliveryDateTime'=>$deliveryDate,
             'deliveryAddress'=> "$shippingStreet[0]",
             'barangay'=> $shippingBrgy,
             'deliveryCity' => $shippingCity,'province'=>$shippingState,
             'postalCode'=>$shippingPostcode,'itemPrice'=>$subTotal,
             'codAmount'=>$grandTotal,'productSize'=>$packagingSize];
           
           $body        = json_encode($payLoad);
          
        return $body;
    }
    
    /**
     * @param $item
     * @return mixed
     */
     public function getShippingMethodConfiguration($item)
    {
         $createdDate = $this->timezone->formatDateTime($item->getCreatedAt());
         
        //Get Configuration of Selected Shipping Method
         $shippingMethodConfig = $this->getPersistentShippingMethod($item);
         
         //Dates & Partner Id
         if(isset($shippingMethodConfig)){
             $result =$this->getCompletionDateTime($shippingMethodConfig, $createdDate);
         }

        return $result;
        
    }
    
    
    /**
     * @param $item
     * @return mixed
     */
     public function getShippingMethod($item)
    {
        $shippingMethod = $this->getPersistentShippingMethod($item);       
        $serviceType = $shippingMethod->getServiceType();
        
        //Pickup Details
        if($serviceType==='On-Demand'){
            
        }
        else{
            
            $schedule = $shippingMethod->getPickupSchedule();
            $scheduleDetails = $this->getShippingSchedule($schedule);      
        }
        
        //Delivery Details
        if($serviceType==='On-Demand'){
            
        }
        else{
            
            $schedule = $shippingMethod->getDeliverySchedule();
            $scheduleDetails = $this->getShippingSchedule($schedule);      
        }

        return $result;
    }

    /**
     * @param $item
     * @return mixed
     */
     public function getPersistentShippingMethod($item)
    {
        $orderId = $item ->getEntityId();
        $orderInfo = $this-> orderDetails->get($orderId); 
        $selectedShippingMethod = $orderInfo->getShippingMethod();
        
        $shippingMethod = explode("_",$selectedShippingMethod)[0];    
        switch ($shippingMethod) {
            case 'dphshipping':
              $result = $this->dphShipping;
              break;
            case 'dphcustomshipping':
              $result = $this->dphCustomShipping;
              break;
            default:
              $result = null;
        }
        
        return $result;
        
    }
    
    /**
     * @param $schedule
     * @param $shippingMethod
     * @return mixed
     */
     public function getShippingSchedule($schedule,$referenceDate)
    {
        $getDate = date('Y-m-d',strtotime($referenceDate));
        $days = intval($schedule['days']);
        $mins = intval($schedule['minutes']);
        $time = str_replace(',', ':', $schedule['time']);
        $anytime ='23:59:59';

        $transactDate = null;
       
        if($schedule['schedule']==='Complete within'){
            $transactDate = date('Y-m-d\TH:i',strtotime("+$mins minutes",strtotime($referenceDate)));
        }
        else{  
            error_log("get shipping schedule:  {$schedule['schedule']} , anytime: {$anytime}, time: {$time} ");
            $addTime = strpos($schedule['schedule'],'anytime')!==false ? $anytime : $time;

            if(strpos($schedule['schedule'],'today')!==false){
                $transactDate = date('Y-m-d\TH:i',strtotime("$getDate,$addTime"));
            }
            else{
                $addDays = strpos($schedule['schedule'],'tomorrow')!==false ? 1 : $days;
                $tDate = date('Y-m-d',strtotime("+$addDays days",strtotime($getDate)));
                $transactDate = date('Y-m-d\TH:i',strtotime("$tDate,$addTime"));
            }
        }
    
        return $transactDate;
    }
    
    /**
     * @param $item
     * @return mixed
     */
     public function getCompletionDateTime($shippingMethod, $createdDate)
    {      
        $serviceType = $shippingMethod->getServiceType();
        $getDate = date('Y-m-d',strtotime($createdDate));
        $pickupDate =null;
        $deliveryDate =null;
        $datesInfo = null;
        
        $datesInfo['partnerId'] = $shippingMethod->getPartner();
        $datesInfo['packagingSize']='';
        $datesInfo['serviceType']=$shippingMethod->getServiceType();
        
        //Pickup Details
        if($serviceType ==='On-Demand'){
            $pickupDate = date('Y-m-d\TH:i',strtotime('+30 minutes',strtotime($createdDate)));
            $datesInfo['partnerId'] = $shippingMethod->getOnDemandPartner();
        }
        else{
            
            $schedule['schedule'] = $shippingMethod->getPickupSchedule();
            $schedule['minutes'] = $shippingMethod->getPickupMinutes();
            $schedule['days'] = $shippingMethod->getPickupDays();
            $schedule['time'] = $shippingMethod->getPickupTime();
               
            $pickupDate = $this->getShippingSchedule($schedule,$createdDate);      
        }
        
        $datesInfo['pickupDate'] = $pickupDate;
        
        //Delivery Details
        if($serviceType==='On-Demand'){
            $time ='23:59:59';
            $deliveryDate = date('Y-m-d\TH:i',strtotime("$getDate,$time"));
        }
        else{
            
            $datesInfo['packagingSize'] = $shippingMethod->getPackagingSize();
            $schedule['schedule'] = $shippingMethod->getDeliverySchedule();
            $schedule['minutes'] = $shippingMethod->getDeliveryMinutes();
            $schedule['days'] = $shippingMethod->getDeliveryDays();
            $schedule['time'] = $shippingMethod->getDeliveryTime();
            
            $deliveryDate = $this->getShippingSchedule($schedule,$pickupDate);      
        }
        
        $datesInfo['deliveryDate'] = $deliveryDate;

        return $datesInfo;
    }

    /**
     * @param $hook
     * @return mixed
     */
     public function getPartnersList()
    {
         $baseUrl           = $this->getWebhookURL();
         $result            = ['success' => false];
         $authInfo          = $this->getAuthenticationToken($baseUrl);

        if($authInfo['success'] === true){
            
           $apiKey = $authInfo['data']['apiKey'];
           $token = $authInfo['data']['sessionToken'];
           
           $credentials = ["apiKey"=>$apiKey,"token"=>$token];
           
           $partnerInfo          = $this->getPartnersWithAuthInfo($baseUrl, $credentials);  
           
            if($partnerInfo['success'] === true){ 
                $result = $partnerInfo['data'];
            }
            
        }  

        return $result;
    }

    function changeOrderStatus($item){
        
    // REPLACE WITH YOUR ACTUAL DATA OBTAINED WHILE CREATING NEW INTEGRATION
    $consumerKey = '0a6vgzcflloank8rodo5pslfeij6yrhy';
    $consumerSecret = 'ywl9b2syh0wij9ewcsojgdm7uv59t17a';
    $accessToken = 'iy5j7a554rkfl8a04yrdsiutwafulzi3';
    $accessTokenSecret = 'uxi1906x5j526osm84mfl2p29nujyvku';

    $method = 'POST';
    $storeUrl = 'http://localhost.com/index.php/rest/V1/orders';

    // GENERATE OAUTH TOKEN function
    $data = [
    'oauth_consumer_key' => $consumerKey,
    'oauth_nonce' => md5(uniqid(rand(), true)),
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp' => time(),
    'oauth_token' => $accessToken,
    'oauth_version' => '1.0',
    ];

    $data['oauth_signature'] = $this->sign($method, $storeUrl, $data, $consumerSecret, $accessTokenSecret);

    // UPDATE ORDER STATUS
    $entity_id =  $item ->getEntityId(); // replace with your entity_id
    $increment_id = 1; // replace with your increment_id. You must have this so as not to increment this value
    $state = "complete"; //complete, pending, etc..
    $status = "complete"; //complete, pending, etc..

    $request_url= $storeUrl;
    $data_json = [
    "entity"=> [
    "entity_id" => $entity_id,
    "increment_id" => $increment_id,
    "state" => $state,
    "status" => $status
    ]
    ];

    $data_string = json_encode($data_json);
    $ch = curl_init($request_url);
    
    $qData=http_build_query($data, '', ',');
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data_string) .'',
    'Authorization: OAuth ' . http_build_query($data, '', ','))
    );
    //$response = curl_exec($ch);
    //$response = json_decode($response);
    print_r($response);
    curl_close($ch);
    
    }
    
    function sign($method, $url, $data, $consumerSecret, $tokenSecret)
    {
        $url = $this->urlEncodeAsZend($url);

        $data = $this->urlEncodeAsZend(http_build_query($data, '', '&'));
        $data = implode('&', [$method, $url, $data]);

        $secret = implode('&', [$consumerSecret, $tokenSecret]);

        return base64_encode(hash_hmac('sha1', $data, $secret, true));
    }

    function urlEncodeAsZend($value)
    {
        $encoded = rawurlencode($value);
        $encoded = str_replace('%7E', '~', $encoded);
        return $encoded;
    }
}