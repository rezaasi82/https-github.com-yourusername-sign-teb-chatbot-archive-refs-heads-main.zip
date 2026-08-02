<?php

declare(strict_types=1);

namespace Medora\Authority\Medical;

use Medora\Authority\Entity\EntityType;
use Medora\Authority\Graph\RelationType;

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

            'Cirrhosis' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['سیروز', 'سیروز کبدی', 'تشمع الكبد'],
                'same_as' => ['https://www.wikidata.org/wiki/Q147778'],
            ],
            'Hepatitis C' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['هپاتیت سی', 'التهاب الكبد سي'],
                'same_as' => ['https://www.wikidata.org/wiki/Q154869'],
            ],
            'Celiac disease' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['coeliac disease', 'بیماری سلیاک', 'الداء البطني'],
                'same_as' => ['https://www.wikidata.org/wiki/Q212961'],
            ],
            'Ulcerative colitis' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['کولیت اولسراتیو', 'التهاب القولون التقرحي'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1077505'],
            ],
            'Gallstones' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['cholelithiasis', 'سنگ کیسه صفرا', 'حصوات المرارة'],
                'same_as' => ['https://www.wikidata.org/wiki/Q815819'],
            ],
            'Pancreatitis' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['پانکراتیت', 'التهاب البنكرياس'],
                'same_as' => ['https://www.wikidata.org/wiki/Q193782'],
            ],
            'Helicobacter pylori infection' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['H. pylori', 'هلیکوباکتر پیلوری', 'جرثومة المعدة'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1069984'],
            ],
            'Obesity' => [
                'type'    => EntityType::MEDICAL_CONDITION,
                'aliases' => ['چاقی', 'اضافه وزن', 'السمنة'],
                'same_as' => ['https://www.wikidata.org/wiki/Q12174'],
            ],

            // --- Procedures ---------------------------------------------------
            // Note: Persian aliases are folded by Text::normalize (آ → ا, ي → ی),
            // so listing both spellings of one word would be a dead entry.
            'Endoscopy' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['gastroscopy', 'آندوسکوپی', 'التنظير'],
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
            'ERCP' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['endoscopic retrograde cholangiopancreatography', 'ای آر سی پی', 'تصوير البنكرياس'],
                'same_as' => ['https://www.wikidata.org/wiki/Q901269'],
            ],
            'Abdominal ultrasound' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['سونوگرافی شکم', 'الموجات فوق الصوتية للبطن'],
                'same_as' => [],
            ],
            'Sleeve gastrectomy' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['gastric sleeve', 'اسلیو معده', 'تكميم المعدة'],
                'same_as' => ['https://www.wikidata.org/wiki/Q7541645'],
            ],
            'Cholecystectomy' => [
                'type'    => EntityType::MEDICAL_PROCEDURE,
                'aliases' => ['gallbladder removal', 'جراحی کیسه صفرا', 'استئصال المرارة'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1062193'],
            ],

            // --- Drugs -----------------------------------------------------------
            'Proton pump inhibitor' => [
                'type'    => EntityType::DRUG,
                'aliases' => ['omeprazole', 'pantoprazole', 'مهارکننده پمپ پروتون', 'امپرازول', 'مثبطات مضخة البروتون'],
                'same_as' => ['https://www.wikidata.org/wiki/Q413565'],
            ],
            'Metformin' => [
                'type'    => EntityType::DRUG,
                'aliases' => ['متفورمین', 'ميتفورمين'],
                'same_as' => ['https://www.wikidata.org/wiki/Q19484'],
            ],
            'Ursodeoxycholic acid' => [
                'type'    => EntityType::DRUG,
                'aliases' => ['ursodiol', 'اورسودیول', 'حمض أورسوديوكسيكوليك'],
                'same_as' => ['https://www.wikidata.org/wiki/Q413672'],
            ],

            // --- Anatomy -----------------------------------------------------------
            'Liver' => [
                'type'    => EntityType::ANATOMY,
                'aliases' => ['کبد', 'جگر', 'الكبد'],
                'same_as' => ['https://www.wikidata.org/wiki/Q9368'],
            ],
            'Stomach' => [
                'type'    => EntityType::ANATOMY,
                'aliases' => ['معده', 'المعدة'],
                'same_as' => ['https://www.wikidata.org/wiki/Q9603'],
            ],
            'Colon' => [
                'type'    => EntityType::ANATOMY,
                'aliases' => ['large intestine', 'روده بزرگ', 'القولون'],
                'same_as' => ['https://www.wikidata.org/wiki/Q2005732'],
            ],
            'Pancreas' => [
                'type'    => EntityType::ANATOMY,
                'aliases' => ['پانکراس', 'لوزالمعده', 'البنكرياس'],
                'same_as' => ['https://www.wikidata.org/wiki/Q9614'],
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
            'Heartburn' => [
                'type'    => EntityType::SYMPTOM,
                'aliases' => ['acid reflux symptom', 'سوزش سر دل', 'ترشی', 'حرقة المعدة'],
                'same_as' => ['https://www.wikidata.org/wiki/Q506274'],
            ],
            'Bloating' => [
                'type'    => EntityType::SYMPTOM,
                'aliases' => ['نفخ', 'نفخ شکم', 'انتفاخ البطن'],
                'same_as' => ['https://www.wikidata.org/wiki/Q1058607'],
            ],
            'Chronic diarrhoea' => [
                'type'    => EntityType::SYMPTOM,
                'aliases' => ['chronic diarrhea', 'اسهال مزمن', 'الإسهال المزمن'],
                'same_as' => [],
            ],
            'Fatigue' => [
                'type'    => EntityType::SYMPTOM,
                'aliases' => ['خستگی', 'ضعف و بی حالی', 'التعب'],
                'same_as' => ['https://www.wikidata.org/wiki/Q186005'],
            ],

            // --- Specialties -----------------------------------------------------
            'Gastroenterology' => [
                'type'    => EntityType::MEDICAL_SPECIALTY,
                'aliases' => ['گوارش', 'متخصص گوارش', 'أمراض الجهاز الهضمي'],
                'same_as' => ['https://www.wikidata.org/wiki/Q206567'],
            ],
            'Hepatology' => [
                'type'    => EntityType::MEDICAL_SPECIALTY,
                // Deliberately not the bare "کبد": in Persian that is the organ,
                // which belongs to the anatomy term. Claiming it here would make
                // every mention of the liver look like a mention of the
                // specialty.
                'aliases' => ['متخصص کبد', 'بیماری های کبد', 'أمراض الكبد'],
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

    /**
     * Curated clinical relations between ontology terms.
     *
     * This is the disease / treatment / drug graph. It is deliberately
     * *declared*, never inferred: co-occurrence can tell you two things are
     * discussed together, but only a human can assert that one treats the
     * other. A wrong "is a treatment for" edge is machine-readable
     * misinformation, so these are held to a different standard than the
     * inferred layer and are written with `source = 'ontology'` so a graph
     * rebuild never touches them.
     *
     * Subjects and objects reference terms by their canonical name.
     *
     * @return list<array{subject: string, predicate: string, object: string, weight?: float}>
     */
    public function relations(): array
    {
        $relations = [
            // --- Gastroenterology / hepatology ---------------------------
            ['subject' => 'Endoscopy', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Gastroesophageal reflux disease'],
            ['subject' => 'Endoscopy', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Peptic ulcer'],
            ['subject' => 'Colonoscopy', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => "Crohn's disease"],
            ['subject' => 'Liver biopsy', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Fatty liver disease'],
            ['subject' => 'FibroScan', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Fatty liver disease'],
            ['subject' => 'FibroScan', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Hepatitis B'],

            ['subject' => 'Abdominal pain', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Irritable bowel syndrome'],
            ['subject' => 'Abdominal pain', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Peptic ulcer'],
            ['subject' => 'Abdominal pain', 'predicate' => RelationType::SYMPTOM_OF, 'object' => "Crohn's disease"],
            ['subject' => 'Nausea', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Gastroesophageal reflux disease'],
            ['subject' => 'Nausea', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Peptic ulcer'],
            ['subject' => 'Jaundice', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Hepatitis B'],
            ['subject' => 'Jaundice', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Fatty liver disease'],

            ['subject' => 'Heartburn', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Gastroesophageal reflux disease'],
            ['subject' => 'Bloating', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Irritable bowel syndrome'],
            ['subject' => 'Bloating', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Celiac disease'],
            ['subject' => 'Chronic diarrhoea', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Ulcerative colitis'],
            ['subject' => 'Chronic diarrhoea', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Celiac disease'],
            ['subject' => 'Fatigue', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Fatty liver disease'],
            ['subject' => 'Fatigue', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Hepatitis C'],
            ['subject' => 'Jaundice', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Cirrhosis'],
            ['subject' => 'Abdominal pain', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Pancreatitis'],
            ['subject' => 'Abdominal pain', 'predicate' => RelationType::SYMPTOM_OF, 'object' => 'Gallstones'],

            // --- Diagnostics -------------------------------------------------
            ['subject' => 'Colonoscopy', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Ulcerative colitis'],
            ['subject' => 'Endoscopy', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Celiac disease'],
            ['subject' => 'Endoscopy', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Helicobacter pylori infection'],
            ['subject' => 'Abdominal ultrasound', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Gallstones'],
            ['subject' => 'Abdominal ultrasound', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Fatty liver disease'],
            ['subject' => 'ERCP', 'predicate' => RelationType::DIAGNOSED_BY, 'object' => 'Gallstones'],

            // --- Treatments ----------------------------------------------------
            ['subject' => 'Cholecystectomy', 'predicate' => RelationType::TREATS, 'object' => 'Gallstones'],
            ['subject' => 'Sleeve gastrectomy', 'predicate' => RelationType::TREATS, 'object' => 'Obesity'],
            ['subject' => 'Proton pump inhibitor', 'predicate' => RelationType::DRUG_FOR, 'object' => 'Gastroesophageal reflux disease'],
            ['subject' => 'Proton pump inhibitor', 'predicate' => RelationType::DRUG_FOR, 'object' => 'Peptic ulcer'],
            ['subject' => 'Metformin', 'predicate' => RelationType::DRUG_FOR, 'object' => 'Diabetes mellitus'],
            ['subject' => 'Ursodeoxycholic acid', 'predicate' => RelationType::DRUG_FOR, 'object' => 'Gallstones'],

            // --- Risk factors and complications ----------------------------------
            ['subject' => 'Diabetes mellitus', 'predicate' => RelationType::RISK_FACTOR, 'object' => 'Fatty liver disease'],
            ['subject' => 'Hypertension', 'predicate' => RelationType::RISK_FACTOR, 'object' => 'Fatty liver disease'],
            ['subject' => 'Obesity', 'predicate' => RelationType::RISK_FACTOR, 'object' => 'Fatty liver disease'],
            ['subject' => 'Obesity', 'predicate' => RelationType::RISK_FACTOR, 'object' => 'Gastroesophageal reflux disease'],
            ['subject' => 'Helicobacter pylori infection', 'predicate' => RelationType::RISK_FACTOR, 'object' => 'Peptic ulcer'],
            ['subject' => 'Gallstones', 'predicate' => RelationType::RISK_FACTOR, 'object' => 'Pancreatitis'],
            ['subject' => 'Cirrhosis', 'predicate' => RelationType::COMPLICATION_OF, 'object' => 'Hepatitis C'],
            ['subject' => 'Cirrhosis', 'predicate' => RelationType::COMPLICATION_OF, 'object' => 'Fatty liver disease'],
            ['subject' => 'Cirrhosis', 'predicate' => RelationType::COMPLICATION_OF, 'object' => 'Hepatitis B'],

            // --- Anatomy ------------------------------------------------------------
            ['subject' => 'Fatty liver disease', 'predicate' => RelationType::AFFECTS_ANATOMY, 'object' => 'Liver'],
            ['subject' => 'Cirrhosis', 'predicate' => RelationType::AFFECTS_ANATOMY, 'object' => 'Liver'],
            ['subject' => 'Hepatitis B', 'predicate' => RelationType::AFFECTS_ANATOMY, 'object' => 'Liver'],
            ['subject' => 'Hepatitis C', 'predicate' => RelationType::AFFECTS_ANATOMY, 'object' => 'Liver'],
            ['subject' => 'Peptic ulcer', 'predicate' => RelationType::AFFECTS_ANATOMY, 'object' => 'Stomach'],
            ['subject' => 'Ulcerative colitis', 'predicate' => RelationType::AFFECTS_ANATOMY, 'object' => 'Colon'],
            ['subject' => 'Irritable bowel syndrome', 'predicate' => RelationType::AFFECTS_ANATOMY, 'object' => 'Colon'],
            ['subject' => 'Pancreatitis', 'predicate' => RelationType::AFFECTS_ANATOMY, 'object' => 'Pancreas'],

            // --- Specialty routing -----------------------------------------
            ['subject' => 'Fatty liver disease', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Hepatology'],
            ['subject' => 'Hepatitis B', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Hepatology'],
            ['subject' => 'Gastroesophageal reflux disease', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Gastroenterology'],
            ['subject' => 'Irritable bowel syndrome', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Gastroenterology'],
            ['subject' => "Crohn's disease", 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Gastroenterology'],
            ['subject' => 'Peptic ulcer', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Gastroenterology'],
            ['subject' => 'Celiac disease', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Gastroenterology'],
            ['subject' => 'Ulcerative colitis', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Gastroenterology'],
            ['subject' => 'Gallstones', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Gastroenterology'],
            ['subject' => 'Pancreatitis', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Gastroenterology'],
            ['subject' => 'Cirrhosis', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Hepatology'],
            ['subject' => 'Hepatitis C', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Hepatology'],
            ['subject' => 'Hypertension', 'predicate' => RelationType::SPECIALTY_OF, 'object' => 'Cardiology'],
        ];

        /**
         * Extend the curated clinical graph.
         *
         * Every subject and object must name a term that exists in the
         * ontology; `MedicalGraph` skips and reports anything that does not, so
         * a typo degrades one edge rather than corrupting the graph.
         *
         * @param list<array{subject: string, predicate: string, object: string, weight?: float}> $relations
         */
        return (array) apply_filters('medora_medical_relations', $relations);
    }

    /**
     * Relations whose endpoints both resolve to a known term.
     *
     * @return array{valid: list<array<string, mixed>>, unresolved: list<array<string, mixed>>}
     */
    public function validatedRelations(): array
    {
        $terms      = $this->terms();
        $valid      = [];
        $unresolved = [];

        foreach ($this->relations() as $relation) {
            $subject   = (string) ($relation['subject'] ?? '');
            $object    = (string) ($relation['object'] ?? '');
            $predicate = (string) ($relation['predicate'] ?? '');

            $known = isset($terms[$subject], $terms[$object]) && RelationType::isValid($predicate);

            if ($known) {
                $valid[] = $relation;
            } else {
                $unresolved[] = $relation;
            }
        }

        return ['valid' => $valid, 'unresolved' => $unresolved];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $counts = [];

        foreach ($this->terms() as $definition) {
            $type          = (string) $definition['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }
}
