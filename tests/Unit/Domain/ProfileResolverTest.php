<?php
/**
 * Tests for ProfileResolver alias-domain resolution.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Tests\Unit\Domain
 */

declare(strict_types=1);

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../../../' );
	}

	if ( ! class_exists( 'WP_Post' ) ) {
		class WP_Post {
			public function __construct(
				public int $ID,
				public string $post_type = 'spx_policy_profile',
				public string $post_status = 'publish'
			) {}
		}
	}

	$GLOBALS['spx_profile_resolver_test_state'] = [
		'posts'          => [],
		'post_meta'      => [],
		'posts_by_id'    => [],
		'options'        => [],
		'get_posts_args' => null,
	];

	if ( ! function_exists( 'get_posts' ) ) {
		function get_posts( array $args ): array {
			$GLOBALS['spx_profile_resolver_test_state']['get_posts_args'] = $args;
			return $GLOBALS['spx_profile_resolver_test_state']['posts'];
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
			$value = $GLOBALS['spx_profile_resolver_test_state']['post_meta'][ $post_id ][ $key ] ?? '';

			if ( $single ) {
				return $value;
			}

			return [ $value ];
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( int $post_id ): ?WP_Post {
			return $GLOBALS['spx_profile_resolver_test_state']['posts_by_id'][ $post_id ] ?? null;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( string $option, mixed $default = false ): mixed {
			return $GLOBALS['spx_profile_resolver_test_state']['options'][ $option ] ?? $default;
		}
	}
}

namespace Starisian\Sparxstar\PolicyRenderer\Tests\Unit\Domain {

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\PolicyRenderer\Domain\HostNormalizer;
use Starisian\Sparxstar\PolicyRenderer\Domain\ProfileResolver;
use WP_Post;

final class ProfileResolverTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['spx_profile_resolver_test_state'] = [
			'posts'          => [],
			'post_meta'      => [],
			'posts_by_id'    => [],
			'options'        => [],
			'get_posts_args' => null,
		];

		$this->new_resolver()->flush_cache();
	}

	/**
	 * @test
	 */
	public function it_resolves_alias_domains(): void {
		$profile = new WP_Post( 123 );

		$GLOBALS['spx_profile_resolver_test_state']['posts'] = [ $profile ];
		$GLOBALS['spx_profile_resolver_test_state']['post_meta'][123] = [
			'active'         => '1',
			'primary_domain' => 'primary.example.com',
			'alias_domains'  => [ 'alias.example.com' ],
			'allowed_hosts'  => [],
		];

		$resolver = $this->new_resolver();
		$resolved = $resolver->resolve( $this->new_normalizer()->normalize( 'Alias.Example.com' ) );

		self::assertSame( $profile, $resolved );
		self::assertSame(
			'alias_domains',
			$GLOBALS['spx_profile_resolver_test_state']['get_posts_args']['meta_query'][1]['key'] ?? null
		);
		self::assertSame(
			'"alias.example.com"',
			$GLOBALS['spx_profile_resolver_test_state']['get_posts_args']['meta_query'][1]['value'] ?? null
		);
	}

	private function new_resolver(): ProfileResolver {
		return new ProfileResolver( $this->new_normalizer() );
	}

	private function new_normalizer(): HostNormalizer {
		return new HostNormalizer();
	}
}
}
