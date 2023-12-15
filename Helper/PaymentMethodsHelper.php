<?php

namespace Paynow\PaymentGateway\Helper;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Customer\Model\Session;
use Paynow\Exception\PaynowException;
use Paynow\Model\PaymentMethods\PaymentMethod;
use Paynow\Model\PaymentMethods\Type;
use Paynow\PaymentGateway\Model\Logger\Logger;
use Paynow\Service\Payment;

/**
 * Class PaymentMethodsHelper
 *
 * @package Paynow\PaymentGateway\Helper
 */
class PaymentMethodsHelper
{
    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var Quote
     */
    private $quote;

    /**
     * @var Session
     */
    private $customerSession;

    public function __construct(
        PaymentHelper $paymentHelper,
        Logger $logger,
        ConfigHelper
        $configHelper,
        Quote $quote,
        Session $customerSession
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->logger        = $logger;
        $this->configHelper  = $configHelper;
        $this->quote         = $quote;
        $this->customerSession = $customerSession;
    }

    /**
     * Returns payment methods array
     *
     * @param string|null $currency
     * @param float|null $amount
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getAvailable(?string $currency = null, ?float $amount = null): array
    {
        $paymentMethodsArray = [];
        if (!$this->configHelper->isConfigured()) {
            return $paymentMethodsArray;
        }

        try {
            $payment      = new Payment($this->paymentHelper->initializePaynowClient());
            $amount       = $this->paymentHelper->formatAmount($amount);
            $idempotencyKey = KeysGenerator::generateIdempotencyKey(KeysGenerator::generateExternalIdFromQuoteId($this->quote->getId()));
            $customerId = $this->customerSession->getCustomer()->getId();
            $buyerExternalId = $customerId ? $this->paymentHelper->generateBuyerExternalId($customerId) : null;
            $applePayEnabled = htmlspecialchars($_COOKIE['applePayEnabled'] ?? '0') === '1';
            $methods      = $payment->getPaymentMethods($currency, $amount, $applePayEnabled, $idempotencyKey, $buyerExternalId)->getAll();
            $isBlikActive = $this->configHelper->isBlikActive();

            foreach ($methods ?? [] as $paymentMethod) {
                if (!(Type::BLIK === $paymentMethod->getType() && $isBlikActive) && Type::CARD !== $paymentMethod->getType()) {
                    $paymentMethodsArray[] = [
                        'id'          => $paymentMethod->getId(),
                        'name'        => $paymentMethod->getName(),
                        'description' => $paymentMethod->getDescription(),
                        'image'       => $paymentMethod->getImage(),
                        'enabled'     => $paymentMethod->isEnabled()
                    ];
                }
            }
        } catch (PaynowException $exception) {
			$this->logger->error(
				$exception->getMessage(),
				[
					'service' => 'Payment',
					'action' => 'getPaymentMethods',
					'currency' => $currency,
					'amount' => $amount,
					'code' => $exception->getCode(),
				]
			);
        }

        return $paymentMethodsArray;
    }

    /**
     * Returns payment methods array
     *
     * @param string|null $currency
     * @param float|null $amount
     *
     * @return PaymentMethod
     * @throws NoSuchEntityException
     */
    public function getBlikPaymentMethod(?string $currency = null, ?float $amount = null)
    {
        if (!$this->configHelper->isConfigured()) {
            return null;
        }

        try {
            $payment        = new Payment($this->paymentHelper->initializePaynowClient());
            $amount         = $this->paymentHelper->formatAmount($amount);
            $idempotencyKey = KeysGenerator::generateIdempotencyKey(KeysGenerator::generateExternalIdFromQuoteId($this->quote->getId()));
            $customerId = $this->customerSession->getCustomer()->getId();
            $buyerExternalId = $customerId ? $this->paymentHelper->generateBuyerExternalId($customerId) : null;
            $paymentMethods = $payment->getPaymentMethods($currency, $amount, $idempotencyKey, $buyerExternalId)->getOnlyBlik();

            if (! empty($paymentMethods)) {
                return $paymentMethods[0];
            }
        } catch (PaynowException $exception) {
			$this->logger->error(
				$exception->getMessage(),
				[
					'service' => 'Payment',
					'action' => 'getPaymentMethods',
					'paymentMethod' => 'BLIK',
					'currency' => $currency,
					'amount' => $amount,
					'code' => $exception->getCode(),
				]
			);
        }

        return null;
    }

    /**
     * Returns payment methods array
     *
     * @param string|null $currency
     * @param float|null $amount
     *
     * @return PaymentMethod
     * @throws NoSuchEntityException
     */
    public function getCardPaymentMethod(?string $currency = null, ?float $amount = null)
    {
        if (!$this->configHelper->isConfigured()) {
            return null;
        }

        try {
            $payment        = new Payment($this->paymentHelper->initializePaynowClient());
            $amount         = $this->paymentHelper->formatAmount($amount);
            $idempotencyKey = KeysGenerator::generateIdempotencyKey(KeysGenerator::generateExternalIdFromQuoteId($this->quote->getId()));
            $customerId = $this->customerSession->getCustomer()->getId();
            $buyerExternalId = $customerId ? $this->paymentHelper->generateBuyerExternalId($customerId) : null;
            $paymentMethods = $payment->getPaymentMethods($currency, $amount, $idempotencyKey, $buyerExternalId)->getOnlyCards();

            if (!empty($paymentMethods)) {
                return $paymentMethods[0];
            }
        } catch (PaynowException $exception) {
            $this->logger->error(
				$exception->getMessage(),
				[
					'service' => 'Payment',
					'action' => 'getPaymentMethods',
					'paymentMethod' => 'card',
					'currency' => $currency,
					'amount' => $amount,
					'code' => $exception->getCode(),
				]
			);
        }

        return null;
    }
}
