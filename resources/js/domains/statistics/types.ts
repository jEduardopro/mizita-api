export type StatisticsRange = {
    from: string;
    to: string;
};

export type CollectedPeriod = {
    from: string;
    to: string;
    collected_cents: number;
};

export type DailyCollection = {
    date: string;
    collected_cents: number;
};

export type PaymentMethodCollection = {
    code: string;
    collected_cents: number;
    share_percent: number;
};

export type StaffMemberCollection = {
    id: string;
    name: string;
    email: string;
    collected_cents: number;
    share_percent: number;
    attended_appointments: number;
};

export type AppointmentStatistics = {
    total: number;
    attended: number;
    cancelled: number;
    upcoming: number;
    by_source: {
        admin: number;
        public: number;
    };
};

export type CustomerStatistics = {
    attended: number;
    new: number;
    returning: number;
};

export type BusinessStatistics = {
    currency_code: string;
    timezone: string;
    period: CollectedPeriod;
    previous_period: CollectedPeriod;
    change_percent: number | null;
    today: DailyCollection;
    last_7_days: DailyCollection[];
    by_payment_method: PaymentMethodCollection[];
    by_staff: StaffMemberCollection[];
    appointments: AppointmentStatistics;
    customers: CustomerStatistics;
};
