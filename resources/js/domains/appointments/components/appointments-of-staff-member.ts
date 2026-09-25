import type { Appointment } from '../types';

export function appointmentsOfStaffMember(appointments: Appointment[], staffMemberId: string): Appointment[] {
    return appointments.filter((appointment) => appointment.staff_member.id === staffMemberId);
}
