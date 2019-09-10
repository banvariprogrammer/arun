<?php

namespace Ambab\BankDiscount\Helper\PayPal\Log;

use Ambab\BankDiscount\Helper\Psr\Log\LoggerInterface;

interface PayPalLogFactory
{
    /**
     * Returns logger instance implementing LoggerInterface.
     *
     * @param string $className
     * @return LoggerInterface instance of logger object implementing LoggerInterface
     */
    public function getLogger($className);
}
