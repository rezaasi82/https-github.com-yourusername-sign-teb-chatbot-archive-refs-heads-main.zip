<?php

declare(strict_types=1);

namespace Medora\Authority\Eeat;

use Medora\Authority\Score\Deduction;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Scores an author profile against the four E-E-A-T pillars.
 *
 * Experience and Expertise are separated deliberately: a practising clinician
 * with no publications and a researcher who has never seen a patient are
 * strong in different ways, and content is best served by making both visible
 * rather than collapsing them into one "authority" number.
 */
final class TrustScorer
{
    /**
     * @return array{
     *     score: float,
     *     experience: float,
     *     expertise: float,
     *     authoritativeness: float,
     *     trustworthiness: float,
     *     gaps: list<array<string, mixed>>
     * }
     */
    public function score(AuthorProfile $profile): array
    {
        $gaps = [];

        // --- Experience: has this person actually done the thing? -----------
        $experience = 0.0;
        $years      = (int) $profile->get('_medora_years_active');

        if ($years > 0) {
            $experience += min(50.0, $years * 5.0);
        } else {
            $gaps[] = (new Deduction(
                'no_experience',
                __('No years of practice recorded', 'medora-authority'),
                20,
                __('State how long the author has practised. First-hand experience is a distinct signal from qualifications.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM,
                'author-profile'
            ))->toArray();
        }

        if ($profile->publishedPosts >= 10) {
            $experience += 30.0;
        } elseif ($profile->publishedPosts > 0) {
            $experience += $profile->publishedPosts * 3.0;
        }

        if ($profile->bio !== '') {
            $experience += 20.0;
        } else {
            $gaps[] = (new Deduction(
                'no_bio',
                __('No author biography', 'medora-authority'),
                15,
                __('Write a biography that names what the author does and where.', 'medora-authority'),
                Deduction::SEVERITY_HIGH,
                'author-profile'
            ))->toArray();
        }

        // --- Expertise: formal qualification ---------------------------------
        $expertise = 0.0;

        if ($profile->has('_medora_credentials')) {
            $expertise += 45.0;
        } else {
            $gaps[] = (new Deduction(
                'no_credentials',
                __('No credentials listed', 'medora-authority'),
                30,
                __('Add the author\'s qualification (MD, PhD, RN…).', 'medora-authority'),
                Deduction::SEVERITY_CRITICAL,
                'author-profile'
            ))->toArray();
        }

        if ($profile->has('_medora_education')) {
            $expertise += 25.0;
        }

        if ($profile->has('_medora_license_no')) {
            $expertise += 30.0;
        } else {
            $gaps[] = (new Deduction(
                'no_license',
                __('No professional licence number', 'medora-authority'),
                15,
                __('A licence number is independently checkable, which is exactly what makes it a trust signal.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM,
                'author-profile'
            ))->toArray();
        }

        // --- Authoritativeness: does the wider field recognise them? ----------
        $profiles          = $profile->verifiableProfiles();
        $authoritativeness = min(70.0, count($profiles) * 25.0);

        if ($profile->has('_medora_affiliation')) {
            $authoritativeness += 30.0;
        } else {
            $gaps[] = (new Deduction(
                'no_affiliation',
                __('No institutional affiliation', 'medora-authority'),
                15,
                __('Name the hospital, clinic or university the author is attached to.', 'medora-authority'),
                Deduction::SEVERITY_MEDIUM,
                'author-profile'
            ))->toArray();
        }

        if ($profiles === []) {
            $gaps[] = (new Deduction(
                'no_external_profile',
                __('No externally verifiable profile', 'medora-authority'),
                25,
                __('Link an ORCID, Google Scholar or ResearchGate profile. Without one, the author cannot be verified to exist.', 'medora-authority'),
                Deduction::SEVERITY_HIGH,
                'author-profile'
            ))->toArray();
        }

        // --- Trustworthiness: the aggregate of the above ------------------------
        $trustworthiness = ($expertise * 0.4) + ($authoritativeness * 0.4) + ($experience * 0.2);

        $overall = (min(100.0, $experience) * 0.20)
            + (min(100.0, $expertise) * 0.30)
            + (min(100.0, $authoritativeness) * 0.25)
            + (min(100.0, $trustworthiness) * 0.25);

        return [
            'score'             => round(min(100.0, $overall), 1),
            'experience'        => round(min(100.0, $experience), 1),
            'expertise'         => round(min(100.0, $expertise), 1),
            'authoritativeness' => round(min(100.0, $authoritativeness), 1),
            'trustworthiness'   => round(min(100.0, $trustworthiness), 1),
            'gaps'              => $gaps,
        ];
    }
}
