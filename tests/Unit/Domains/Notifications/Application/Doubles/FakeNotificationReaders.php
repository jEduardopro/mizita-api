<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Contracts\NotificationReaders;
use App\Domains\Notifications\Exceptions\NotificationsNotAccessible;
use App\Domains\Notifications\ValueObjects\NotificationReader;

final class FakeNotificationReaders implements NotificationReaders
{
    /**
     * @var array<string, NotificationReader>
     */
    private array $readers = [];

    /**
     * @var list<array{businessId: string, accountId: string}>
     */
    public array $lookups = [];

    public function __construct(
        private readonly NotificationsJournal $journal = new NotificationsJournal,
    ) {}

    public static function ofTheTeam(NotificationsJournal $journal = new NotificationsJournal): self
    {
        return (new self($journal))
            ->add(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::MEMBER_ACCOUNT_ID, NotificationReader::member(NotificationsFixtures::MEMBER_ID))
            ->add(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::OWNER_ACCOUNT_ID, NotificationReader::owner(NotificationsFixtures::OWNER_MEMBER_ID))
            ->add(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::OTHER_MEMBER_ACCOUNT_ID, NotificationReader::member(NotificationsFixtures::OTHER_MEMBER_ID));
    }

    public function add(string $businessId, string $accountId, NotificationReader $reader): self
    {
        $this->readers[$businessId.'|'.$accountId] = $reader;

        return $this;
    }

    public function readerFor(string $businessId, string $accountId): NotificationReader
    {
        $this->journal->record('readers.readerFor');
        $this->lookups[] = ['businessId' => $businessId, 'accountId' => $accountId];

        return $this->readers[$businessId.'|'.$accountId] ?? throw NotificationsNotAccessible::forAccount($accountId);
    }
}
