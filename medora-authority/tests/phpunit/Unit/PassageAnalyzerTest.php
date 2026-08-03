<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Geo\PassageAnalyzer;
use Medora\Authority\Support\Chunker;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * These lints run over customer prose and appear in the fix list, so the cost
 * of a false positive is high: a check that fires on correct writing gets
 * ignored, and then so do the checks around it. Roughly half the cases below
 * assert that something is *not* flagged, for that reason.
 */
final class PassageAnalyzerTest extends TestCase
{
    private PassageAnalyzer $analyzer;

    /** Filler long enough to clear the 40-word floor without saying anything. */
    private const FILLER = ' The condition is managed with dietary change and regular '
        . 'monitoring by a clinician who reviews liver enzyme results every three months '
        . 'and adjusts treatment when the readings move outside the expected range.';

    private const FILLER_FA = ' درمان این وضعیت با تغییر رژیم غذایی و پایش منظم آنزیم های '
        . 'کبدی توسط پزشک انجام می شود و هر سه ماه یک بار نتایج بررسی و دارو در صورت نیاز '
        . 'تنظیم می شود تا بیمار در محدوده مورد انتظار باقی بماند.';

    protected function setUp(): void
    {
        $this->analyzer = new PassageAnalyzer(new Chunker());
    }

    public function testProseThatNamesItsSubjectIsClean(): void
    {
        $report = $this->analyze(
            'Fatty liver disease',
            '<h2>What is fatty liver disease?</h2><p>Fatty liver disease is the '
            . 'accumulation of fat in liver cells.' . self::FILLER . '</p>'
        );

        $this->assertSame([], $this->codes($report));
        $this->assertSame(1, $report['clean']);
        $this->assertSame(1.0, $report['ratio']);
    }

    public function testSentenceInitialConnectiveIsFlagged(): void
    {
        $report = $this->analyze(
            'Fatty liver disease',
            '<h2>Treatment</h2><p>However, that is rarely the whole picture for a '
            . 'patient whose fatty liver disease has progressed.' . self::FILLER . '</p>'
        );

        $this->assertContains('dangling_connective', $this->codes($report));
    }

    public function testStandaloneDemonstrativeIsFlagged(): void
    {
        $report = $this->analyze(
            'Fatty liver disease',
            '<h2>Treatment</h2><p>This is why the numbers matter when a patient with '
            . 'fatty liver disease is first assessed.' . self::FILLER . '</p>'
        );

        $this->assertContains('dangling_pronoun', $this->codes($report));
    }

    public function testDemonstrativeIntroducingANounIsNotFlagged(): void
    {
        // "This disease…" names its own subject; the passage survives retrieval.
        $report = $this->analyze(
            'Fatty liver disease',
            '<h2>Treatment</h2><p>This disease is diagnosed by ultrasound in most '
            . 'patients.' . self::FILLER . '</p>'
        );

        $this->assertNotContains('dangling_pronoun', $this->codes($report));
    }

    public function testBarePronounIsFlaggedWhateverFollowsIt(): void
    {
        // "It" cannot introduce a noun, so unlike "this" it never anchors.
        $report = $this->analyze(
            'Fatty liver disease',
            '<h2>Treatment</h2><p>It affects roughly a quarter of adults worldwide '
            . 'according to recent estimates.' . self::FILLER . '</p>'
        );

        $this->assertContains('dangling_pronoun', $this->codes($report));
    }

    public function testHeadingIsNotMistakenForTheOpeningSentence(): void
    {
        // The chunker prepends the heading to the chunk body. If that were read
        // as the opening sentence, no dangling opener anywhere would ever fire.
        $report = $this->analyze(
            'Fatty liver disease',
            '<h2>Treatment</h2><p>Therefore the dose is reduced once enzyme levels '
            . 'return to normal.' . self::FILLER . '</p>'
        );

        $this->assertContains('dangling_connective', $this->codes($report));
    }

    public function testPersianDemonstrativeWithANounIsNotFlagged(): void
    {
        $report = $this->analyze(
            'کبد چرب',
            '<h2>درمان کبد چرب</h2><p>این بیماری با سونوگرافی تشخیص داده می شود.'
            . self::FILLER_FA . '</p>'
        );

        $this->assertNotContains('dangling_pronoun', $this->codes($report));
    }

    public function testPersianStandaloneDemonstrativeIsFlagged(): void
    {
        $report = $this->analyze(
            'کبد چرب',
            '<h2>درمان کبد چرب</h2><p>این است که پایش منظم اهمیت زیادی دارد.'
            . self::FILLER_FA . '</p>'
        );

        $this->assertContains('dangling_pronoun', $this->codes($report));
    }

    public function testPersianConnectiveIsFlagged(): void
    {
        $report = $this->analyze(
            'کبد چرب',
            '<h2>درمان کبد چرب</h2><p>اما همیشه این طور نیست که بیماری پیشرفت کند.'
            . self::FILLER_FA . '</p>'
        );

        $this->assertContains('dangling_connective', $this->codes($report));
    }

    public function testReferenceToAnotherPartOfThePageIsFlagged(): void
    {
        $report = $this->analyze(
            'Fatty liver disease',
            '<h2>Treatment</h2><p>Fatty liver disease responds to the changes listed '
            . 'in the table above for most patients.' . self::FILLER . '</p>'
        );

        $this->assertContains('page_reference', $this->codes($report));
    }

    public function testPersianReferenceToAnotherPartOfThePageIsFlagged(): void
    {
        $report = $this->analyze(
            'کبد چرب',
            '<h2>درمان کبد چرب</h2><p>روش های درمان کبد چرب در ادامه بررسی می شود.'
            . self::FILLER_FA . '</p>'
        );

        $this->assertContains('page_reference', $this->codes($report));
    }

    public function testPassageThatNeverNamesTheSubjectIsFlagged(): void
    {
        $report = $this->analyze(
            'Fatty liver disease',
            '<h2>Insurance</h2><p>Most policies reimburse the consultation fee when a '
            . 'referral letter is supplied by the general practitioner ahead of the '
            . 'appointment, and the balance is invoiced directly afterwards.</p>'
        );

        $this->assertContains('subject_absent', $this->codes($report));
    }

    public function testUnheadedFragmentIsFlaggedTwice(): void
    {
        $report = $this->analyze('Fatty liver disease', '<p>Short intro paragraph.</p>');

        $this->assertContains('no_heading', $this->codes($report));
        $this->assertContains('too_short', $this->codes($report));
    }

    public function testEmptyContentProducesNoPassages(): void
    {
        $report = $this->analyze('Fatty liver disease', '');

        $this->assertSame(0, $report['total']);
        $this->assertSame(0.0, $report['ratio']);
    }

    /**
     * @return array<string, mixed>
     */
    private function analyze(string $title, string $content): array
    {
        $post = new WP_Post((object) [
            'ID'           => 1,
            'post_title'   => $title,
            'post_content' => $content,
        ]);

        return $this->analyzer->analyze($post);
    }

    /**
     * Issue codes for the first passage.
     *
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private function codes(array $report): array
    {
        return array_map(
            static fn (array $issue): string => $issue['code'],
            $report['passages'][0]['issues'] ?? []
        );
    }
}
