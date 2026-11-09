<?php

namespace App\Services;

use RuntimeException;

/** A booking rule was not met; the message is safe to show the applicant. */
class BookingException extends RuntimeException
{
}
