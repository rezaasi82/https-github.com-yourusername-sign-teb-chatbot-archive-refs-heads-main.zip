<?php

declare(strict_types=1);

namespace Medora\Authority\Content;

use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Entity\EntityType;
use Medora\Authority\Linking\LinkSuggestionEngine;
use Medora\Authority\Prompt\PromptPackRepository;
use Medora\Authority\Semantic\SemanticAnalyzer;
use Medora\Authority\Semantic\TopicCoverage;
use Medora\Authority\Support\Text;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Produces a writing brief for a page.
 *
 * This is where the Content Optimizer stops at, deliberately. It tells a writer
 * exactly what to add — which sections, which questions, which entities, what
 * the opening needs to do — but it never edits the page. Auto-rewriting
 * published content is an editorial and, on a health site, a regulatory
 * problem: the byline stays accountable for words the author did not write.
 *
 * The brief is the useful half of that boundary. A recommendation list says
 * "expected sub-topics are missing"; a brief says "add an H2 called 'How is it
 * diagnosed?', cover FibroScan and liver biopsy, cite at least one guideline,
 * and open with a 40-word answer to the title question".
 */
final class ContentBrief
{
    /** Words per expected facet, used to size the target. */
    private const WORDS_PER_SECTION = 180;

    private const MIN_TARGET_WORDS = 600;

    public function __construct(
        private readonly SemanticAnalyzer $semantic,
        private readonly EntityRepository $entities,
        private readonly PromptPackRepository $packs,
        private readonly RecommendationEngine $recommendations,
    ) {
    }

    /**
     * @return array{
     *     post_id: int,
     *     title: string,
     *     url: string,
     *     score: float,
     *     potential_score: float,
     *     subject: array<string, mixed>|null,
     *     word_count: array{current: int, target: int},
     *     opening: array{needs_rewrite: bool, current: string, spec: string},
     *     sections: list<array{heading: string, why: string, cover: list<string>, status: string}>,
     *     questions: list<array{question: string, status: string}>,
     *     entities: array{covered: list<string>, add: list<array{name: string, type: string, why: string}>},
     *     evidence: array{required: bool, current: int, target: int, note: string},
     *     internal_links: list<array<string, mixed>>,
     *     checklist: list<array{done: bool, task: string}>
     * }
     */
    public function forPost(WP_Post $post, ?LinkSuggestionEngine $linking = null): array
    {
        $analysis    = $this->semantic->analyze($post);
        $indexed     = $this->entities->forObject('post', $post->ID, 25);
        $pack        = $this->packs->find('post', $post->ID);
        $suggestions = $this->recommendations->forPost($post);

        $subject  = $indexed[0]['entity'] ?? null;
        $sections = $this->sections($post, $analysis['missing_facets']);
        $target   = $this->targetWordCount($analysis['word_count'], count($analysis['missing_facets']));

        return [
            'post_id'         => $post->ID,
            'title'           => get_the_title($post),
            'url'             => (string) get_permalink($post),
            'score'           => $suggestions['score'],
            'potential_score' => $suggestions['potential_score'],
            'subject'         => $subject instanceof Entity ? $subject->toArray() : null,
            'word_count'      => ['current' => $analysis['word_count'], 'target' => $target],
            'opening'         => $this->opening($post, $analysis, $pack),
            'sections'        => $sections,
            'questions'       => $this->questions($post, $pack),
            'entities'        => $this->entityPlan($indexed, $subject),
            'evidence'        => $this->evidence($post),
            'internal_links'  => $linking instanceof LinkSuggestionEngine
                ? array_slice($linking->suggestFor($post, 5), 0, 5)
                : [],
            'checklist'       => $this->checklist($analysis, $pack, $sections),
        ];
    }

    /**
     * What the first paragraph has to accomplish.
     *
     * @param array<string, mixed>      $analysis
     * @param array<string, mixed>|null $pack
     * @return array{needs_rewrite: bool, current: string, spec: string}
     */
    private function opening(WP_Post $post, array $analysis, ?array $pack): array
    {
        $current = (string) ($pack['canonical_answer'] ?? '');
        $needs   = ((float) $analysis['answer_readiness']) < 60;

        $spec = $needs
            ? sprintf(
                /* translators: %s: page title. */
                __(
                    'Open with 30–70 words that answer "%s" outright. Name the subject in the first clause, give the answer in the second, and leave background for later. Do not begin with "In this article" or with a pronoun.',
                    'medora-authority'
                ),
                get_the_title($post)
            )
            : __('The opening already answers the question directly. Leave it alone.', 'medora-authority');

        return ['needs_rewrite' => $needs, 'current' => $current, 'spec' => $spec];
    }

    /**
     * Sections the page has, and sections it is missing.
     *
     * Existing headings are returned alongside the gaps so a writer sees the
     * whole outline rather than a detached list of complaints.
     *
     * @param list<string> $missing Facet slugs.
     * @return list<array{heading: string, why: string, cover: list<string>, status: string}>
     */
    private function sections(WP_Post $post, array $missing): array
    {
        $sections = [];

        foreach ($this->existingHeadings($post->post_content) as $heading) {
            $sections[] = [
                'heading' => $heading,
                'why'     => '',
                'cover'   => [],
                'status'  => 'present',
            ];
        }

        foreach ($missing as $facet) {
            $sections[] = [
                'heading' => $this->headingFor($facet, get_the_title($post)),
                'why'     => sprintf(
                    /* translators: %s: facet name. */
                    __('"%s" is a question readers and assistants ask about this subject that the page cannot currently answer.', 'medora-authority'),
                    TopicCoverage::label($facet)
                ),
                'cover'   => $this->coverageHints($facet),
                'status'  => 'missing',
            ];
        }

        return $sections;
    }

    /**
     * Turn a facet label into a heading a writer can paste in.
     *
     * Question-shaped, because that is the form assistants match against.
     */
    private function headingFor(string $facet, string $title): string
    {
        $map = [
            'definition'           => __('What is it?', 'medora-authority'),
            'symptoms'             => __('What are the symptoms?', 'medora-authority'),
            'causes'               => __('What causes it?', 'medora-authority'),
            'diagnosis'            => __('How is it diagnosed?', 'medora-authority'),
            'treatment'            => __('How is it treated?', 'medora-authority'),
            'prognosis'            => __('What is the outlook?', 'medora-authority'),
            'when_to_seek_care'    => __('When should you see a doctor?', 'medora-authority'),
            'what_it_is'           => __('What is it?', 'medora-authority'),
            'who_needs_it'         => __('Who is it for?', 'medora-authority'),
            'preparation'          => __('How do you prepare?', 'medora-authority'),
            'recovery'             => __('What does recovery involve?', 'medora-authority'),
            'risks'                => __('What are the risks?', 'medora-authority'),
            'cost'                 => __('What does it cost?', 'medora-authority'),
            'what_it_does'         => __('What does it do?', 'medora-authority'),
            'features'             => __('What does it include?', 'medora-authority'),
            'pricing'              => __('What does it cost?', 'medora-authority'),
            'comparison'           => __('How does it compare?', 'medora-authority'),
            'reviews'              => __('What do people say?', 'medora-authority'),
            'how_it_works'         => __('How does it work?', 'medora-authority'),
            'why_it_matters'       => __('Why does it matter?', 'medora-authority'),
            'examples'             => __('Examples', 'medora-authority'),
            'common_questions'     => __('Common questions', 'medora-authority'),
            'evidence_and_sources' => __('Evidence and sources', 'medora-authority'),
            'review_date'          => __('Medical review', 'medora-authority'),
        ];

        // Looked up on the slug. The previous version compared the facet against
        // `__($key, …)` with a variable key — which gettext cannot extract, so
        // the "translation" was always the English key and the lookup silently
        // missed every facet on a translated site, sending every heading to the
        // fallback below.
        return $map[$facet] ?? sprintf('%s — %s', TopicCoverage::label($facet), $title);
    }

    /**
     * Concrete things the section should mention.
     *
     * @return list<string>
     */
    private function coverageHints(string $facet): array
    {
        $hints = [
            'diagnosis' => [__('name the specific tests', 'medora-authority'), __('say what each rules in or out', 'medora-authority')],
            'treatment' => [__('first-line option first', 'medora-authority'), __('expected timeframe', 'medora-authority'), __('what happens if untreated', 'medora-authority')],
            'symptoms'  => [__('most common first', 'medora-authority'), __('which are red flags', 'medora-authority')],
            'causes'    => [__('separate causes from risk factors', 'medora-authority')],
            'prognosis' => [__('give a figure with a source', 'medora-authority')],
            'cost'      => [__('a range, with what changes it', 'medora-authority')],
            'pricing'   => [__('a range, with what changes it', 'medora-authority')],
            'risks'     => [__('rate the common ones', 'medora-authority'), __('name the rare serious ones', 'medora-authority')],
        ];

        return $hints[$facet] ?? [];
    }

    /**
     * @return list<string>
     */
    private function existingHeadings(string $content): array
    {
        preg_match_all('#<h([2-3])[^>]*>(.*?)</h\1>#is', $content, $matches);

        $headings = [];

        foreach ($matches[2] ?? [] as $heading) {
            $text = Text::plain($heading);

            if ($text !== '') {
                $headings[] = $text;
            }
        }

        return $headings;
    }

    /**
     * Questions the page answers, and the obvious ones it does not.
     *
     * @param array<string, mixed>|null $pack
     * @return list<array{question: string, status: string}>
     */
    private function questions(WP_Post $post, ?array $pack): array
    {
        $questions = [];
        $answered  = [];

        foreach ((array) ($pack['questions'] ?? []) as $pair) {
            $question = (string) ($pair['question'] ?? '');

            if ($question === '') {
                continue;
            }

            $answered[]  = Text::normalize($question);
            $questions[] = ['question' => $question, 'status' => 'answered'];
        }

        $title = get_the_title($post);

        // The comparison and cost questions are asked about almost every
        // subject and are almost always missing.
        $universal = [
            sprintf(
                /* translators: %s: page title. */
                __('How long does %s take?', 'medora-authority'),
                $title
            ),
            sprintf(
                /* translators: %s: page title. */
                __('Is %s safe?', 'medora-authority'),
                $title
            ),
            sprintf(
                /* translators: %s: page title. */
                __('What are the alternatives to %s?', 'medora-authority'),
                $title
            ),
        ];

        foreach ($universal as $candidate) {
            $tokens = Text::tokens($candidate);
            $seen   = false;

            foreach ($answered as $existing) {
                if (count(array_intersect($tokens, Text::tokens($existing))) >= max(2, (int) (count($tokens) * 0.6))) {
                    $seen = true;
                    break;
                }
            }

            if (! $seen) {
                $questions[] = ['question' => $candidate, 'status' => 'unanswered'];
            }
        }

        return $questions;
    }

    /**
     * Which entities the page covers and which it should introduce.
     *
     * Suggested additions come from the site's own graph — entities that
     * co-occur with this page's subject elsewhere on the site but are absent
     * here — so the brief never invents a topic the site knows nothing about.
     *
     * @param list<array{entity: Entity, salience: float, occurrences: int}> $indexed
     * @return array{covered: list<string>, add: list<array{name: string, type: string, why: string}>}
     */
    private function entityPlan(array $indexed, ?Entity $subject): array
    {
        $covered   = [];
        $coveredIds = [];

        foreach ($indexed as $row) {
            $covered[]                   = $row['entity']->name;
            $coveredIds[$row['entity']->id] = true;
        }

        $add = [];

        if ($subject instanceof Entity) {
            foreach ($this->neighboursOf($subject) as $neighbour) {
                if (isset($coveredIds[$neighbour['entity']->id])) {
                    continue;
                }

                $add[] = [
                    'name' => $neighbour['entity']->name,
                    'type' => EntityType::label($neighbour['entity']->type),
                    'why'  => $neighbour['why'],
                ];

                if (count($add) >= 8) {
                    break;
                }
            }
        }

        return ['covered' => $covered, 'add' => $add];
    }

    /**
     * Entities related to the subject in the graph, ordered by edge strength.
     *
     * @return list<array{entity: Entity, why: string}>
     */
    private function neighboursOf(Entity $subject): array
    {
        /**
         * Supply related entities for a content brief.
         *
         * The Graph module answers this; when it is disabled the brief simply
         * omits the "entities to add" section rather than failing.
         *
         * @param list<array{entity: Entity, why: string}> $neighbours
         * @param Entity                                   $subject
         */
        return (array) apply_filters('medora_brief_related_entities', [], $subject);
    }

    /**
     * @return array{required: bool, current: int, target: int, note: string}
     */
    private function evidence(WP_Post $post): array
    {
        /**
         * Report how many citations a post carries.
         *
         * Owned by the Citation module; absent, the brief reports zero and
         * still says what good looks like.
         *
         * @param int     $count
         * @param WP_Post $post
         */
        $current  = (int) apply_filters('medora_brief_citation_count', 0, $post);
        $required = (bool) apply_filters('medora_brief_citations_required', false, $post);
        $target   = $required ? 3 : 1;

        return [
            'required' => $required,
            'current'  => $current,
            'target'   => $target,
            'note'     => $required
                ? __('Health claims need primary literature — a DOI or PubMed identifier, not a link to another blog. Attach at least one source per substantive claim.', 'medora-authority')
                : __('Cite at least one outbound source. A page that asserts without sourcing is a weak candidate for citation itself.', 'medora-authority'),
        ];
    }

    private function targetWordCount(int $current, int $missingFacets): int
    {
        $target = max(self::MIN_TARGET_WORDS, $current + ($missingFacets * self::WORDS_PER_SECTION));

        // Round to the nearest 50 so the number reads as guidance, not a quota.
        return (int) (round($target / 50) * 50);
    }

    /**
     * The brief as a tickable list, in the order it should be worked.
     *
     * @param array<string, mixed>      $analysis
     * @param array<string, mixed>|null $pack
     * @param list<array<string, mixed>> $sections
     * @return list<array{done: bool, task: string}>
     */
    private function checklist(array $analysis, ?array $pack, array $sections): array
    {
        $missing = array_filter($sections, static fn (array $s): bool => $s['status'] === 'missing');

        $checklist = [
            [
                'done' => ((float) $analysis['answer_readiness']) >= 60,
                'task' => __('Opening answers the title question in 30–70 words', 'medora-authority'),
            ],
            [
                'done' => $missing === [],
                'task' => sprintf(
                    /* translators: %d: number of sections. */
                    _n(
                        'Add %d missing section',
                        'Add %d missing sections',
                        count($missing),
                        'medora-authority'
                    ),
                    count($missing)
                ),
            ],
            [
                'done' => ((float) $analysis['chunk_integrity']) >= 60,
                'task' => __('Every section stands alone when read without the rest of the page', 'medora-authority'),
            ],
            [
                'done' => count((array) ($pack['facts'] ?? [])) >= 3,
                'task' => __('At least three sentences carry a checkable figure', 'medora-authority'),
            ],
            [
                'done' => (string) ($pack['canonical_answer'] ?? '') !== '',
                'task' => __('A passage is marked with the "medora-answer" class', 'medora-authority'),
            ],
            [
                'done' => (int) $analysis['word_count'] >= self::MIN_TARGET_WORDS,
                'task' => __('Long enough to establish authority', 'medora-authority'),
            ],
        ];

        // Unfinished work first — a checklist that opens with ticks buries the
        // thing the writer actually has to do.
        usort($checklist, static fn (array $a, array $b): int => ($a['done'] ? 1 : 0) <=> ($b['done'] ? 1 : 0));

        return $checklist;
    }

    /**
     * The brief as Markdown, for pasting into a brief document or a ticket.
     *
     * @param array<string, mixed> $brief
     */
    public function toMarkdown(array $brief): string
    {
        $lines = [
            sprintf('# Content brief: %s', $brief['title']),
            '',
            sprintf('%s · Score %s/100 → potential %s', $brief['url'], $brief['score'], $brief['potential_score']),
            sprintf('Word count: %d now, ~%d target', $brief['word_count']['current'], $brief['word_count']['target']),
            '',
            '## Opening',
            '',
            $brief['opening']['spec'],
            '',
            '## Outline',
            '',
        ];

        foreach ($brief['sections'] as $section) {
            $mark = $section['status'] === 'present' ? 'x' : ' ';

            $lines[] = sprintf('- [%s] %s', $mark, $section['heading']);

            if ($section['why'] !== '') {
                $lines[] = sprintf('      %s', $section['why']);
            }

            foreach ($section['cover'] as $hint) {
                $lines[] = sprintf('      - %s', $hint);
            }
        }

        if ($brief['entities']['add'] !== []) {
            $lines[] = '';
            $lines[] = '## Entities to introduce';
            $lines[] = '';

            foreach ($brief['entities']['add'] as $entity) {
                $lines[] = sprintf('- **%s** (%s) — %s', $entity['name'], $entity['type'], $entity['why']);
            }
        }

        $unanswered = array_filter(
            $brief['questions'],
            static fn (array $q): bool => $q['status'] === 'unanswered'
        );

        if ($unanswered !== []) {
            $lines[] = '';
            $lines[] = '## Questions not yet answered';
            $lines[] = '';

            foreach ($unanswered as $question) {
                $lines[] = sprintf('- %s', $question['question']);
            }
        }

        $lines[] = '';
        $lines[] = '## Evidence';
        $lines[] = '';
        $lines[] = sprintf('%d of %d sources attached. %s', $brief['evidence']['current'], $brief['evidence']['target'], $brief['evidence']['note']);

        $lines[] = '';
        $lines[] = '## Checklist';
        $lines[] = '';

        foreach ($brief['checklist'] as $item) {
            $lines[] = sprintf('- [%s] %s', $item['done'] ? 'x' : ' ', $item['task']);
        }

        return implode("\n", $lines);
    }
}
