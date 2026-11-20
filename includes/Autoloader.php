<?php
/**
 * Autoloader for Premium Membership Plugin.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

defined('ABSPATH') || exit;

class Autoloader
{
    /**
     * Register the autoloader.
     */
    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload classes based on namespace.
     *
     * @param string $class Full class name.
     */
    public static function autoload(string $class): void
    {
        $prefix = 'PremiumMembership\\';
        $base_dir = PM_PLUGIN_DIR;

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);

        // Map sub-namespaces to directory structure
        $map = [
            'Admin\\'    => 'admin/',
            'Frontend\\' => 'public/',
            'Gateways\\' => 'includes/gateways/',
            ''           => 'includes/',
        ];

        $found = false;
        foreach ($map as $sub_ns => $dir) {
            if ($sub_ns === '' || str_starts_with($relative_class, $sub_ns)) {
                $sub_len = strlen($sub_ns);
                $clean_class = substr($relative_class, $sub_len);
                $file = $base_dir . $dir . str_replace('\\', '/', $clean_class) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                    $found = true;
                    break;
                }
            }
        }

        // Fallback search in includes
        if (!$found) {
            $file = $base_dir . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}
