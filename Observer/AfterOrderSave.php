<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace DphInteg\Webhook\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use DphInteg\Webhook\Helper\Data;

/**
 * Class AfterSave
 * @package Magecitron\Dphwebhook\Observer
 */
class AfterOrderSave implements ObserverInterface
{

    /**
     * @var Data
     */
    protected $helper;
 

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * AfterSave constructor.
     *
     * @param ManagerInterface $messageManager
     * @param StoreManagerInterface $storeManager
     * @param Data $helper
     */
    public function __construct(
        //ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        Data $helper
    ) {
        $this->helper          = $helper;
        $this->storeManager    = $storeManager;
    }

    /**
     * @param Observer $observer
     *
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $item     = $observer->getDataObject();
        $this->helper->send($item);
    }
    
}
