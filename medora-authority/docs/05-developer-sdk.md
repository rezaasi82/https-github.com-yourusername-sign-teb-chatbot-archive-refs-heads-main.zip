# Developer SDK

## Getting started

Depend on `Sdk\Medora` and on the documented hooks. Repository classes are
internal and their signatures may change between minor versions; the facade is
the supported contract, and every method degrades gracefully when the module
behind it is disabled.

```php
use Medora\Authority\Sdk\Medora;

$post = get_post();

$score   = Medora::score( $post );          // ?array — null if scoring is off
$pack    = Medora::promptPack( $post );     // ?array — summary, answer, facts
$related = Medora::related( $post, 5 );     // list — empty if Vector is off
$hits    = Medora::search( 'liver biopsy recovery' );
$graph   = Medora::knowledgeGraph();        // JSON-LD @graph

if ( Medora::isModuleActive( 'entity' ) ) {
    foreach ( Medora::entitiesFor( $post ) as $row ) {
        printf( '%s (%.2f)', $row['entity']->name, $row['salience'] );
    }
}
```

## Recipe: teach Medora your vocabulary

The single highest-value integration. Anything you add here is recognised by
the extractor, scored for authority, published in schema and reconciled against
external knowledge bases.

```php
add_filter( 'medora_entity_dictionary', function ( array $terms ): array {
    $terms['Endoscopic sleeve gastroplasty'] = [
        'type'    => \Medora\Authority\Entity\EntityType::MEDICAL_PROCEDURE,
        'aliases' => [ 'ESG', 'اسلیو آندوسکوپیک', 'تكميم المعدة بالمنظار' ],
        'same_as' => [ 'https://www.wikidata.org/wiki/Q30589952' ],
    ];

    return $terms;
} );
```

Aliases matter more than the canonical name: they are how the same concept
written three ways in three languages resolves to one entity.

## Recipe: a custom extractor

```php
use Medora\Authority\Entity\{Candidate, Entity, EntityType, ExtractorInterface};

final class ProductSkuExtractor implements ExtractorInterface {
    public function id(): string { return 'sku'; }
    public function priority(): int { return 70; }

    public function extract( \WP_Post $post, string $plainText ): array {
        $sku = get_post_meta( $post->ID, '_sku', true );

        if ( ! is_string( $sku ) || $sku === '' ) {
            return [];
        }

        return [ new Candidate(
            entity: new Entity(
                name: get_the_title( $post ),
                type: EntityType::PRODUCT,
                objectType: 'post',
                objectId: $post->ID,
                permalink: (string) get_permalink( $post ),
                meta: [ 'sku' => $sku ],
            ),
            occurrences: 1,
            confidence: 0.95,
            source: $this->id(),
        ) ];
    }
}

add_action( 'medora_register_entity_extractors', function ( $extractor ): void {
    $extractor->addExtractor( new ProductSkuExtractor() );
} );
```

Extractors must be pure: no writes, no HTTP on the hot path. If you need a
remote NER service, queue a job and return what you can synchronously.

## Recipe: a custom scoring dimension

Scorers start at 100 and deduct. That inversion is what makes results
explainable — the deduction list *is* the derivation, so the UI never has to
reverse-engineer a number.

```php
use Medora\Authority\Score\{Deduction, ScoreComponent, ScorerInterface};

final class VideoScorer implements ScorerInterface {
    public function id(): string { return 'video'; }
    public function label(): string { return __( 'Video coverage', 'my-plugin' ); }
    public function weight(): float { return 0.08; }

    public function appliesTo( \WP_Post $post ): bool {
        return $post->post_type === 'post';
    }

    public function score( \WP_Post $post ): ScoreComponent {
        $hasVideo = has_block( 'core/video', $post ) || str_contains( $post->post_content, 'youtube.com' );

        return new ScoreComponent(
            $this->id(),
            $this->label(),
            $hasVideo ? 100.0 : 55.0,
            $this->weight(),
            $hasVideo ? [] : [ new Deduction(
                'no_video',
                __( 'No video', 'my-plugin' ),
                45,
                __( 'Add a short explainer video with a transcript.', 'my-plugin' ),
                Deduction::SEVERITY_MEDIUM
            ) ],
            [ 'has_video' => $hasVideo ]
        );
    }
}

add_action( 'medora_register_scorers', fn ( $calculator ) => $calculator->addScorer( new VideoScorer() ) );
```

Weights are re-normalised across whichever scorers apply, so adding one does not
silently cap every page below 100. A scorer that throws is logged via
`medora_scorer_failed` and skipped — one broken dimension never takes down the
analysis.

## Recipe: a custom schema node

```php
use Medora\Authority\Schema\{NodeInterface, SchemaContext};

final class CourseNode implements NodeInterface {
    public function id(): string { return 'course'; }

    public function appliesTo( SchemaContext $context ): bool {
        return $context->post?->post_type === 'course';
    }

    public function build( SchemaContext $context ): array {
        return [ [
            '@type'    => 'Course',
            '@id'      => $context->id( 'course' ),
            'name'     => get_the_title( $context->post ),
            'provider' => [ '@id' => $context->siteId( 'organization' ) ],
        ] ];
    }
}

add_action( 'medora_register_schema_nodes', fn ( $graph ) => $graph->addNode( new CourseNode() ) );
```

Nodes return *lists* because one concern often produces several nodes. Nodes
sharing an `@id` are merged automatically — duplicate identities are the most
common structured-data validation failure once several builders contribute to
one graph.

## Recipe: a different embedding provider

```php
use Medora\Authority\Vector\EmbeddingProviderInterface;

add_filter( 'medora_embedding_providers', function ( array $providers ): array {
    $providers['my-model'] = new MyProvider(); // implements EmbeddingProviderInterface
    return $providers;
} );
```

Return **L2-normalised** vectors of a fixed length; `Similarity::cosine()`
assumes it and reduces to a dot product. Switching provider truncates the index
automatically, because two embedding spaces are not comparable and keeping the
old vectors would silently return nonsense.

## Recipe: delegate search to a vector database

```php
add_filter( 'medora_vector_search', function ( $result, array $query, int $limit, array $args ) {
    // Return null to fall through to the built-in linear scan.
    return my_pinecone_query( $query, $limit );
}, 10, 4 );
```

## Recipe: a full module

```php
use Medora\Authority\Core\Container;
use Medora\Authority\Module\AbstractModule;

final class MyModule extends AbstractModule {
    public function id(): string { return 'my_module'; }
    public function title(): string { return 'My Module'; }
    public function dependencies(): array { return [ 'entity' ]; }

    public function register( Container $c ): void {
        $c->singleton( MyService::class, fn ( Container $c ) => new MyService( $c->get( \Medora\Authority\Entity\EntityRepository::class ) ) );
    }

    public function boot( Container $c ): void {
        add_action( 'medora_entities_indexed', fn ( $post ) => $c->get( MyService::class )->handle( $post ) );
    }
}

add_filter( 'medora_module_classes', function ( array $classes ): array {
    $classes[] = MyModule::class;
    return $classes;
} );
```

`register()` may only bind services — no queries, no hooks, no assuming another
module has run. All wiring belongs in `boot()`.

## Background work

```php
use Medora\Authority\Performance\{JobInterface, JobQueue};

final class MyJob implements JobInterface {
    public function handle( array $payload, Container $container ): void {
        // Must be idempotent — delivery is at-least-once.
    }
}

Medora::container()->get( JobQueue::class )->push( MyJob::class, [ 'id' => 42 ] );
```

Identical pending jobs are collapsed, so re-saving a post five times in a minute
produces one analysis. Failures retry three times with exponential back-off
(1, 4, 9 minutes) before being marked failed.

Drain the queue manually with `wp medora queue --max=500`.

## Testing your integration

The unit suite runs without a WordPress install — `tests/phpunit/bootstrap.php`
shims the handful of WordPress functions the pure-logic classes touch. Anything
needing real WordPress belongs in an integration suite.

```bash
composer test
composer analyse   # PHPStan
composer lint      # WPCS
npm run check      # tsc + eslint + jest
```
