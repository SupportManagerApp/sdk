<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Dashboard;

/**
 * A feature a team's plan may include, mirroring the platform's `PlanFeature`.
 * The values are the keys of {@see \SupportManager\Sdk\Data\Dashboard\Capabilities::$planFeatures};
 * the platform may add more, so that map keeps plain string keys.
 */
enum PlanFeature: string
{
    case Dashboard = 'dashboard';
    case Rota = 'rota';
    case Holidays = 'holidays';
    case Documents = 'documents';
    case CarJourneys = 'car_journeys';
    case Timesheets = 'timesheets';
    case Payroll = 'payroll';
    case Auditing = 'auditing';
    case Recruitment = 'recruitment';
    case ESigning = 'e_signing';
    case Agencies = 'agencies';
    case AiAssistance = 'ai_assistance';
}
