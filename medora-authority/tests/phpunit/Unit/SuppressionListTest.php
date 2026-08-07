<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Core\Options;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\SuppressionList;
use PHPUnit\Framework\TestCase;

/**
 * The list that makes entity deletion mean something.
 *
 * Extraction is deterministic, so deleting an entity without recording the
 * decision puts it back on the next analysis. This is what turns a delete from
 * a gesture into an instruction — which is why it must be idempotent, bounded,
 * reversible, and tolerant of whatever ends up in the option.
 */
final class SuppressionListTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['medora_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['medora_test_options']);
    }

    public function testStartsEmpty(): void
    {
        $list = $this->list();

        $this->assertSame(0, $list->count());
        $this->assertSame([], $list->all());
    }

    public function testSuppressRecordsTheEntityForReview(): void
    {
        $list = $this->list();
        $junk = $this->entity('Read More', 'Organization');

        $this->assertTrue($list->suppress($junk));
        $this->assertTrue($list->isSuppressed($junk->uid));

        // The name and type are stored alongside the uid purely so a person can
        // read the list. A page of bare hashes cannot be reviewed, and
        // reviewing it is the point.
        $row = $list->all()[0];

        $this->assertSame('Read More', $row['name']);
        $this->assertSame('Organization', $row['type']);
    }

    public function testSuppressingTwiceDoesNotDuplicate(): void
    {
        $list = $this->list();
        $junk = $this->entity('Read More');

        $list->suppress($junk);
        $list->suppress($junk);

        $this->assertSame(1, $list->count());
    }

    public function testFilterDropsOnlySuppressedCandidates(): void
    {
        $list  = $this->list();
        $junk  = $this->entity('Read More');
        $real  = $this->entity('Fatty liver disease', 'MedicalCondition');

        $list->suppress($junk);

        $candidates = [$junk->uid => 'junk', $real->uid => 'real'];

        $this->assertSame([$real->uid => 'real'], $list->filter($candidates));
    }

    public function testAnEmptyListLeavesCandidatesUntouched(): void
    {
        $candidates = ['a' => 1, 'b' => 2];

        $this->assertSame($candidates, $this->list()->filter($candidates));
    }

    public function testRestoreLetsAnEntityBeExtractedAgain(): void
    {
        $list = $this->list();
        $junk = $this->entity('Read More');

        $list->suppress($junk);
        $list->restore($junk->uid);

        $this->assertFalse($list->isSuppressed($junk->uid));
        $this->assertSame([$junk->uid => 'junk'], $list->filter([$junk->uid => 'junk']));
    }

    public function testRestoringAnUnknownUidIsHarmless(): void
    {
        $list = $this->list();
        $junk = $this->entity('Read More');

        $list->suppress($junk);
        $list->restore('not-a-real-uid');

        $this->assertSame(1, $list->count());
        $this->assertTrue($list->isSuppressed($junk->uid));
    }

    public function testClearEmptiesTheList(): void
    {
        $list = $this->list();

        $list->suppress($this->entity('One'));
        $list->suppress($this->entity('Two'));
        $list->clear();

        $this->assertSame(0, $list->count());
    }

    public function testTheListIsCapped(): void
    {
        // The option is autoloaded on every request, so unbounded growth would
        // be a site-wide cost. Refusing is visible; growing quietly is not.
        $list = $this->list();

        for ($i = 0; $i < SuppressionList::LIMIT; $i++) {
            $list->suppress($this->entity('Entity ' . $i));
        }

        $this->assertSame(SuppressionList::LIMIT, $list->count());
        $this->assertFalse($list->suppress($this->entity('One too many')));
        $this->assertSame(SuppressionList::LIMIT, $list->count());
    }

    public function testMalformedStoredRowsAreSkipped(): void
    {
        $GLOBALS['medora_test_options'] = [
            Options::OPTION_KEY => [
                'suppressed_entities' => [
                    ['uid' => 'a', 'name' => 'A', 'type' => 'T', 'at' => ''],
                    'garbage',
                    ['no_uid' => 1],
                ],
            ],
        ];

        $list = new SuppressionList(new Options());

        $this->assertSame(1, $list->count());
        $this->assertTrue($list->isSuppressed('a'));
    }

    private function list(): SuppressionList
    {
        $list = new SuppressionList(new Options());

        // Options caches on first read; warming it pins this instance to the
        // configuration set above.
        $list->all();

        return $list;
    }

    private function entity(string $name, string $type = 'Thing'): Entity
    {
        return new Entity(
            name: $name,
            type: $type,
            uid: hash('sha256', $type . '|' . mb_strtolower($name)),
        );
    }
}
