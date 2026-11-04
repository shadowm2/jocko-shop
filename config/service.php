<?php

return [
    /*
    | A customer is texted this many days before the next service date the shop
    | entered on their last order, and (for a shop that only just started using
    | the date) up to overdue_days after it. Set remind_days_before to -1 to
    | turn the reminders off.
    */
    'remind_days_before' => (int) env('SERVICE_REMIND_DAYS_BEFORE', 3),

    'overdue_days' => (int) env('SERVICE_REMIND_OVERDUE_DAYS', 30),
];
