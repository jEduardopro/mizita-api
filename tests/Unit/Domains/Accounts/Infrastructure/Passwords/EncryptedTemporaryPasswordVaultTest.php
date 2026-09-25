<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Passwords\EncryptedTemporaryPasswordVault;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->previousResolver = Model::getConnectionResolver();

    Model::setConnectionResolver(new class implements ConnectionResolverInterface
    {
        public function connection($name = null)
        {
            throw new RuntimeException('The vault reached for a database connection.');
        }

        public function getDefaultConnection()
        {
            return 'unreachable';
        }

        public function setDefaultConnection($name) {}
    });
});

afterEach(function () {
    if ($this->previousResolver === null) {
        Model::unsetConnectionResolver();

        return;
    }

    Model::setConnectionResolver($this->previousResolver);
});

it('answers an empty batch with nobody, without reaching the database', function () {
    expect((new EncryptedTemporaryPasswordVault)->accountsHoldingTemporaryPassword([]))->toBe([]);
});
