<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Eloquent\Models\MediaModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Tests\Support\Media\FakeMediaOwner;
use Tests\Support\Media\RootMediaOwner;

beforeEach(function () {
    $this->previousDispatcher = Model::getEventDispatcher();

    Model::clearBootedModels();
    Model::setEventDispatcher(new Dispatcher);
});

afterEach(function () {
    Model::clearBootedModels();

    if ($this->previousDispatcher === null) {
        Model::unsetEventDispatcher();

        return;
    }

    Model::setEventDispatcher($this->previousDispatcher);
});

function mediaOwnedBy(mixed $owner): MediaModel
{
    $media = new MediaModel;
    $media->setRelation('model', $owner);

    return $media;
}

function mediaBeingCreated(MediaModel $media): MediaModel
{
    Model::getEventDispatcher()->until('eloquent.creating: '.MediaModel::class, $media);

    return $media;
}

describe('the business a media row belongs to', function () {
    it('takes the business of the owner it is being attached to', function () {
        $media = mediaBeingCreated(mediaOwnedBy(new FakeMediaOwner(42)));

        expect($media->business_id)->toBe(42);
    });

    it('asks the owner rather than a bound context, so a worker and the console fill it too', function () {
        $owner = new FakeMediaOwner;

        mediaBeingCreated(mediaOwnedBy($owner));

        expect($owner->timesAsked)->toBe(1);
    });

    it('leaves a business the caller already named alone, and asks the owner nothing', function () {
        $owner = new FakeMediaOwner(42);
        $media = mediaOwnedBy($owner);
        $media->business_id = 99;

        mediaBeingCreated($media);

        expect($media->business_id)->toBe(99)
            ->and($owner->timesAsked)->toBe(0);
    });

    it('leaves it empty for an owner that belongs to no business', function () {
        expect(mediaBeingCreated(mediaOwnedBy(new RootMediaOwner))->business_id)->toBeNull();
    });

    it('leaves it empty when the row names no owner at all', function () {
        expect(mediaBeingCreated(mediaOwnedBy(null))->business_id)->toBeNull();
    });
});

describe('what the row hands out', function () {
    it('hides the internal key, so the int foreign key never leaves infrastructure', function () {
        expect((new MediaModel)->getHidden())->toContain('business_id');
    });

    it('reads the key back as an int', function () {
        expect((new MediaModel)->getCasts())->toHaveKey('business_id', 'integer');
    });

    it('keeps every json cast the media library declares, so the custom properties still decode', function () {
        expect((new MediaModel)->getCasts())
            ->toHaveKey('manipulations', 'array')
            ->toHaveKey('custom_properties', 'array')
            ->toHaveKey('generated_conversions', 'array')
            ->toHaveKey('responsive_images', 'array');
    });
});
