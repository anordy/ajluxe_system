<?php

namespace App\Exceptions;

use RuntimeException;

/** A rule of the business was broken (e.g. "not enough stock"). The message is shown to the user as-is. */
class BusinessRuleException extends RuntimeException {}
