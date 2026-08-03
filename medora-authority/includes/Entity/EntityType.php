<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Entity types Medora recognises, aligned with Schema.org so an entity can be
 * emitted as JSON-LD without a translation layer.
 */
final class EntityType
{
    public const THING            = 'Thing';
    public const PERSON           = 'Person';
    public const ORGANIZATION     = 'Organization';
    public const PLACE            = 'Place';
    public const PRODUCT          = 'Product';
    public const SERVICE          = 'Service';
    public const CREATIVE_WORK    = 'CreativeWork';
    public const TOPIC            = 'DefinedTerm';
    public const EVENT            = 'Event';

    // Medical vocabulary (YMYL mode).
    public const PHYSICIAN            = 'Physician';
    public const MEDICAL_ORGANIZATION = 'MedicalOrganization';
    public const HOSPITAL             = 'Hospital';
    public const MEDICAL_CLINIC       = 'MedicalClinic';
    public const MEDICAL_CONDITION    = 'MedicalCondition';
    public const MEDICAL_PROCEDURE    = 'MedicalProcedure';
    public const MEDICAL_SPECIALTY    = 'MedicalSpecialty';
    public const DRUG                 = 'Drug';
    public const SYMPTOM              = 'MedicalSignOrSymptom';
    public const ANATOMY              = 'AnatomicalStructure';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::THING,
            self::PERSON,
            self::ORGANIZATION,
            self::PLACE,
            self::PRODUCT,
            self::SERVICE,
            self::CREATIVE_WORK,
            self::TOPIC,
            self::EVENT,
            self::PHYSICIAN,
            self::MEDICAL_ORGANIZATION,
            self::HOSPITAL,
            self::MEDICAL_CLINIC,
            self::MEDICAL_CONDITION,
            self::MEDICAL_PROCEDURE,
            self::MEDICAL_SPECIALTY,
            self::DRUG,
            self::SYMPTOM,
            self::ANATOMY,
        ];
    }

    /** @return list<string> */
    public static function medical(): array
    {
        return [
            self::PHYSICIAN,
            self::MEDICAL_ORGANIZATION,
            self::HOSPITAL,
            self::MEDICAL_CLINIC,
            self::MEDICAL_CONDITION,
            self::MEDICAL_PROCEDURE,
            self::MEDICAL_SPECIALTY,
            self::DRUG,
            self::SYMPTOM,
            self::ANATOMY,
        ];
    }

    public static function isMedical(string $type): bool
    {
        return in_array($type, self::medical(), true);
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true) || (bool) apply_filters('medora_entity_type_valid', false, $type);
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::PERSON               => __('Person', 'medora-authority'),
            self::ORGANIZATION         => __('Organization', 'medora-authority'),
            self::PLACE                => __('Place', 'medora-authority'),
            self::PRODUCT              => __('Product', 'medora-authority'),
            self::SERVICE              => __('Service', 'medora-authority'),
            self::CREATIVE_WORK        => __('Creative work', 'medora-authority'),
            self::TOPIC                => __('Topic', 'medora-authority'),
            self::EVENT                => __('Event', 'medora-authority'),
            self::PHYSICIAN            => __('Physician', 'medora-authority'),
            self::MEDICAL_ORGANIZATION => __('Medical organization', 'medora-authority'),
            self::HOSPITAL             => __('Hospital', 'medora-authority'),
            self::MEDICAL_CLINIC       => __('Clinic', 'medora-authority'),
            self::MEDICAL_CONDITION    => __('Condition', 'medora-authority'),
            self::MEDICAL_PROCEDURE    => __('Procedure', 'medora-authority'),
            self::MEDICAL_SPECIALTY    => __('Specialty', 'medora-authority'),
            self::DRUG                 => __('Drug', 'medora-authority'),
            self::SYMPTOM              => __('Sign or symptom', 'medora-authority'),
            self::ANATOMY              => __('Anatomical structure', 'medora-authority'),
            default                    => __('Thing', 'medora-authority'),
        };
    }
}
