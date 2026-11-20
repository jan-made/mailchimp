<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Logger;

use Throwable;

final class NullExceptionLogger implements IExceptionLogger
{
    public function log(Throwable $exception) : void
    {
    }
}
