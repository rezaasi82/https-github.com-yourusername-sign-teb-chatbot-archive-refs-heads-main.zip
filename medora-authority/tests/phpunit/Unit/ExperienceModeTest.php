<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Admin\ExperienceMode;
use Medora\Authority\Core\Options;
use PHPUnit\Framework\TestCase;

/**
 * Experience mode decides how much of the dashboard is on screen.
 *
 * Two properties matter more than the specific feature assignments, and both
 * are easy to break by editing the feature table casually:
 *
 * 1. **The beginner loop is complete.** See the score, see what is wrong, fix
 *    it, change the settings that affect it. A mode that hides part of that
 *    leaves someone stuck with no path forward.
 * 2. **Modes are monotonic.** Moving up a mode never takes a screen away,
 *    otherwise "switch to Agency" can lose someone the panel they were using.
 *
 * It is presentation, never authorisation — nothing here is a security
 * boundary, which is why the unknown-feature case shows rather than hides.
 */
final class ExperienceModeTest extends TestCase
{
    protected function tearDown(): void
    {
        unset( $GLOBALS['medora_test_options'] );
    }

    /**
     * @return list<string>
     */
    public static function beginnerLoop(): array
    {
        return ['overview', 'content', 'settings', 'crawlers', 'fixes', 'brief'];
    }

    public function testBeginnerKeepsTheWholeWorkingLoop(): void
    {
        $mode = $this->mode(ExperienceMode::BEGINNER);

        foreach (self::beginnerLoop() as $feature) {
            $this->assertTrue($mode->shows($feature), $feature . ' is part of the beginner loop');
        }
    }

    public function testBeginnerHidesTheAnalyticalSurfaces(): void
    {
        $mode = $this->mode(ExperienceMode::BEGINNER);

        $this->assertFalse($mode->shows('entities'));
        $this->assertFalse($mode->shows('graph'));
        $this->assertFalse($mode->shows('modules'));
        $this->assertFalse($mode->shows('audit_log'));
    }

    public function testProfessionalAddsExplanationButNotOperations(): void
    {
        $mode = $this->mode(ExperienceMode::PROFESSIONAL);

        $this->assertTrue($mode->shows('entities'));
        $this->assertTrue($mode->shows('passages'));
        $this->assertFalse($mode->shows('graph'));
        $this->assertFalse($mode->shows('audit_log'));
    }

    public function testAgencyAddsOperationsButNotCompliance(): void
    {
        $mode = $this->mode(ExperienceMode::AGENCY);

        $this->assertTrue($mode->shows('graph'));
        $this->assertTrue($mode->shows('modules'));
        $this->assertFalse($mode->shows('audit_log'));
    }

    public function testEnterpriseShowsEverything(): void
    {
        $features = $this->mode(ExperienceMode::ENTERPRISE)->all();

        $this->assertNotEmpty($features);
        $this->assertSame([], array_keys(array_filter($features, static fn (bool $shown): bool => ! $shown)));
    }

    public function testModesAreMonotonic(): void
    {
        $previous = null;

        foreach ([ExperienceMode::BEGINNER, ExperienceMode::PROFESSIONAL, ExperienceMode::AGENCY, ExperienceMode::ENTERPRISE] as $id) {
            $current = $this->mode($id)->all();

            if ($previous !== null) {
                foreach ($previous as $feature => $shown) {
                    if ($shown) {
                        $this->assertTrue($current[$feature], $id . ' must keep ' . $feature);
                    }
                }
            }

            $previous = $current;
        }
    }

    public function testUnknownFeatureIsShown(): void
    {
        // Failing open is right for a presentational filter: a third-party
        // screen that never registered itself should appear, not vanish.
        $this->assertTrue($this->mode(ExperienceMode::BEGINNER)->shows('some_addon_screen'));
    }

    /**
     * @dataProvider invalidModes
     */
    public function testAnInvalidStoredModeFallsBackToBeginner(string $stored): void
    {
        $this->assertSame(ExperienceMode::BEGINNER, $this->mode($stored)->current());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidModes(): array
    {
        return [
            'empty'    => [''],
            'unknown'  => ['nonsense'],
            'cased'    => ['Beginner'],
        ];
    }

    public function testChoicesDriveTheWizard(): void
    {
        $choices = ExperienceMode::choices();

        $this->assertSame(
            [ExperienceMode::BEGINNER, ExperienceMode::PROFESSIONAL, ExperienceMode::AGENCY, ExperienceMode::ENTERPRISE],
            array_column($choices, 'id')
        );

        foreach ($choices as $choice) {
            $this->assertNotSame('', $choice['label']);
            $this->assertNotSame('', $choice['description']);
        }
    }

    private function mode(string $stored): ExperienceMode
    {
        $GLOBALS['medora_test_options'] = [Options::OPTION_KEY => ['experience_mode' => $stored]];

        $mode = new ExperienceMode(new Options());

        // Options caches on first read; warming it pins this instance to the
        // value set above rather than to whatever a later test writes.
        $mode->current();

        return $mode;
    }
}
