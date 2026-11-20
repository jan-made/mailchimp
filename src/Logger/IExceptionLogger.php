<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Logger;

use Throwable;

interface IExceptionLogger
{
    public function log(Throwable $exception) : void;
}
