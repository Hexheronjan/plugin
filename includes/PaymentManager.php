<?php
/**
 * Payment Manager / Gateway Registry.
 *
 * @package PremiumMembership
 */

namespace PremiumMembership;

use PremiumMembership\Gateways\MockGateway;
use PremiumMembership\Gateways\MidtransGateway;
use PremiumMembership\Gateways\DuitkuGateway;
use PremiumMembership\Gateways\XenditGateway;
use PremiumMembership\Gateways\TripayGateway;
use PremiumMembership\Gateways\IPaymuGateway;
use PremiumMembership\Gateways\UniversalGateway;

defined('ABSPATH') || exit;

class PaymentManager
{
    /**
     * Registered gateways array
     *
     * @var array<string, PaymentGatewayInterface>
     */
    private static array $gateways = [];

    /**
     * Initialize default gateways
     */
    public static function init(): void
    {
        if (empty(self::$gateways)) {
            self::register_gateway(new MockGateway());
            self::register_gateway(new MidtransGateway());
            self::register_gateway(new XenditGateway());
            self::register_gateway(new TripayGateway());
            self::register_gateway(new DuitkuGateway());
            self::register_gateway(new IPaymuGateway());
            self::register_gateway(new UniversalGateway());

            // Allow external plugins or add-ons to register custom gateways
            do_action('pm_register_payment_gateways', __CLASS__);
        }
    }

    /**
     * Register a payment gateway.
     *
     * @param PaymentGatewayInterface $gateway
     */
    public static function register_gateway(PaymentGatewayInterface $gateway): void
    {
        self::$gateways[$gateway->get_id()] = $gateway;
    }

    /**
     * Get specific gateway by slug.
     *
     * @param string $slug
     * @return PaymentGatewayInterface|null
     */
    public static function get_gateway(string $slug): ?PaymentGatewayInterface
    {
        self::init();
        return self::$gateways[$slug] ?? null;
    }

    /**
     * Get the currently active payment gateway from settings.
     *
     * @return PaymentGatewayInterface
     */
    public static function get_active_gateway(): PaymentGatewayInterface
    {
        self::init();
        $active_slug = Helpers::get_setting('active_gateway', 'mock');
        $gateway = self::get_gateway($active_slug);

        if (!$gateway) {
            $gateway = new MockGateway();
        }

        return $gateway;
    }

    /**
     * Get list of all registered gateways.
     *
     * @return array<string, string> slug => name
     */
    public static function get_available_gateways(): array
    {
        self::init();
        $list = [];
        foreach (self::$gateways as $slug => $instance) {
            $list[$slug] = $instance->get_name();
        }
        return $list;
    }
}
