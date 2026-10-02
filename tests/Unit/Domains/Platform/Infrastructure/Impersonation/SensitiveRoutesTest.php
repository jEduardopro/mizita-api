<?php

declare(strict_types=1);

use App\Domains\Platform\Infrastructure\Impersonation\SensitiveRoutes;
use Illuminate\Http\Request;

const SENSITIVE_STAFF_MEMBER_UUID = '01930000-0000-7000-8000-0000000ad5f1';

const SENSITIVE_STAFF_MEMBER = '/api/staff-members/'.SENSITIVE_STAFF_MEMBER_UUID;

const SENSITIVE_CHECKOUT_SESSION = 'cs_test_a1b2c3';

beforeEach(function () {
    $this->routes = new SensitiveRoutes;
});

describe('off limits while impersonating', function () {
    it('blocks every request that touches the owner credentials, account, billing or google', function (string $method, string $uri) {
        expect($this->routes->matches(Request::create($uri, $method)))->toBeTrue();
    })->with([
        'changing the password' => ['PUT', '/user/password'],
        'changing the profile information and email' => ['PUT', '/user/profile-information'],
        'enabling two factor' => ['POST', '/user/two-factor-authentication'],
        'disabling two factor' => ['DELETE', '/user/two-factor-authentication'],
        'confirming two factor' => ['POST', '/user/confirmed-two-factor-authentication'],
        'reading the two factor secret' => ['GET', '/user/two-factor-secret-key'],
        'reading the recovery codes' => ['GET', '/user/two-factor-recovery-codes'],
        'regenerating the recovery codes' => ['POST', '/user/two-factor-recovery-codes'],
        'reading the two factor qr code' => ['GET', '/user/two-factor-qr-code'],
        'registering a passkey' => ['POST', '/user/passkeys'],
        'reading passkey registration options' => ['GET', '/user/passkeys/options'],
        'deleting a passkey' => ['DELETE', '/user/passkeys/'.SENSITIVE_STAFF_MEMBER_UUID],
        'confirming the password' => ['POST', '/user/confirm-password'],
        'showing the confirm password screen' => ['GET', '/user/confirm-password'],
        'confirming with a passkey' => ['POST', '/passkeys/confirm'],
        'reading passkey confirm options' => ['GET', '/passkeys/confirm/options'],
        'deleting the account' => ['DELETE', '/api/me/account'],
        'previewing the account deletion' => ['GET', '/api/me/account/deletion'],
        'starting a checkout' => ['POST', '/api/subscription/checkout'],
        'confirming a checkout' => ['POST', '/api/subscription/checkout/'.SENSITIVE_CHECKOUT_SESSION.'/confirm'],
        'resuming the subscription' => ['POST', '/api/subscription/resume'],
        'switching to free' => ['POST', '/api/subscription/switch-to-free'],
        'opening the billing portal' => ['POST', '/api/subscription/billing-portal'],
        'authorizing google calendar' => ['POST', '/api/integrations/google-calendar/authorizations'],
        'disconnecting google calendar' => ['DELETE', '/api/integrations/google-calendar/connection'],
        'the google calendar oauth callback' => ['GET', '/integrations/google-calendar/callback'],
    ]);

    it('blocks every change to the team and to the business settings', function (string $method, string $uri) {
        expect($this->routes->matches(Request::create($uri, $method)))->toBeTrue();
    })->with([
        'adding a staff member' => ['POST', '/api/staff-members'],
        'editing a staff member with patch' => ['PATCH', SENSITIVE_STAFF_MEMBER],
        'editing a staff member with put' => ['PUT', SENSITIVE_STAFF_MEMBER],
        'removing a staff member' => ['DELETE', SENSITIVE_STAFF_MEMBER],
        'inviting a staff member' => ['POST', SENSITIVE_STAFF_MEMBER.'/invitation'],
        'revealing a staff temporary password' => ['GET', SENSITIVE_STAFF_MEMBER.'/temporary-password'],
        'creating business settings' => ['POST', '/api/business/settings'],
        'replacing the business settings' => ['PUT', '/api/business/settings'],
        'updating the business settings' => ['PATCH', '/api/business/settings'],
        'deleting the business settings' => ['DELETE', '/api/business/settings'],
    ]);

    it('blocks a user route whatever the method', function (string $method) {
        expect($this->routes->matches(Request::create('/user/password', $method)))->toBeTrue();
    })->with(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);

    it('blocks account, google and temporary password routes whatever the method', function (string $uri, string $method) {
        expect($this->routes->matches(Request::create($uri, $method)))->toBeTrue();
    })->with([
        '/api/me/account',
        '/api/integrations/google-calendar/connection',
        '/integrations/google-calendar/callback',
        SENSITIVE_STAFF_MEMBER.'/temporary-password',
    ])->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

    it('ignores a query string when matching', function () {
        expect($this->routes->matches(Request::create('/integrations/google-calendar/callback?code=abc&state=xyz', 'GET')))->toBeTrue();
    });

    it('cannot be slipped past with the spelling of the path', function (string $method, string $uri) {
        expect($this->routes->matches(Request::create($uri, $method)))->toBeTrue();
    })->with([
        'a trailing slash on the team' => ['POST', '/api/staff-members/'],
        'a trailing slash on the settings' => ['PATCH', '/api/business/settings/'],
        'a percent encoded letter in the settings' => ['PATCH', '/api/business/%73ettings'],
        'a percent encoded character in a staff member id' => ['DELETE', '/api/staff-members/01930000-0000-7000-8000-0000000ad5f%31'],
        'a percent encoded character in the team path' => ['POST', '/api/staff%2Dmembers'],
        'a percent encoded letter in the temporary password' => ['GET', SENSITIVE_STAFF_MEMBER.'/temporary-%70assword'],
    ]);

    it('judges a post overridden to another method as the method the router dispatches', function () {
        $request = Request::create(SENSITIVE_STAFF_MEMBER, 'POST');
        $request->headers->set('X-HTTP-Method-Override', 'PATCH');

        expect($this->routes->matches($request))->toBeTrue();
    });
});

describe('allowed while impersonating', function () {
    it('lets the admin operate the dashboard as the owner', function (string $method, string $uri) {
        expect($this->routes->matches(Request::create($uri, $method)))->toBeFalse();
    })->with([
        'reading the subscription' => ['GET', '/api/subscription'],
        'reading the plans' => ['GET', '/api/plans'],
        'reading the integrations' => ['GET', '/api/integrations'],
        'the integrations page' => ['GET', '/integrations'],
        'listing the businesses' => ['GET', '/api/me/businesses'],
        'switching the current business' => ['PUT', '/api/me/current-business'],
        'reading the own profile' => ['GET', '/api/me/profile'],
        'the calendar page' => ['GET', '/calendar'],
        'booking an appointment' => ['POST', '/api/appointments'],
        'reading the business settings' => ['GET', '/api/business/settings'],
        'reading the calendar settings' => ['GET', '/api/business/calendar-settings'],
        'the owner logout, which the middleware turns into a stop' => ['POST', '/logout'],
        'the admin area' => ['GET', '/mizita-admin/businesses'],
        'stopping the impersonation' => ['POST', '/mizita-admin/impersonation/stop'],
    ]);

    it('lets the admin read the team and run its day to day', function (string $method, string $uri) {
        expect($this->routes->matches(Request::create($uri, $method)))->toBeFalse();
    })->with([
        'listing the staff' => ['GET', '/api/staff-members'],
        'reading one staff member' => ['GET', SENSITIVE_STAFF_MEMBER],
        'previewing a staff removal' => ['GET', SENSITIVE_STAFF_MEMBER.'/removal'],
        'reading a staff schedule' => ['GET', SENSITIVE_STAFF_MEMBER.'/schedule'],
        'replacing a staff schedule' => ['PUT', SENSITIVE_STAFF_MEMBER.'/schedule'],
        'creating a staff booking link' => ['POST', SENSITIVE_STAFF_MEMBER.'/booking-link'],
        'updating a staff booking link' => ['PUT', SENSITIVE_STAFF_MEMBER.'/booking-link'],
        'uploading a staff photo' => ['POST', SENSITIVE_STAFF_MEMBER.'/photo'],
        'deleting a staff photo' => ['DELETE', SENSITIVE_STAFF_MEMBER.'/photo'],
    ]);

    it('takes a staff member id as a single segment, so a nested route is not a member change', function (string $method, string $suffix) {
        expect($this->routes->matches(Request::create(SENSITIVE_STAFF_MEMBER.$suffix, $method)))->toBeFalse();
    })->with([
        'patching a schedule' => ['PATCH', '/schedule'],
        'deleting a schedule' => ['DELETE', '/schedule'],
        'patching a booking link' => ['PATCH', '/booking-link'],
        'deleting a booking link' => ['DELETE', '/booking-link'],
        'putting a photo' => ['PUT', '/photo'],
    ]);

    it('lets a read of the subscription through, since only posts to it are billing actions', function (string $uri) {
        expect($this->routes->matches(Request::create($uri, 'GET')))->toBeFalse();
    })->with([
        '/api/subscription',
        '/api/subscription/checkout',
    ]);

    it('does not mistake a path that merely starts like a protected one for it', function (string $method, string $uri) {
        expect($this->routes->matches(Request::create($uri, $method)))->toBeFalse();
    })->with([
        'users instead of user' => ['GET', '/users'],
        'the settings of something else under business' => ['PATCH', '/api/business/calendar-settings'],
        'a sibling of the team collection' => ['POST', '/api/staff-members-import'],
        'the callback with a suffix' => ['GET', '/integrations/google-calendar/callback-preview'],
    ]);
});
