<?php

declare(strict_types=1);

namespace ContactWarden\Signals;

use ContactWarden\Http\RequestContext;

interface SignalInterface
{
    public function evaluate(RequestContext $context): SignalResult;
}
