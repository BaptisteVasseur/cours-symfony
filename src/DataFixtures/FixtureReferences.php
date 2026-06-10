<?php

declare(strict_types=1);

namespace App\DataFixtures;

final class FixtureReferences
{
    public const POLICY_FLEXIBLE = 'policy_flexible';
    public const POLICY_MODERATE = 'policy_moderate';
    public const POLICY_STRICT = 'policy_strict';

    public const AMENITY_WIFI = 'amenity_wifi';
    public const AMENITY_POOL = 'amenity_pool';
    public const AMENITY_PARKING = 'amenity_parking';
    public const AMENITY_KITCHEN = 'amenity_kitchen';
    public const AMENITY_AC = 'amenity_ac';
    public const AMENITY_WASHER = 'amenity_washer';

    public const USER_SUPER_ADMIN = 'user_super_admin';
    public const USER_ADMIN = 'user_admin';
    public const USER_HOST_1 = 'user_host_1';
    public const USER_HOST_2 = 'user_host_2';
    public const USER_HOST_3 = 'user_host_3';
    public const USER_GUEST_1 = 'user_guest_1';
    public const USER_GUEST_2 = 'user_guest_2';
    public const USER_GUEST_3 = 'user_guest_3';
    public const USER_GUEST_4 = 'user_guest_4';

    public const PROPERTY_1 = 'property_1';
    public const PROPERTY_2 = 'property_2';
    public const PROPERTY_3 = 'property_3';

    public const RESERVATION_CONFIRMED = 'reservation_confirmed';
    public const RESERVATION_COMPLETED = 'reservation_completed';
    public const RESERVATION_PENDING = 'reservation_pending';
    public const RESERVATION_CANCELLED = 'reservation_cancelled';

    public const PAYMENT_CONFIRMED = 'payment_confirmed';
    public const REVIEW_1 = 'review_1';
    public const CONVERSATION_1 = 'conversation_1';
}
