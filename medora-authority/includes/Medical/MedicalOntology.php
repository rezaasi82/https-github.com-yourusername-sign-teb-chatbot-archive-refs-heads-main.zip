<?php

declare(strict_types=1);

namespace Medora\Authority\Medical;

use Medora\Authority\Entity\EntityType;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A seed clinical vocabulary, with Persian and Arabic surface forms.
 *
 * This is deliberately a *seed*, not a full ontology — shipping SNOMED or MeSH
 * inside a plugin is neither licensable nor practical. What it does is give a
 * medical site immediate, meaningful entity recognition out of the box, in the
 * languages the product targets, with Wikidata identifiers so the entities
 * reconcile against something an LLM already knows.
 *
 * Sites extend it through `medora_medical_ontology`, and a customer's own term
 * list loads the same way.
 */
final class MedicalOntology
{
    /**
     * @return array<string, array{type: string, aliases: list<string>, same_as: list<string>}>
     */
    public function terms(): array
    {
        $terms = [
            // --- Conditions -------------------------------------------------
            'Diabetes mellitus' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['diabetes', 'type 2 diabetes', 'دیابت', 'مرض السكري', 'قند خون'],
                'same_as' => ['https://www.wikidata.org/wiki/Q12206'],
            ],
            'Hypertension' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['high blood pressure', 'فشار خون بالا', 'ارتفاع ضغط الدم'],
                'same_as' => ['https://www.wikidata.org/wiki/Q41861'],
            ],
            'Gastroesophageal reflux disease' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['GERD', 'acid reflux', 'ریفلاکس معده', 'رفلاکس', 'الارتجاع المعدي'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1195532'],
            ],
            'Irritable bowel syndrome' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['IBS', 'سندرم روده تحریک‌پذیر', 'القولون العصبي'],
                'same_as' => ['https://www.wikidata.org/wiki/Q243519'],
            ],
            'Fatty liver disease' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['hepatic steatosis', 'NAFLD', 'کبد چرب', 'الكبد الدهني'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1058054'],
            ],
            'Hepatitis B' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['هپاتیت ب', 'التهاب الكبد ب'],
                'same_as' => ['https://www.wikidata.org/wiki/Q39227'],
            ],
            'Crohn\'s disease' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['regional enteritis', 'بیماری کرون', 'داء كرون'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1088087'],
            ],
            'Peptic ulcer' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['stomach ulcer', 'زخم معده', 'قرحة المعدة'],
                'same_as' => ['https://www.wikidata.org/wiki/Q184753'],
            ],

            // --- Procedures ---------------------------------------------------
            'Endoscopy' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['gastroscopy', 'آندوسکوپی', 'اندوسکوپی', 'التنظير'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1069405'],
            ],
            'Colonoscopy' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['کولونوسکوپی', 'تنظير القولون'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1129963'],
            ],
            'Liver biopsy' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['بیوپسی کبد', 'خزعة الكبد'],
                'same_as' => [],
            ],
            'FibroScan' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['transient elastography', 'فیبرواسکن', 'الاستشعار المرن'],
                'same_as' => [],
            ],

            // --- Symptoms ------------------------------------------------------
            'Abdominal pain' => [
                'type'    => EntityType::SYMPTOM,
                'aliases' => ['stomach pain', 'درد شکم', 'ألم البطن'],
                'same_as' => ['https://www.wikidata.org/wiki/Q201989'],
            ],
            'Nausea' => [
                'type'    => EntityType::SYMPTOM,
                'aliases' => ['تهوع', 'حالت تهوع', 'الغثيان'],
                'same_as' => ['https://www.wikidata.org/wiki/Q42982'],
            ],
            'Jaundice' => [
                'type'    => EntityType::SYMPTOM,
                'aliases' => ['یرقان', 'زردی', 'اليرقان'],
                'same_as' => ['https://www.wikidata.org/wiki/Q101991'],
            ],

            // --- Specialties -----------------------------------------------------
            'Gastroenterology' => [
                'type'    => EntityType::MEDICAL_SPECIALTY,
                'aliases' => ['گوارش', 'متخصص گوارش', 'أمراض الجهاز الهضمي'],
                'same_as' => ['https://www.wikidata.org/wiki/Q206567'],
            ],
            'Hepatology' => [
                'type'    => EntityType::MEDICAL_SPECIALTY,
                'aliases' => ['کبد', 'متخصص کبد', 'أمراض الكبد'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1622272'],
            ],
            'Cardiology' => [
                'type'    => EntityType::MEDICAL_SPECIALTY,
                'aliases' => ['قلب و عروق', 'أمراض القلب'],
                'same_as' => ['https://www.wikidata.org/wiki/Q10379'],
            ],
            'Dermatology' => [
                'type'    => EntityType::MEDICAL_SPECIALTY,
                'aliases' => ['پوست', 'پوست و مو', 'الأمراض الجلدية'],
                'same_as' => ['https://www.wikidata.org/wiki/Q171171'],
            ],
        ];

        /**
         * Extend or replace the seed clinical vocabulary.
         *
         * @param array<string, array{type: string, aliases: list<string>, same_as: list<string>}> $terms
         */
        return (array) apply_filters('medora_medical_ontology', $terms);
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $counts = [];

        foreach ($this->terms() as $definition) {
            $type          = (string) $definition['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }
}
