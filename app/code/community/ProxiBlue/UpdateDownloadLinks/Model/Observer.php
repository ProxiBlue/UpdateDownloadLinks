<?php

class ProxiBlue_UpdateDownloadLinks_Model_Observer {

    public function catalog_product_save_after($observer) {
        $product = $observer->getEvent()->getProduct();
        if ($product->getTypeId() == Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE) {
            $date = new DateTime();
            $productPurItem = Mage::getModel('downloadable/link_purchased_item')->getCollection()
                    ->addFieldToFilter('product_id', $product->getId());
            if ($product->getTypeInstance(true)->hasLinks($product)) {
                $fl = $product->getTypeInstance(true)->getLinks($product);
                foreach ($fl as $fl1) {
                    if (!is_null($productPurItem)) {
                        foreach ($productPurItem as $_itemPur) {
                            $_itemPur->setLinkUrl(null)
                                    ->setLinkId($fl1["link_id"])
                                    ->setLinkType('file')
                                    ->setLinkTitle($fl1["default_title"])
                                    ->setStatus($_itemPur->getStatus())
                                    ->setLinkFile($fl1["link_file"])
                                    ->setUpdatedAt($date->format('Y-m-d H:i:s'))
                                    ->save();
                        }
                    }
                }
            }
        }
    }

}

