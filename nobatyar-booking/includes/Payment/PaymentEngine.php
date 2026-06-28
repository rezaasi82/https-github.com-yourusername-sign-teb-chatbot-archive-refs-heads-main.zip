<?php

namespace Nobatyar\Payment;

use Nobatyar\Booking\BookingRepository;
use Nobatyar\Coupons\Coupon;
use Nobatyar\Coupons\CouponRepository;
use Nobatyar\Service\ServiceRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Payment status lives entirely in nby_transactions and is intentionally
 * decoupled from booking status (نوبتیار strategy: "وضعیت پرداخت مستقل از
 * وضعیت بوکینگ") - verify_from_callback() never touches the booking row,
 * it only fires nobatyar_payment_verified for other listeners to react to.
 */
class PaymentEngine
{
    private TransactionRepository $transaction_repository;
    private BookingRepository $booking_repository;
    private ServiceRepository $service_repository;
    private CouponRepository $coupon_repository;

    public function __construct(
        TransactionRepository $transaction_repository,
        BookingRepository $booking_repository,
        ServiceRepository $service_repository,
        CouponRepository $coupon_repository
    ) {
        $this->transaction_repository = $transaction_repository;
        $this->booking_repository     = $booking_repository;
        $this->service_repository     = $service_repository;
        $this->coupon_repository      = $coupon_repository;
    }

    /**
     * @return array{transaction_id:int,redirect_url:string}|\WP_Error
     */
    public function init_payment(int $booking_id, string $callback_url)
    {
        $booking = $this->booking_repository->find($booking_id);

        if (! $booking) {
            return new \WP_Error('nobatyar_booking_not_found', __('نوبت یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        $service = $this->service_repository->find((int) $booking['service_id']);

        if (! $service) {
            return new \WP_Error('nobatyar_invalid_service', __('خدمت مرتبط با این نوبت معتبر نیست.', 'nobatyar-booking'), ['status' => 422]);
        }

        $amount = $this->resolve_amount($service, $booking);

        if (null === $amount) {
            return new \WP_Error('nobatyar_no_payment_required', __('برای این نوبت پرداختی تعریف نشده است.', 'nobatyar-booking'), ['status' => 422]);
        }

        $gateway = PaymentGatewayFactory::active_gateway();

        if (! $gateway) {
            return new \WP_Error('nobatyar_no_gateway_configured', __('درگاه پرداخت تنظیم نشده است.', 'nobatyar-booking'), ['status' => 503]);
        }

        $transaction_id = $this->transaction_repository->create([
            'booking_id' => $booking_id,
            'gateway'    => $gateway->get_name(),
            'amount'     => $amount,
            'status'     => TransactionStatus::PENDING,
        ]);

        $transaction = $this->transaction_repository->find($transaction_id);

        $result = $gateway->init($amount, $callback_url, $transaction);

        if (! $result->is_success()) {
            $this->transaction_repository->update_status($transaction_id, TransactionStatus::FAILED, $result->error_message());

            return new \WP_Error('nobatyar_payment_init_failed', $result->error_message(), ['status' => 502]);
        }

        if ($result->authority()) {
            $this->transaction_repository->set_authority($transaction_id, $result->authority());
        }

        return [
            'transaction_id' => $transaction_id,
            'redirect_url'   => $result->redirect_url(),
        ];
    }

    /**
     * @return array{booking_id:int,status:string}|\WP_Error
     */
    public function verify_from_callback(array $callback_params)
    {
        $authority       = null;
        $matched_gateway = null;

        foreach (PaymentGatewayFactory::all_known_gateways() as $gateway) {
            $candidate = $gateway->extract_authority($callback_params);

            if (null !== $candidate) {
                $authority       = $candidate;
                $matched_gateway = $gateway;
                break;
            }
        }

        if (null === $authority || ! $matched_gateway) {
            return new \WP_Error('nobatyar_invalid_callback', __('بازخورد درگاه پرداخت نامعتبر است.', 'nobatyar-booking'), ['status' => 400]);
        }

        $transaction = $this->transaction_repository->find_by_authority($authority);

        if (! $transaction) {
            return new \WP_Error('nobatyar_transaction_not_found', __('تراکنش یافت نشد.', 'nobatyar-booking'), ['status' => 404]);
        }

        // Already finalized (e.g. the gateway retried its callback request) -
        // respond idempotently without re-hitting the gateway's verify endpoint.
        if (TransactionStatus::PENDING !== $transaction['status']) {
            return [
                'booking_id' => (int) $transaction['booking_id'],
                'status'     => $transaction['status'],
            ];
        }

        $result = $matched_gateway->verify($callback_params, $transaction);

        if ($result->is_success()) {
            $this->transaction_repository->mark_verified((int) $transaction['id'], TransactionStatus::SUCCESS, $result->raw_response());

            do_action('nobatyar_payment_verified', (int) $transaction['booking_id'], (int) $transaction['id']);

            return [
                'booking_id' => (int) $transaction['booking_id'],
                'status'     => TransactionStatus::SUCCESS,
            ];
        }

        $this->transaction_repository->mark_verified((int) $transaction['id'], TransactionStatus::FAILED, $result->raw_response());

        return new \WP_Error('nobatyar_payment_failed', $result->error_message() ?? __('پرداخت ناموفق بود.', 'nobatyar-booking'), ['status' => 402]);
    }

    /**
     * The base amount comes from the service's deposit/price as before; if the
     * booking carries a coupon_id (already validated and counted as used at
     * booking-creation time by BookingEngine::book()), the discount is applied
     * on top via the same Coupon::calculate_discount() math CouponEngine::validate()
     * uses for its preview, so the two never disagree on the final amount.
     *
     * A gift card redemption is handled differently: unlike the coupon
     * discount (statically re-derivable from the coupon row at any time), the
     * gift card amount was already locked in and stored on the booking row
     * itself (gift_card_amount_applied) at booking-creation time, since the
     * card's balance mutates per redemption. So it's read directly here rather
     * than recomputed via a repository lookup.
     */
    private function resolve_amount(array $service, array $booking): ?float
    {
        $deposit = null !== $service['deposit_amount'] ? (float) $service['deposit_amount'] : null;
        $price   = null !== $service['price'] ? (float) $service['price'] : null;

        $base = null;

        if (null !== $deposit && $deposit > 0) {
            $base = $deposit;
        } elseif (null !== $price && $price > 0) {
            $base = $price;
        }

        if (null === $base) {
            return null;
        }

        if (! empty($booking['coupon_id'])) {
            $coupon = $this->coupon_repository->find((int) $booking['coupon_id']);

            if ($coupon) {
                $base = max(0.0, $base - Coupon::calculate_discount($coupon, $base));
            }
        }

        $base = max(0.0, $base - (float) ($booking['gift_card_amount_applied'] ?? 0));

        return $base;
    }
}
