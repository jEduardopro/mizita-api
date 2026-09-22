<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\BusinessPaymentMethodData;
use App\Domains\Payments\Application\UseCases\ListBusinessPaymentMethods;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Payments\FakePaymentMethodCatalog;
use Tests\Support\Payments\PaymentFixtures;

beforeEach(function () {
    $this->cash = PaymentFixtures::paymentMethod(PaymentFixtures::CASH_METHOD_ID, 'cash', 1);
    $this->card = PaymentFixtures::paymentMethod(PaymentFixtures::CARD_METHOD_ID, 'card', 2, requiresIntegration: true);
    $this->transfer = PaymentFixtures::paymentMethod(PaymentFixtures::TRANSFER_METHOD_ID, 'transfer', 3);

    $this->paymentMethods = new FakePaymentMethodCatalog;

    $this->build = fn (?FakeBusinessContext $business = null): ListBusinessPaymentMethods => new ListBusinessPaymentMethods(
        $this->paymentMethods,
        $business ?? new FakeBusinessContext,
    );

    $this->useCase = ($this->build)();
});

it('answers with what the catalogue offers the business, enabled or not', function () {
    $this->paymentMethods
        ->enableFor(FakeBusinessContext::BUSINESS_ID, $this->cash)
        ->disableFor(FakeBusinessContext::BUSINESS_ID, $this->card)
        ->register($this->transfer);

    $methods = $this->useCase->handle()->value();

    expect($methods)->toHaveCount(3)
        ->and($methods[0])->toBeInstanceOf(BusinessPaymentMethodData::class)
        ->and($methods[0]->id)->toBe(PaymentFixtures::CASH_METHOD_ID)
        ->and($methods[0]->code)->toBe('cash')
        ->and($methods[0]->position)->toBe(1)
        ->and($methods[0]->enabled)->toBeTrue()
        ->and($methods[0]->requiresIntegration)->toBeFalse()
        ->and($methods[1]->id)->toBe(PaymentFixtures::CARD_METHOD_ID)
        ->and($methods[1]->enabled)->toBeFalse()
        ->and($methods[1]->requiresIntegration)->toBeTrue()
        ->and($methods[2]->id)->toBe(PaymentFixtures::TRANSFER_METHOD_ID)
        ->and($methods[2]->enabled)->toBeFalse();
});

it('answers with a success even when the catalogue is empty', function () {
    $response = $this->useCase->handle();

    expect($response->failed())->toBeFalse()
        ->and($response->value())->toBe([]);
});

it('hands back each method under the uuid the client already holds', function () {
    $this->paymentMethods->enableFor(FakeBusinessContext::BUSINESS_ID, $this->cash);

    expect($this->useCase->handle()->value()[0]->id)->toBe(PaymentFixtures::CASH_METHOD_ID);
});

it('asks the catalogue about the business in context', function () {
    $this->useCase->handle();

    expect($this->paymentMethods->availabilityReads)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('shows a method as enabled only for the business that enabled it', function () {
    $this->paymentMethods->enableFor(PaymentFixtures::OTHER_BUSINESS_ID, $this->cash);

    $mine = $this->useCase->handle()->value();
    $theirs = ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))->handle()->value();

    expect($mine[0]->enabled)->toBeFalse()
        ->and($theirs[0]->enabled)->toBeTrue();
});
