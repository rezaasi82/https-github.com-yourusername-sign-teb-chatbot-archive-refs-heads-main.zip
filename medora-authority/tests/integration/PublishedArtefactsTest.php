<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Integration;

use Medora\Authority\Core\Options;
use Medora\Authority\Crawler\CrawlerPolicy;
use Medora\Authority\Crawler\RobotsManager;
use Medora\Authority\Entity\EntityExtractor;
use Medora\Authority\Llms\LlmsTxtGenerator;
use Medora\Authority\Schema\SchemaGraph;
use Medora\Authority\Schema\SchemaModule;
use Medora\Authority\Schema\SchemaValidator;
use Medora\Authority\Sitemap\SitemapBuilder;
use Medora\Authority\Sitemap\SitemapRenderer;

/**
 * The artefacts an AI system actually consumes: JSON-LD, llms.txt, sitemaps and
 * robots.txt. These are the plugin's public output, so they are asserted
 * against real posts rather than fixtures.
 */
final class PublishedArtefactsTest extends MedoraTestCase
{
    // --- Schema -------------------------------------------------------------

    public function test_schema_graph_is_connected_and_valid(): void
    {
        $post = $this->makePost();
        wp_set_object_terms($post->ID, ['Hepatology'], 'category');

        $this->container->get(EntityExtractor::class)->indexPost($post);

        $this->go_to(get_permalink($post));

        $context  = $this->container->get(SchemaModule::class)->contextFor($this->container, $post);
        $document = $this->container->get(SchemaGraph::class)->build($context);

        $this->assertSame('https://schema.org', $document['@context']);
        $this->assertNotEmpty($document['@graph']);

        $validation = $this->container->get(SchemaValidator::class)->validate($document);

        $errors = array_filter(
            $validation['errors'],
            static fn (array $issue): bool => $issue['severity'] === SchemaValidator::SEVERITY_ERROR
        );

        $this->assertSame([], array_values($errors), 'Generated schema must validate cleanly.');
    }

    public function test_schema_nodes_reference_the_publisher(): void
    {
        $this->container->get(Options::class)->set('organization_name', 'Acme Clinic');

        $post = $this->makePost();
        $this->go_to(get_permalink($post));

        $context = $this->container->get(SchemaModule::class)->contextFor($this->container, $post);
        $graph   = $this->container->get(SchemaGraph::class)->build($context)['@graph'];

        $types = array_column($graph, '@type');

        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('BreadcrumbList', $types);

        // The WebSite node must point at the Organization node by @id, which is
        // what ties scattered pages back to one accountable publisher.
        $website = current(array_filter($graph, static fn (array $n): bool => ($n['@type'] ?? '') === 'WebSite'));
        $this->assertSame(home_url('/#organization'), $website['publisher']['@id']);
    }

    public function test_medical_mode_upgrades_the_page_and_publisher_types(): void
    {
        $this->container->get(Options::class)->merge([
            'site_mode'         => 'medical',
            'organization_name' => 'Acme Clinic',
        ]);

        $post = $this->makePost();
        $this->go_to(get_permalink($post));

        $context = $this->container->get(SchemaModule::class)->contextFor($this->container, $post);
        $types   = array_column($this->container->get(SchemaGraph::class)->build($context)['@graph'], '@type');

        $this->assertContains('MedicalOrganization', $types);
        $this->assertContains('MedicalWebPage', $types);
    }

    public function test_faq_is_extracted_from_question_headings(): void
    {
        $post = $this->makePost(); // Has three question-shaped H2s.
        $this->go_to(get_permalink($post));

        $context = $this->container->get(SchemaModule::class)->contextFor($this->container, $post);
        $graph   = $this->container->get(SchemaGraph::class)->build($context)['@graph'];

        $faq = array_values(array_filter($graph, static fn (array $n): bool => ($n['@type'] ?? '') === 'FAQPage'));

        $this->assertCount(1, $faq);
        $this->assertGreaterThanOrEqual(2, count($faq[0]['mainEntity']));

        foreach ($faq[0]['mainEntity'] as $question) {
            $this->assertSame('Question', $question['@type']);
            $this->assertNotSame('', $question['acceptedAnswer']['text']);
        }
    }

    public function test_schema_prints_into_wp_head(): void
    {
        $post = $this->makePost();
        $this->go_to(get_permalink($post));

        ob_start();
        do_action('wp_head');
        $head = (string) ob_get_clean();

        $this->assertStringContainsString('application/ld+json', $head);
        $this->assertStringContainsString('data-medora="schema"', $head);
    }

    // --- llms.txt -----------------------------------------------------------

    public function test_llms_txt_lists_published_content(): void
    {
        $this->makePost(['post_title' => 'Liver biopsy recovery']);
        $this->makePost(['post_title' => 'Endoscopy preparation']);

        $document = $this->container->get(LlmsTxtGenerator::class)->index();

        $this->assertStringContainsString('# ', $document);
        $this->assertStringContainsString('Liver biopsy recovery', $document);
        $this->assertStringContainsString('Endoscopy preparation', $document);
        $this->assertStringContainsString('/llms-full.txt', $document);
    }

    public function test_llms_txt_honours_the_per_post_exclusion(): void
    {
        $visible  = $this->makePost(['post_title' => 'Visible page']);
        $excluded = $this->makePost(['post_title' => 'Excluded page']);

        update_post_meta($excluded->ID, '_medora_exclude_llms', '1');

        $this->container->get(LlmsTxtGenerator::class)->flush();
        $document = $this->container->get(LlmsTxtGenerator::class)->index();

        $this->assertStringContainsString('Visible page', $document);
        $this->assertStringNotContainsString('Excluded page', $document);
        unset($visible);
    }

    public function test_llms_txt_omits_drafts(): void
    {
        $this->makePost(['post_title' => 'Draft page', 'post_status' => 'draft']);

        $this->container->get(LlmsTxtGenerator::class)->flush();

        $this->assertStringNotContainsString(
            'Draft page',
            $this->container->get(LlmsTxtGenerator::class)->index()
        );
    }

    public function test_medical_mode_declares_editorial_standards(): void
    {
        $this->container->get(Options::class)->set('site_mode', 'medical');
        $this->makePost();

        $this->container->get(LlmsTxtGenerator::class)->flush();
        $document = $this->container->get(LlmsTxtGenerator::class)->index();

        $this->assertStringContainsString('Editorial standards', $document);
    }

    // --- Sitemaps -----------------------------------------------------------

    public function test_content_sitemap_renders_in_every_format(): void
    {
        $this->makePost(['post_title' => 'Liver biopsy recovery']);

        $builder  = $this->container->get(SitemapBuilder::class);
        $renderer = $this->container->get(SitemapRenderer::class);
        $entries  = $builder->entries(SitemapBuilder::TYPE_CONTENT);

        $this->assertNotEmpty($entries);

        $xml = $renderer->render($entries, SitemapRenderer::FORMAT_XML, 'content');
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('<loc>', $xml);
        // The XML must still parse as valid XML with the Medora extensions.
        $this->assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($xml));

        $json = json_decode($renderer->render($entries, SitemapRenderer::FORMAT_JSON, 'content'), true);
        $this->assertIsArray($json);
        $this->assertSame('content', $json['type']);
        $this->assertSame(count($entries), $json['entry_count']);

        $markdown = $renderer->render($entries, SitemapRenderer::FORMAT_MARKDOWN, 'content');
        $this->assertStringContainsString('Liver biopsy recovery', $markdown);
    }

    public function test_sitemap_index_lists_every_variant(): void
    {
        $builder = $this->container->get(SitemapBuilder::class);
        $index   = $this->container->get(SitemapRenderer::class)->index($builder->availableTypes());

        $this->assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($index));

        foreach ($builder->availableTypes() as $type) {
            $this->assertStringContainsString('medora-sitemap-' . $type . '.xml', $index);
        }
    }

    public function test_knowledge_sitemap_advertises_the_machine_endpoints(): void
    {
        $entries = $this->container->get(SitemapBuilder::class)->entries(SitemapBuilder::TYPE_KNOWLEDGE);
        $urls    = array_map(static fn ($entry): string => $entry->url, $entries);

        $this->assertContains(home_url('/llms.txt'), $urls);
        $this->assertContains(home_url('/llms-full.txt'), $urls);
    }

    public function test_noindexed_posts_are_excluded_from_the_sitemap(): void
    {
        $post = $this->makePost(['post_title' => 'Hidden page']);
        update_post_meta($post->ID, '_medora_noindex', '1');

        $builder = $this->container->get(SitemapBuilder::class);
        $builder->flush();

        $titles = array_map(
            static fn ($entry): string => $entry->title,
            $builder->entries(SitemapBuilder::TYPE_CONTENT)
        );

        $this->assertNotContains('Hidden page', $titles);
    }

    // --- robots.txt ---------------------------------------------------------

    public function test_robots_directives_reflect_the_policy(): void
    {
        $policy = $this->container->get(CrawlerPolicy::class);
        $policy->setPreset('selective');

        $directives = $this->container->get(RobotsManager::class)->directives();

        $this->assertStringContainsString('User-agent: GPTBot', $directives);
        $this->assertStringContainsString('User-agent: Google-Extended', $directives);
        $this->assertStringContainsString('Sitemap: ', $directives);
        $this->assertStringContainsString('/llms.txt', $directives);

        // Selective must block training crawlers.
        $this->assertMatchesRegularExpression(
            '/User-agent: GPTBot\s+Disallow: \//',
            $directives
        );
    }

    public function test_robots_filter_appends_to_the_virtual_file(): void
    {
        $output = apply_filters('robots_txt', "User-agent: *\nDisallow:\n", true);

        $this->assertStringContainsString('Medora Authority', $output);
    }

    public function test_robots_directives_are_not_added_to_a_private_site(): void
    {
        $output = apply_filters('robots_txt', "User-agent: *\nDisallow: /\n", false);

        $this->assertStringNotContainsString('Medora Authority', $output);
    }
}
