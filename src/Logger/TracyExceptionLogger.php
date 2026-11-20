<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Logger;

use Throwable;
use Tracy\ILogger;

final class TracyExceptionLogger implements IExceptionLogger
{
    public function __construct(
        private readonly ILogger $logger
    ) {
    }

    public function log(Throwable $exception) : void
    {
        $this->logger->log($exception);
    }
}
