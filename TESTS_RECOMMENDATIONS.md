# Tests recommandés - Système de Réservation

## 🧪 Stratégie de test

Ce document liste tous les tests à implémenter pour valider le système de réservation.

## Unit Tests

### Service: AvailabilityChecker

```php
tests/Service/AvailabilityCheckerTest.php

- testIsAvailable_PropertyNotPublished()
  // Property.status != 'published' → false

- testIsAvailable_InsufficientCapacity()
  // guestCount > property.maxGuests → false

- testIsAvailable_WithUnavailability()
  // Date range overlaps with PropertyUnavailability → false

- testIsAvailable_WithConfirmedReservation()
  // Date range overlaps with confirmed reservation → false

- testIsAvailable_AllConditionsMet()
  // All checks pass → true

- testGetAvailabilityDetails_ReturnsConflicts()
  // Returns array with conflicts and reasons

- testGetBlockedDates_CombinesUnavailabilityAndReservations()
  // Returns merged list of all blocked dates

- testGetNextAvailableDate_FindsFirstAvailableDay()
  // Skips unavailable days, returns next available

- testGetNextAvailableDate_ReturnsNullIfNoAvailability()
  // When property closed for long period
```

### Service: ReservationManager

```php
tests/Service/ReservationManagerTest.php

State transition tests:

- testConfirm_ChangesStatusPendingToConfirmed()
  // pending → confirmed

- testConfirm_ThrowsExceptionIfNotPending()
  // Can't confirm if already confirmed/cancelled

- testReject_ChangesStatusPendingToCancelled()
  // pending → cancelled with reason

- testReject_ThrowsExceptionIfNotPending()
  // Can't reject if not pending

- testCancel_ChangesStatusToCancelled()
  // any status → cancelled (except completed)

- testCancel_ThrowsExceptionIfCompleted()
  // Can't cancel completed reservation

- testComplete_ChangesStatusToCompleted()
  // confirmed → completed (after checkout date)

- testComplete_ThrowsExceptionBeforeCheckout()
  // Can't complete before checkout date

Status history tests:

- testConfirm_RecordsStatusHistoryEntry()
  // Creates entry with oldStatus, newStatus, changedBy

- testReject_RecordsReasonInHistory()
  // Stores rejection reason

Message dispatch tests:

- testConfirm_DispatchesConfirmedMessage()
  // Verifies ReservationConfirmedMessage sent

- testReject_DispatchesRejectedMessage()
  // Verifies ReservationRejectedMessage sent

- testCancel_DispatchesCancelledMessage()
  // Verifies ReservationCancelledMessage sent
```

## Integration Tests

### Repository: ReservationRepository

```php
tests/Repository/ReservationRepositoryTest.php

- testCountOverlappingReservations_NoOverlap()
  // Returns 0 for non-conflicting dates

- testCountOverlappingReservations_WithOverlap()
  // Returns 1+ for overlapping confirmed reservations

- testCountOverlappingReservations_IgnoresPending()
  // Pending reservations don't count as overlap

- testCountOverlappingReservations_IgnoresCancelled()
  // Cancelled reservations don't count

- testCountOverlappingReservations_ExcludesSpecificReservation()
  // Excludes the provided reservation from count

- testFindOverlappingReservations_ReturnsConflictingReservations()
  // Returns array of overlapping confirmed reservations

- testFindPendingByProperty_ReturnsPendingReservations()
  // Only returns status='pending'

- testFindByHostForListing_ReturnsAllHostReservations()
  // Returns all reservations of host's properties
```

### Repository: PropertyUnavailabilityRepository

```php
tests/Repository/PropertyUnavailabilityRepositoryTest.php

- testHasUnavailabilityBetween_ReturnsTrueWhenOverlap()
  // Detects overlapping unavailability

- testHasUnavailabilityBetween_ReturnsFalseNoOverlap()
  // Returns false for non-overlapping dates

- testFindOverlappingUnavailability_ReturnsList()
  // Returns array of overlapping unavailability periods

- testFindByProperty_ReturnsAllUnavailabilityForProperty()
  // Lists all blocked periods
```

### Controller: BookingController

```php
tests/Controller/BookingControllerTest.php

- testCheckout_GET_DisplaysBookingForm()
  // Form is rendered correctly

- testCheckout_POST_InvalidDates_ShowsError()
  // checkin >= checkout shows error

- testCheckout_POST_ValidDates_CreatesReservation()
  // Creates reservation with status=pending or confirmed

- testCheckout_POST_CreatesReservationWithCorrectPricing()
  // Total price = (nights × pricePerNight) + cleaningFee + serviceFee

- testCheckout_POST_DispatchesReservationCreatedMessage()
  // Message sent to messenger

- testCheckout_RedirectsToReservationShow()
  // After success, redirects to reservation detail page

- testCheckout_HostCannotBookOwnProperty()
  // Shows error message

- testCheckout_UnpublishedProperty_Shows404()
  // Throws NotFoundException
```

### Controller: SearchController

```php
tests/Controller/SearchControllerTest.php

- testSearch_WithoutFilters_ReturnsAllPublished()
  // /search returns all published properties

- testSearch_ByDestination_FiltersCity()
  // ?destination=Paris filters by city

- testSearch_ByDestination_FiltersByAddress()
  // ?destination=filters by address text

- testSearch_ByGuests_ExcludesSmallProperties()
  // ?guests=4 excludes properties with maxGuests < 4

- testSearch_ByDates_FiltersAvailable()
  // ?checkin=2026-07-10&checkout=2026-07-15 checks availability

- testSearch_CombinedFilters_Works()
  // All filters combined work correctly

- testSearch_InvalidDates_ShowsError()
  // checkin >= checkout shows error message

- testSearch_ValidatesDateFormat()
  // Rejects malformed dates
```

### Controller: HostReservationController

```php
tests/Controller/HostReservationControllerTest.php

- testList_ShowsAllReservationsForHost()
  // Dashboard displays all host's reservations

- testList_GroupsByStatus()
  // Pending, confirmed, completed, cancelled sections

- testDetail_HostCanViewOwnReservation()
  // Can access detail page of own reservation

- testDetail_HostCannotViewOtherReservation()
  // Throws 403 Forbidden for other's reservation

- testDetail_POST_Confirm_UpdatesReservation()
  // Changes status to 'confirmed'

- testDetail_POST_Reject_SavesReason()
  // Cancels reservation with motif

- testDetail_POST_Cancel_SavesReason()
  // Cancels confirmed reservation with motif

- testDetail_POST_DispatchesProperMessage()
  // Correct message sent to Messenger
```

### Controller: HostUnavailabilityController

```php
tests/Controller/HostUnavailabilityControllerTest.php

- testList_ShowsAllUnavailabilityForProperty()
  // Lists all blocked periods

- testNew_GET_DisplaysForm()
  // Form is rendered

- testNew_POST_CreatesUnavailability()
  // Saves period to database

- testNew_POST_ValidatesDateRange()
  // endDate > startDate required

- testNew_POST_HostCannotModifyOtherProperty()
  // Throws 403 Forbidden

- testEdit_Updates ExistingUnavailability()
  // Modifies existing period

- testDelete_RemovesUnavailability()
  // Deletes period from database
```

### Controller: PropertyICalController

```php
tests/Controller/PropertyICalControllerTest.php

- testExportICal_WithoutToken_Returns401()
  // Missing token → Unauthorized

- testExportICal_WithInvalidToken_Returns401()
  // Invalid token → Unauthorized

- testExportICal_WithRevokedToken_Returns401()
  // Revoked token → Unauthorized

- testExportICal_ValidToken_ReturnsICal()
  // Valid token returns iCal format

- testExportICal_ValidatesTokenFormat()
  // Returns proper RFC 5545 format

- testExportICal_IncludesOnlyConfirmedReservations()
  // Pending/cancelled not included

- testExportICal_UpdatesLastAccessedAt()
  // Token.lastAccessedAt is updated

- testExportICal_ResponseHeaders_Correct()
  // Content-Type: text/calendar
  // Content-Disposition: attachment
```

### Message Handlers

```php
tests/MessageHandler/ReservationMessageHandlersTest.php

- testReservationCreatedMessageHandler_SendsEmailToHost_WhenPending()
  // Email sent to host for approval

- testReservationCreatedMessageHandler_SendsEmailToGuest_WhenConfirmed()
  // Email sent to guest for instant booking

- testReservationConfirmedMessageHandler_SendsEmailsToHostandGuest()
  // Both parties notified

- testReservationRejectedMessageHandler_SendsEmailToGuest()
  // Guest notified with reason

- testReservationCancelledMessageHandler_SendsEmailsToBoth()
  // Both parties notified with reason

- testMessageHandlers_UseCorrectEmailAddresses()
  // Correct email addresses used
```

## End-to-End Tests

```php
tests/E2E/ReservationFlowTest.php

Complete user journey tests:

Scenario 1: Instant Booking
- testInstantBookingFlow()
  1. Voyageur recherche → /search
  2. Sélectionne propriété → /logement/123/reserver
  3. Remplit dates et guests → POST /logement/123/reserver
  4. Assert: Réservation créée avec status='confirmed'
  5. Assert: Email reçu au voyageur
  6. Assert: Dates bloquées dans AvailabilityChecker

Scenario 2: On-Demand Booking
- testOnDemandBookingFlow()
  1. Voyageur crée réservation → status='pending'
  2. Assert: Email reçu par hôte
  3. Hôte consulte demande → /host/reservations/123
  4. Hôte accepte → POST action=confirm
  5. Assert: Status changé à 'confirmed'
  6. Assert: Email de confirmation au voyageur

Scenario 3: Cancellation
- testCancellationFlow()
  1. Hôte accède réservation confirmée
  2. Clique sur "ANNULER"
  3. Remplit motif
  4. Assert: Status='cancelled'
  5. Assert: Dates libérées (AvailabilityChecker retourne true)
  6. Assert: Emails envoyés aux deux parties

Scenario 4: ICal Export
- testICalExportFlow()
  1. Hôte génère token → /host/properties/123/ical-tokens/new
  2. Copie URL iCal
  3. Ajoute dans Google Calendar
  4. Assert: URL valide, token généré
  5. Assert: Réservations confirmées visibles
  6. Hôte revoke token
  7. Assert: Token révoqué, accès refusé (401)
```

## Performance Tests

```php
tests/Performance/AvailabilityPerformanceTest.php

- testCheckAvailabilityOn365Days_CompletesUnder100ms()
  // Single query, not iterative

- testSearchWithComplexFilters_CompletesUnder500ms()
  // Database indexes used efficiently

- testLargeReservationList_LoadsUnder1s()
  // 1000+ reservations load quickly
```

## Security Tests

```php
tests/Security/ReservationSecurityTest.php

- testUnauthorizedUserCannotAccessHostDashboard()
  // Requires ROLE_USER

- testGuestCannotAccessOtherGuestReservation()
  // Voter prevents access

- testHostCannotAccessOtherHostReservation()
  // Voter prevents access

- testICal TokenCannotBeGuessed()
  // Token is cryptographically secure (random_bytes)

- testCSRFTokenRequired_OnDelete()
  // CSRF tokens validated
```

## Manual Testing Checklist

### Setup

- [ ] PHP 8.4 installed
- [ ] Migrations run
- [ ] Messenger configured
- [ ] Mailpit running
- [ ] Database populated with fixtures

### Search & Booking

- [ ] Search filters work (destination, dates, guests)
- [ ] Unavailability blocks availability checker
- [ ] Instant booking creates confirmed reservation
- [ ] On-demand booking creates pending reservation
- [ ] Price calculation is correct
- [ ] Dates displayed correctly (checkout not included)

### Host Dashboard

- [ ] Host sees only own reservations
- [ ] Pending section shows new requests
- [ ] Can accept pending reservation
- [ ] Can reject pending reservation (with reason)
- [ ] Can cancel confirmed reservation (with reason)
- [ ] Status changes reflect immediately

### Unavailability Management

- [ ] Can create blocked period
- [ ] Can modify blocked period
- [ ] Can delete blocked period
- [ ] Blocked dates prevent reservations
- [ ] Reason stored correctly

### iCal Tokens

- [ ] Can generate new token
- [ ] Token is unique
- [ ] Can revoke token
- [ ] Revoked token gives 401 error
- [ ] iCal file imports to Google Calendar
- [ ] Confirmed reservations appear in external calendar

### Email Notifications

- [ ] Email sent on pending request (to host)
- [ ] Email sent on confirmation (to guest + host)
- [ ] Email sent on rejection (to guest with reason)
- [ ] Email sent on cancellation (to both with reason)
- [ ] All emails received in Mailpit

---

**Couverture cible:** >85% de couverture de code  
**Temps d'exécution total:** <60 secondes (tests unitaires + intégration)
