<?php

declare(strict_types=1);

use App\Domains\Platform\Infrastructure\Impersonation\SensitiveRoutes;
use Illuminate\Http\Request;

const SENSITIVE_STAFF_MEMBER_UUID = '01930000-0000-7000-8000-0000000ad5f1';

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
        'revealing a staff temporary password' => ['GET', '/api/staff-members/'.SENSITIVE_STAFF_MEMBER_UUID.'/temporary-password'],
    ]);

    it('blocks a user route whatever the method', function (string $method) {
        expect($this->routes->matches(Request::create('/user/password', $method)))->toBeTrue();
    })->with(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);

    it('blocks account and google routes whatever the method', function (string $uri, string $method) {
        expect($this->routes->matches(Request::create($uri, $method)))->toBeTrue();
    })->with([
        '/api/me/account',
        '/api/integrations/google-calendar/connection',
        '/integrations/google-calendar/callback',
        '/api/staff-members/'.SENSITIVE_STAFF_MEMBER_UUID.'/temporary-password',
    ])->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

    it('ignores a query string when matching', function () {
        expect($this->routes->matches(Request::create('/integrations/google-calendar/callback?code=abc&state=xyz', 'GET')))->toBeTrue();
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
        'listing the staff' => ['GET', '/api/staff-members'],
        'reading one staff member' => ['GET', '/api/staff-members/'.SENSITIVE_STAFF_MEMBER_UUID],
        'inviting a staff member' => ['POST', '/api/staff-members/'.SENSITIVE_STAFF_MEMBER_UUID.'/invitation'],
        'the owner logout, which the middleware turns into a stop' => ['POST', '/logout'],
        'the admin area' => ['GET', '/mizita-admin/businesses'],
        'stopping the impersonation' => ['POST', '/mizita-admin/impersonation/stop'],
    ]);

    it('lets a read of the subscription through, since only posts to it are billing actions', function (string $uri) {
        expect($this->routes->matches(Request::create($uri, 'GET')))->toBeFalse();
    })->with([
        '/api/subscription',
        '/api/subscription/checkout',
    ]);

    it('does not mistake a path that merely starts like a user route for one', function () {
        expect($this->routes->matches(Request::create('/users', 'GET')))->toBeFalse();
    });
});
