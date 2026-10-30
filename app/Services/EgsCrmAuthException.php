<?php

namespace App\Services;

use RuntimeException;

/** API kaliti berilmagan yoki noto'g'ri (401) — keyingi so'rovlar ham o'xshamaydi */
class EgsCrmAuthException extends RuntimeException
{
}
