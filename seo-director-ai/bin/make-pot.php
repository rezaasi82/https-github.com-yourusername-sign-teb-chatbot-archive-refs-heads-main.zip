<?php
/**
 * Minimal gettext scanner: extracts translatable strings from the plugin's
 * PHP source and writes a .pot template. Covers the single-argument WP
 * translation functions used in this codebase. Not a replacement for wp-cli
 * i18n, but dependency-free and good enough to seed languages/.
 */

$root = getenv( 'SDA_DIR' ) ?: getcwd();
$out  = $root . '/languages/seo-director-ai.pot';

$functions = [ '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', 'esc_html_x', '_x' ];
$domain    = 'seo-director-ai';

/** @var array<string, string[]> $entries msgid => ["file:line", ...] */
$entries = [];

$dir = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS )
);

foreach ( $dir as $file ) {
	if ( 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	$code   = file_get_contents( $file->getPathname() );
	$tokens = token_get_all( $code );
	$rel    = ltrim( str_replace( $root, '', $file->getPathname() ), '/' );

	for ( $i = 0, $n = count( $tokens ); $i < $n; $i++ ) {
		$tok = $tokens[ $i ];
		if ( ! is_array( $tok ) || T_STRING !== $tok[0] || ! in_array( $tok[1], $functions, true ) ) {
			continue;
		}

		// Expect: FUNC ( 'string'
		$j = $i + 1;
		while ( $j < $n && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}
		if ( $j >= $n || '(' !== $tokens[ $j ] ) {
			continue;
		}
		$k = $j + 1;
		while ( $k < $n && is_array( $tokens[ $k ] ) && T_WHITESPACE === $tokens[ $k ][0] ) {
			$k++;
		}
		if ( $k >= $n || ! is_array( $tokens[ $k ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $k ][0] ) {
			continue;
		}

		$raw   = $tokens[ $k ][1];
		$msgid = stripcslashes( substr( $raw, 1, -1 ) );
		if ( '' === $msgid ) {
			continue;
		}

		$entries[ $msgid ][] = $rel . ':' . $tok[2];
	}
}

ksort( $entries );

$header  = "# Copyright (C) SEO Director\n";
$header .= "# This file is distributed under the GPL-2.0-or-later license.\n";
$header .= "msgid \"\"\nmsgstr \"\"\n";
$header .= "\"Project-Id-Version: SEO Director AI\\n\"\n";
$header .= "\"Report-Msgid-Bugs-To: https://seodirector.app\\n\"\n";
$header .= "\"MIME-Version: 1.0\\n\"\n";
$header .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$header .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$header .= "\"Language: \\n\"\n";
$header .= "\"X-Domain: {$domain}\\n\"\n\n";

$body = '';
foreach ( $entries as $msgid => $refs ) {
	foreach ( array_unique( $refs ) as $ref ) {
		$body .= "#: {$ref}\n";
	}
	$escaped = addcslashes( $msgid, "\"\\" );
	$body   .= "msgid \"{$escaped}\"\nmsgstr \"\"\n\n";
}

if ( ! is_dir( dirname( $out ) ) ) {
	mkdir( dirname( $out ), 0755, true );
}

file_put_contents( $out, $header . $body );

printf( "Wrote %d unique strings to %s\n", count( $entries ), $out );
