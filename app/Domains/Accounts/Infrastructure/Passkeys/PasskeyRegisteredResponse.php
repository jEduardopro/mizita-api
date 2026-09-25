<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Passkeys;

use App\Domains\Accounts\Application\Dtos\PasskeyData;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\PasskeyMapper;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Domains\Accounts\Infrastructure\Http\Resources\PasskeyRegistrationResource;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Passkey;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class PasskeyRegisteredResponse implements PasskeyRegistrationResponse
{
    private const STATUS_FLASH_KEY = 'status';

    private const REGISTERED_STATUS = 'passkey-registered';

    private ?PasskeyModel $passkey = null;

    public function __construct(
        private readonly PasskeyMapper $mapper,
    ) {}

    public function withPasskey(Passkey $passkey): static
    {
        if (! $passkey instanceof PasskeyModel) {
            throw new LogicException('Passkeys must be stored through '.PasskeyModel::class.'.');
        }

        $this->passkey = $passkey;

        return $this;
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if (! $request->wantsJson()) {
            return back()->with(self::STATUS_FLASH_KEY, self::REGISTERED_STATUS);
        }

        return PasskeyRegistrationResource::make($this->registeredPasskey())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function registeredPasskey(): PasskeyData
    {
        if ($this->passkey === null) {
            throw new LogicException('No passkey was registered before rendering the registration response.');
        }

        return PasskeyData::fromRegisteredPasskey($this->mapper->toRegisteredPasskey($this->passkey));
    }
}
