<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Vector\Providers\HashingEmbeddingProvider;
use Medora\Authority\Vector\Similarity;
use PHPUnit\Framework\TestCase;

/**
 * Vector maths and the binary storage encoding.
 *
 * A silent bug here would not throw — it would just return subtly wrong
 * neighbours, which is exactly the kind of defect that survives to production.
 */
final class SimilarityTest extends TestCase
{
    public function testPackAndUnpackRoundTripFloat32(): void
    {
        $vector = [0.1, -0.5, 0.25, 0.9];
        $result = Similarity::unpack(Similarity::pack($vector));

        $this->assertCount(4, $result);

        foreach ($vector as $index => $expected) {
            // float32 has ~7 significant digits, so an exact comparison would
            // be wrong to assert.
            $this->assertEqualsWithDelta($expected, $result[$index], 1e-6);
        }
    }

    public function testUnpackOfEmptyStringIsEmpty(): void
    {
        $this->assertSame([], Similarity::unpack(''));
    }

    public function testCosineOfIdenticalVectorsIsOne(): void
    {
        $vector = [0.3, 0.4, 0.5];

        $this->assertEqualsWithDelta(1.0, Similarity::cosine($vector, $vector), 1e-9);
    }

    public function testCosineOfOrthogonalVectorsIsZero(): void
    {
        $this->assertEqualsWithDelta(0.0, Similarity::cosine([1.0, 0.0], [0.0, 1.0]), 1e-9);
    }

    public function testCosineOfOppositeVectorsIsMinusOne(): void
    {
        $this->assertEqualsWithDelta(-1.0, Similarity::cosine([1.0, 0.0], [-1.0, 0.0]), 1e-9);
    }

    public function testCosineWithAZeroVectorIsZeroRatherThanNan(): void
    {
        $this->assertSame(0.0, Similarity::cosine([0.0, 0.0], [1.0, 1.0]));
        $this->assertSame(0.0, Similarity::cosine([], [1.0]));
    }

    public function testCentroidIsRenormalised(): void
    {
        $centroid = Similarity::centroid([[1.0, 0.0], [0.0, 1.0]]);

        $this->assertEqualsWithDelta(1.0, Similarity::magnitude($centroid), 1e-6);
    }

    public function testCentroidOfNothingIsEmpty(): void
    {
        $this->assertSame([], Similarity::centroid([]));
    }

    public function testHashingProviderIsDeterministic(): void
    {
        $provider = new HashingEmbeddingProvider(256);

        $this->assertSame(
            $provider->embed('fatty liver disease'),
            $provider->embed('fatty liver disease')
        );
    }

    public function testHashingProviderReturnsUnitVectors(): void
    {
        $provider = new HashingEmbeddingProvider(256);

        $this->assertEqualsWithDelta(
            1.0,
            Similarity::magnitude($provider->embed('liver biopsy recovery')),
            1e-6
        );
    }

    public function testHashingProviderHonoursDimensions(): void
    {
        $this->assertCount(128, ( new HashingEmbeddingProvider( 128 ) )->embed('anything'));
        // Below the floor, the provider clamps rather than producing a
        // degenerate space.
        $this->assertCount(64, ( new HashingEmbeddingProvider( 8 ) )->embed('anything'));
    }

    public function testRelatedTextScoresHigherThanUnrelatedText(): void
    {
        $provider = new HashingEmbeddingProvider(512);

        $subject = $provider->embed('fatty liver disease treatment options');
        $related = $provider->embed('treatment options for fatty liver disease');
        $other   = $provider->embed('knee replacement surgery recovery timeline');

        $this->assertGreaterThan(
            Similarity::cosine($subject, $other),
            Similarity::cosine($subject, $related)
        );
    }

    public function testEmptyTextProducesAZeroVector(): void
    {
        $vector = ( new HashingEmbeddingProvider( 64 ) )->embed('');

        $this->assertSame(0.0, array_sum(array_map('abs', $vector)));
    }
}
