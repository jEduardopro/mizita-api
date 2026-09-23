<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent;

use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\Exceptions\AppointmentAlreadyHasPayment;
use App\Domains\Payments\Exceptions\PaymentAccountNotFound;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\Infrastructure\Eloquent\Mappers\PaymentMapper;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentItemModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentModel;
use App\Domains\Payments\Infrastructure\Eloquent\Models\PaymentTransactionModel;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentSummary;
use App\Shared\Contracts\BusinessTeamKey;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentPaymentRepository implements PaymentRepository
{
    private const APPOINTMENT_UNIQUE_INDEX = 'payments_appointment_unique';

    private const BUSINESSES_TABLE = 'businesses';

    private const APPOINTMENTS_TABLE = 'appointments';

    private const PAYMENT_METHODS_TABLE = 'payment_methods';

    private const USERS_TABLE = 'users';

    /**
     * @var list<string>
     */
    private const AGGREGATE_RELATIONS = [
        'appointment',
        'items',
        'transactions.paymentMethod',
        'transactions.account',
    ];

    /**
     * @var list<string>
     */
    private const SUMMARY_COLUMNS = [
        'uuid',
        'appointment_id',
        'total_cents',
        'paid_cents',
        'currency_code',
    ];

    public function __construct(
        private readonly PaymentMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function findForAppointment(string $businessId, string $appointmentId): Payment
    {
        return $this->findForAppointmentOrNull($businessId, $appointmentId)
            ?? throw PaymentNotFound::withId($appointmentId);
    }

    public function findForAppointmentOrNull(string $businessId, string $appointmentId): ?Payment
    {
        $model = $this->appointmentPaymentQuery($businessId, $appointmentId)->first();

        if ($model === null) {
            return null;
        }

        return $this->mapper->toEntity($model, $businessId);
    }

    public function findForBusiness(string $businessId, string $paymentId): Payment
    {
        return $this->mapper->toEntity(
            $this->modelOrFail($this->ofBusiness($businessId), $paymentId),
            $businessId,
        );
    }

    public function lockForBusiness(string $businessId, string $paymentId): Payment
    {
        return $this->mapper->toEntity(
            $this->modelOrFail($this->ofBusiness($businessId)->lockForUpdate(), $paymentId),
            $businessId,
        );
    }

    public function save(Payment $payment): void
    {
        $model = $this->upsert($payment);

        $this->syncItems($model, $payment);
        $this->syncTransactions($model, $payment);
    }

    /**
     * @param  list<string>  $appointmentIds
     * @return array<string, PaymentSummary>
     */
    public function summariesForAppointments(string $businessId, array $appointmentIds): array
    {
        if ($appointmentIds === []) {
            return [];
        }

        $appointmentIdsByKey = self::appointmentIdsByKey($businessId, $appointmentIds);

        if ($appointmentIdsByKey === []) {
            return [];
        }

        $rows = $this->ofBusiness($businessId)
            ->whereIn('appointment_id', array_keys($appointmentIdsByKey))
            ->get(self::SUMMARY_COLUMNS);

        $summaries = [];

        foreach ($rows as $row) {
            $appointmentId = $appointmentIdsByKey[(int) $row->appointment_id];

            $summaries[$appointmentId] = new PaymentSummary(
                appointmentId: $appointmentId,
                paymentId: $row->uuid,
                status: self::statusOf($row->paid_cents, $row->total_cents),
                totalCents: $row->total_cents,
                paidCents: $row->paid_cents,
                currencyCode: $row->currency_code,
            );
        }

        return $summaries;
    }

    public function hasPaymentForAppointment(string $businessId, string $appointmentId): bool
    {
        return $this->ofBusiness($businessId)
            ->whereIn('appointment_id', self::appointmentKeyQuery($appointmentId))
            ->exists();
    }

    /**
     * @throws AppointmentAlreadyHasPayment
     */
    private function upsert(Payment $payment): PaymentModel
    {
        try {
            return PaymentModel::query()->updateOrCreate(
                ['uuid' => $payment->id],
                $this->mapper->toAttributes(
                    $payment,
                    $this->businessKeys->teamKeyFor($payment->businessId),
                    $this->appointmentKeyFor($payment->appointmentId),
                ),
            );
        } catch (UniqueConstraintViolationException $violation) {
            self::failFrom($payment, $violation);
        }
    }

    private function syncItems(PaymentModel $model, Payment $payment): void
    {
        $paymentKey = (int) $model->id;
        $keptIds = [];

        foreach ($payment->items() as $item) {
            PaymentItemModel::query()->updateOrCreate(
                ['uuid' => $item->id],
                $this->mapper->itemToAttributes($item, $paymentKey),
            );

            $keptIds[] = $item->id;
        }

        PaymentItemModel::query()
            ->where('payment_id', $paymentKey)
            ->whereNotIn('uuid', $keptIds)
            ->delete();
    }

    private function syncTransactions(PaymentModel $model, Payment $payment): void
    {
        $paymentKey = (int) $model->id;
        $pending = self::unpersistedTransactions($payment->transactions(), $paymentKey);

        if ($pending === []) {
            return;
        }

        $methodKeys = self::paymentMethodKeys($pending);
        $accountKeys = self::accountKeys($pending);

        foreach ($pending as $transaction) {
            PaymentTransactionModel::query()->create(
                $this->mapper->transactionToAttributes(
                    $transaction,
                    $paymentKey,
                    $methodKeys[$transaction->paymentMethodId],
                    $transaction->accountId === null ? null : $accountKeys[$transaction->accountId],
                ),
            );
        }
    }

    /**
     * @param  list<PaymentTransaction>  $transactions
     * @return list<PaymentTransaction>
     */
    private static function unpersistedTransactions(array $transactions, int $paymentKey): array
    {
        if ($transactions === []) {
            return [];
        }

        $persistedIds = PaymentTransactionModel::withTrashed()
            ->where('payment_id', $paymentKey)
            ->pluck('uuid')
            ->all();

        return array_values(array_filter(
            $transactions,
            static fn (PaymentTransaction $transaction): bool => ! in_array($transaction->id, $persistedIds, true),
        ));
    }

    /**
     * @param  Builder<PaymentModel>  $scoped
     *
     * @throws PaymentNotFound
     */
    private function modelOrFail(Builder $scoped, string $paymentId): PaymentModel
    {
        $model = $scoped->with(self::AGGREGATE_RELATIONS)->where('uuid', $paymentId)->first();

        if ($model === null) {
            throw PaymentNotFound::withId($paymentId);
        }

        return $model;
    }

    /**
     * @return Builder<PaymentModel>
     */
    private function appointmentPaymentQuery(string $businessId, string $appointmentId): Builder
    {
        return $this->ofBusiness($businessId)
            ->with(self::AGGREGATE_RELATIONS)
            ->whereIn('appointment_id', self::appointmentKeyQuery($appointmentId));
    }

    /**
     * @return Builder<PaymentModel>
     */
    private function ofBusiness(string $businessId): Builder
    {
        return PaymentModel::query()->whereIn('business_id', self::businessKeyQuery($businessId));
    }

    private static function businessKeyQuery(string $businessId): Closure
    {
        return static fn (QueryBuilder $query) => $query
            ->select('id')
            ->from(self::BUSINESSES_TABLE)
            ->where('uuid', $businessId);
    }

    /**
     * @throws PaymentAppointmentNotFound
     */
    private function appointmentKeyFor(string $appointmentId): int
    {
        return self::keyOf(self::APPOINTMENTS_TABLE, $appointmentId)
            ?? throw PaymentAppointmentNotFound::withId($appointmentId);
    }

    /**
     * @param  list<PaymentTransaction>  $transactions
     * @return array<string, int>
     *
     * @throws PaymentMethodNotFound
     */
    private static function paymentMethodKeys(array $transactions): array
    {
        $wanted = array_values(array_unique(array_map(
            static fn (PaymentTransaction $transaction): string => $transaction->paymentMethodId,
            $transactions,
        )));

        $keys = self::keysOf(self::PAYMENT_METHODS_TABLE, $wanted);

        foreach ($wanted as $paymentMethodId) {
            if (! isset($keys[$paymentMethodId])) {
                throw PaymentMethodNotFound::withId($paymentMethodId);
            }
        }

        return $keys;
    }

    /**
     * @param  list<PaymentTransaction>  $transactions
     * @return array<string, int>
     *
     * @throws PaymentAccountNotFound
     */
    private static function accountKeys(array $transactions): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(
            static fn (PaymentTransaction $transaction): ?string => $transaction->accountId,
            $transactions,
        ))));

        if ($wanted === []) {
            return [];
        }

        $keys = self::accountKeysOf($wanted);

        foreach ($wanted as $accountId) {
            if (! isset($keys[$accountId])) {
                throw PaymentAccountNotFound::withId($accountId);
            }
        }

        return $keys;
    }

    private static function keyOf(string $table, string $uuid): ?int
    {
        return self::keysOf($table, [$uuid])[$uuid] ?? null;
    }

    /**
     * @param  list<string>  $uuids
     * @return array<string, int>
     */
    private static function keysOf(string $table, array $uuids): array
    {
        return self::keysFrom(
            DB::table($table)->whereIn('uuid', $uuids)->whereNull('deleted_at'),
        );
    }

    /**
     * @param  list<string>  $uuids
     * @return array<string, int>
     */
    private static function accountKeysOf(array $uuids): array
    {
        return self::keysFrom(DB::table(self::USERS_TABLE)->whereIn('uuid', $uuids));
    }

    /**
     * @return array<string, int>
     */
    private static function keysFrom(QueryBuilder $query): array
    {
        return $query->pluck('id', 'uuid')
            ->map(static fn (mixed $key): int => (int) $key)
            ->all();
    }

    /**
     * @param  list<string>  $appointmentIds
     * @return array<int, string>
     */
    private static function appointmentIdsByKey(string $businessId, array $appointmentIds): array
    {
        $keys = self::keysFrom(
            DB::table(self::APPOINTMENTS_TABLE)
                ->whereIn('uuid', $appointmentIds)
                ->whereNull('deleted_at')
                ->whereIn('business_id', self::businessKeyQuery($businessId)),
        );

        $byKey = [];

        foreach ($keys as $appointmentId => $key) {
            $byKey[$key] = $appointmentId;
        }

        return $byKey;
    }

    private static function appointmentKeyQuery(string $appointmentId): Closure
    {
        return static fn (QueryBuilder $query) => $query
            ->select('id')
            ->from(self::APPOINTMENTS_TABLE)
            ->where('uuid', $appointmentId);
    }

    private static function statusOf(int $paidCents, int $totalCents): PaymentStatus
    {
        if ($paidCents >= $totalCents) {
            return PaymentStatus::Paid;
        }

        if ($paidCents === 0) {
            return PaymentStatus::Pending;
        }

        return PaymentStatus::PartiallyPaid;
    }

    /**
     * @throws AppointmentAlreadyHasPayment
     */
    private static function failFrom(Payment $payment, UniqueConstraintViolationException $violation): never
    {
        if (str_contains($violation->getMessage(), self::APPOINTMENT_UNIQUE_INDEX)) {
            throw AppointmentAlreadyHasPayment::forAppointment($payment->appointmentId, $violation);
        }

        throw $violation;
    }
}
