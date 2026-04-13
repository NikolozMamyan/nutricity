<?php

namespace App\Service;

use App\Entity\Orders;

class OrderWorkflow
{
    /** transitions autorisées */
    public function canTransition(string $from, string $to): bool
    {
        $allowed = [
            Orders::STATUS_NEW       => [Orders::STATUS_VALIDATED, Orders::STATUS_CANCELLED],
            Orders::STATUS_VALIDATED => [Orders::STATUS_PREPARING, Orders::STATUS_CANCELLED],
            Orders::STATUS_PREPARING => [Orders::STATUS_READY, Orders::STATUS_CANCELLED],
            Orders::STATUS_READY     => [Orders::STATUS_COLLECTED, Orders::STATUS_CANCELLED],
            Orders::STATUS_COLLECTED => [],
            Orders::STATUS_CANCELLED => [],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }

    public function nextStatuses(string $from): array
    {
        $all = Orders::STATUSES;
        return array_values(array_filter($all, fn($to) => $this->canTransition($from, $to)));
    }
}