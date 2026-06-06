<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Enums\Documents;

/**
 * The category a {@see \SupportManager\Sdk\Data\Documents\Policy} belongs to.
 *
 * Mirrors the platform's `PolicyCategory` enum.
 */
enum PolicyCategory: string
{
    case Hr = 'hr';
    case HealthAndSafety = 'health_and_safety';
    case Procedures = 'procedures';
    case Safeguarding = 'safeguarding';
    case DataProtection = 'data_protection';
    case QualityAssurance = 'quality_assurance';
    case Training = 'training';
    case General = 'general';
}
