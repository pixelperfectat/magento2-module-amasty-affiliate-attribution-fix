<?php

declare(strict_types=1);

/**
 * Bootstrap for unit tests.
 *
 * Loads the Magento project's autoloader (for Magento/Amasty classes)
 * and this module's autoloader (for the module's own classes).
 */

$magentoAutoloader = getenv('MAGENTO_AUTOLOADER')
    ?: '/Users/andre/Sites/Vericom/AlpineNaturprodukteGmbh/kaufhausderberge/kdb-shop-bakehouse/src/vendor/autoload.php';

if (!file_exists($magentoAutoloader)) {
    throw new RuntimeException(
        "Magento autoloader not found at: $magentoAutoloader\n"
        . "Set MAGENTO_AUTOLOADER env var to point to your Magento vendor/autoload.php"
    );
}

require_once $magentoAutoloader;

// Stub Magento-generated factory classes that don't exist as real files
if (!class_exists(\Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory::class)) {
    class_alias(
        \Magento\Framework\ObjectManager\TMap::class,
        \Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory::class,
    );
}

// Register this module's PSR-4 namespace
$moduleBase = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($moduleBase): void {
    $prefix = 'PixelPerfect\\AmastyAffiliateAttributionFix\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $moduleBase . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) {
        require_once $path;
        return;
    }

    // Test namespace
    $testPrefix = 'PixelPerfect\\AmastyAffiliateAttributionFix\\Test\\';
    if (str_starts_with($class, $testPrefix)) {
        $relative = substr($class, strlen($testPrefix));
        $path = $moduleBase . '/Test/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});
