<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Llm\Grounding;
use PHPUnit\Framework\TestCase;

/**
 * The check that decides whether model output is allowed to be published.
 *
 * Everything the AI Writer ships passes through here, so the cases below are
 * written as the failure they are meant to prevent: a fabricated dose, an
 * invented percentage, a paragraph about a different subject. The numeric
 * cases carry the most weight — a single wrong figure on a clinical page is
 * the concrete harm this whole module is built around.
 */
final class GroundingTest extends TestCase
{
    private const SOURCE = 'Magnesium glycinate is a chelated form of magnesium. '
        . 'Clinical trials used doses of 300 mg per day for adults. '
        . 'Absorption is higher than magnesium oxide. '
        . 'Side effects are uncommon but include mild diarrhoea.';

    public function testAcceptsAParaphraseBuiltFromThePageOwnVocabulary(): void
    {
        $result = Grounding::check(
            'Magnesium glycinate is a chelated magnesium with higher absorption '
            . 'than magnesium oxide. Trials used 300 mg per day.',
            self::SOURCE
        );

        $this->assertTrue($result['supported']);
        $this->assertSame([], $result['invented_numbers']);
    }

    public function testRejectsAFabricatedDose(): void
    {
        $result = Grounding::check(
            'Magnesium glycinate is a chelated magnesium. Trials used 600 mg per day.',
            self::SOURCE
        );

        $this->assertFalse($result['supported']);
        $this->assertSame(['600'], $result['invented_numbers']);
    }

    public function testRejectsAnInventedPercentage(): void
    {
        $result = Grounding::check(
            'Magnesium glycinate reduces blood pressure by 45 percent in hypertensive patients.',
            self::SOURCE
        );

        $this->assertFalse($result['supported']);
    }

    public function testRejectsAParagraphAboutSomethingElseAndNamesTheSentence(): void
    {
        $result = Grounding::check(
            'The quarterly revenue forecast depends on regional distribution partnerships.',
            self::SOURCE
        );

        $this->assertFalse($result['supported']);
        $this->assertCount(1, $result['unsupported_sentences']);
    }

    public function testOneBadSentenceAmongGoodOnesStillFails(): void
    {
        $result = Grounding::check(
            'Magnesium glycinate is a chelated form of magnesium with higher absorption. '
            . 'Quarterly logistics throughput improved across warehouse partners nationwide.',
            self::SOURCE
        );

        $this->assertFalse($result['supported']);
        $this->assertCount(1, $result['unsupported_sentences']);
    }

    public function testEmptyOutputIsVacuouslySupported(): void
    {
        // An empty answer is a correct answer to a question the page does not
        // address. Callers gate on emptiness separately.
        $this->assertTrue(Grounding::check('', self::SOURCE)['supported']);
    }

    /**
     * @dataProvider equivalentFigures
     */
    public function testFiguresAreComparedAfterNormalisation(string $generated, string $source): void
    {
        $this->assertSame([], Grounding::check($generated, $source)['invented_numbers']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function equivalentFigures(): array
    {
        return [
            'persian digits'          => ['Treated 1500 patients since 2019.', 'Treated ۱۵۰۰ patients since ۲۰۱۹.'],
            'persian thousands mark'  => ['Treated 1500 patients.', 'Treated ۱٬۵۰۰ patients.'],
            'persian decimal mark'    => ['A session lasts 2.5 hours.', 'A session lasts ۲٫۵ hours.'],
            'arabic-indic digits'     => ['Treated 2019 patients.', 'Treated ٢٠١٩ patients.'],
            'ascii thousands mark'    => ['Treated 1,500 patients.', 'Treated 1500 patients.'],
            'trailing decimal zero'   => ['A session lasts 2.50 hours.', 'A session lasts 2.5 hours.'],
            'leading zeros'           => ['Treated 007 patients.', 'Treated 7 patients.'],
        ];
    }

    public function testADifferentFigureIsStillCaughtAfterNormalisation(): void
    {
        $result = Grounding::check('A session lasts 3 hours.', 'A session lasts ۲٫۵ hours.');

        $this->assertSame(['3'], $result['invented_numbers']);
    }

    public function testPersianParaphraseIsAccepted(): void
    {
        $source = 'کم‌خونی فقر آهن شایع‌ترین کم‌خونی در ایران است. '
            . 'درمان آن با مکمل آهن خوراکی انجام می‌شود. '
            . 'آزمایش فریتین برای تشخیص لازم است.';

        $result = Grounding::check(
            'کم‌خونی فقر آهن شایع‌ترین کم‌خونی است و درمان آن با مکمل آهن خوراکی انجام می‌شود.',
            $source
        );

        $this->assertTrue($result['supported']);
    }

    public function testPersianOutputAboutADifferentSubjectIsRejected(): void
    {
        $source = 'کم‌خونی فقر آهن شایع‌ترین کم‌خونی در ایران است. '
            . 'درمان آن با مکمل آهن خوراکی انجام می‌شود.';

        $result = Grounding::check(
            'تزریق آمپول ویتامین ب۱۲ هر هفته برای بیماران دیابتی توصیه می‌شود.',
            $source
        );

        $this->assertFalse($result['supported']);
    }

    public function testArabicAndPersianLetterFormsAreTheSameWord(): void
    {
        // The page writes ي and ی inconsistently, as real Persian copy does.
        // Comparison runs on normalised tokens, so the spelling difference
        // must not read as new vocabulary.
        $result = Grounding::check('علی احمدي متخصص است.', 'علي احمدی متخصص قلب است.');

        $this->assertTrue($result['supported']);
    }
}
