<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Infrastructure\Support;

use DateTimeImmutable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class StoredCalendarCredentials extends PostgresConnection implements ConnectionResolverInterface
{
    private const CONNECTION_NAME = 'stored_calendar_credentials';

    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    /** @var list<string> */
    public array $selects = [];

    /** @var list<array<string, mixed>> */
    public array $updates = [];

    private ?ConnectionResolverInterface $previousResolver = null;

    public function __construct()
    {
        parent::__construct(
            static fn () => throw new RuntimeException('The stored calendar credentials stub has no database behind it.'),
            self::CONNECTION_NAME,
            '',
            ['driver' => 'pgsql', 'name' => self::CONNECTION_NAME],
        );
    }

    public static function install(): self
    {
        $credentials = new self;
        $credentials->previousResolver = Model::getConnectionResolver();

        Model::setConnectionResolver($credentials);

        return $credentials;
    }

    public function uninstall(): void
    {
        if ($this->previousResolver === null) {
            Model::unsetConnectionResolver();

            return;
        }

        Model::setConnectionResolver($this->previousResolver);
    }

    public function hold(
        string $connectionId = IntegrationsFixtures::CONNECTION_ID,
        string $accessToken = IntegrationsFixtures::ACCESS_TOKEN,
        string $refreshToken = IntegrationsFixtures::REFRESH_TOKEN,
        string $accessTokenExpiresAt = '2026-03-29T11:00:00+00:00',
        ?string $deletedAt = null,
    ): self {
        $this->rows[$connectionId] = [
            'id' => count($this->rows) + 1,
            'uuid' => $connectionId,
            'business_id' => 11,
            'staff_member_id' => 21,
            'provider' => 'google',
            'account_email' => IntegrationsFixtures::ACCOUNT_EMAIL,
            'access_token' => Crypt::encryptString($accessToken),
            'refresh_token' => Crypt::encryptString($refreshToken),
            'access_token_expires_at' => $accessTokenExpiresAt,
            'external_calendar_id' => IntegrationsFixtures::CALENDAR_ID,
            'status' => 'connected',
            'connected_at' => IntegrationsFixtures::NOW,
            'created_at' => '2026-03-01 09:00:00',
            'updated_at' => '2026-03-01 09:00:00',
            'deleted_at' => $deletedAt,
        ];

        return $this;
    }

    public function storedAccessToken(string $connectionId = IntegrationsFixtures::CONNECTION_ID): string
    {
        return Crypt::decryptString((string) $this->rows[$connectionId]['access_token']);
    }

    public function storedRefreshToken(string $connectionId = IntegrationsFixtures::CONNECTION_ID): string
    {
        return Crypt::decryptString((string) $this->rows[$connectionId]['refresh_token']);
    }

    public function storedExpiry(string $connectionId = IntegrationsFixtures::CONNECTION_ID): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $this->rows[$connectionId]['access_token_expires_at']);
    }

    public function connection($name = null): self
    {
        return $this;
    }

    public function getDefaultConnection(): string
    {
        return self::CONNECTION_NAME;
    }

    public function setDefaultConnection($name): void {}

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): array
    {
        $this->selects[] = $query;

        foreach ($bindings as $binding) {
            if (is_string($binding) && isset($this->rows[$binding])) {
                return [(object) $this->rows[$binding]];
            }
        }

        return [];
    }

    public function update($query, $bindings = []): int
    {
        preg_match('/ set (.+) where /i', $query, $setClause);
        preg_match_all('/"(\w+)" = \?/', $setClause[1] ?? '', $columns);

        $values = array_combine($columns[1], array_slice($bindings, 0, count($columns[1])));
        $rowKey = end($bindings);

        $this->updates[] = $values;

        foreach ($this->rows as $uuid => $row) {
            if ($row['id'] === $rowKey) {
                $this->rows[$uuid] = [...$row, ...$values];
            }
        }

        return 1;
    }
}
