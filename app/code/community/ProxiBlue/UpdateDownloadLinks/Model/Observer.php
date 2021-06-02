<?php

/*
 * (c) Lucas van Staden <sales@proxiblue.com.au>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class ProxiBlue_UpdateDownloadLinks_Model_Observer {

    public function catalog_product_save_after($observer) {
        if (Mage::helper('core')->isModuleOutputEnabled("ProxiBlue_UpdateDownloadLinks") && Mage::getConfig()->getModuleConfig('ProxiBlue_UpdateDownloadLinks')->is('active', 'true')) {
            $product = $observer->getEvent()->getProduct();
            if ($product->getTypeId() == Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE) {
                // get all the purchased items that match the product
                $linkPurchasedItems = Mage::getModel('downloadable/link_purchased_item')->getCollection()
                                ->addFieldToFilter('product_id', $product->getId())->load();
                $currentPurchasedItems = $linkPurchasedItems->getItems();
                $files = $product->getTypeInstance(true)->getLinks($product);
                //build a list of purchase objects (orders) that were used to buy this product
                $productId = $product->getId();
                $productSku = $product->getSku();
                $collection = Mage::getResourceModel('sales/order_item_collection')
                        ->addAttributeToFilter('product_id', array('eq' => $productId))
                        ->load();
                $purchaseObjects = array();
                foreach ($collection as $orderItem) {
                    //check for exact product since several purchased products could be placed per order                
                    $purchaseObject = mage::getModel('downloadable/link_purchased')->getCollection()
                            ->addFieldToFilter('order_id', $orderItem->getOrderId())
                            ->addFieldToFilter('product_sku', $productSku)
                            ->getFirstItem();

                    if ($purchaseObject->getId()) {
                        $purchaseObjects[$purchaseObject->getId()] = $purchaseObject;
                    }
                }
                //determine and add any new files to the orders that have the product
                $newFiles = array_diff_key($files, $currentPurchasedItems);
                foreach ($newFiles as $newFile) {
                    //attach each new file to the purchase
                    foreach ($purchaseObjects as $linkPurchased) {
                        if ($linkPurchased->getOrderItemId()) {
                            $linkHash = strtr(base64_encode(microtime() . $linkPurchased->getId() . $linkPurchased->getOrderItemId()
                                            . $product->getId()), '+/=', '-_,');
                            $linkPurchasedItem = Mage::getModel('downloadable/link_purchased_item');
                            $linkPurchasedItem->setData($newFile->getData());
                            $linkPurchasedItem->unsItemId();
                            $linkPurchasedItem->setPurchasedId($linkPurchased->getId())
                                    ->setOrderItemId($linkPurchased->getOrderItemId())
                                    ->setLinkHash($linkHash)
                                    ->setLinkTitle($newFile->getTitle())
                                    ->setStatus(Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE);
                            //->setUpdatedAt(now())
                            $linkPurchasedItem->save();
                        }
                    }
                }
                // determine what is no longer attached as files and remove from the download links
                $noLongerAttachedAsFiles = array_diff_key($currentPurchasedItems, $files);
                foreach ($noLongerAttachedAsFiles as $purchasedLink) {
                    $purchasedLink->delete();
                }
            }
        }
    }

}
