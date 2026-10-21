<?php

return [
    'invitation_expire_minutes' => (int) env('PORTAL_INVITATION_EXPIRE_MINUTES', 10080),
    'sending_lease_minutes' => (int) env('PORTAL_INVITATION_SENDING_LEASE_MINUTES', 15),
];
