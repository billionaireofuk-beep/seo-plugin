<?php
/**
 * AI Content Optimizer Feature
 *
 * @package Invenzia_SEO_Matrix
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Invenzia_AI_Content_Optimizer {

	/**
	 * Feature version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'invenzia-seo/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the feature.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Enqueue assets for Gutenberg editor.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		// Only load for supported post types
		$supported_types = apply_filters( 'invenzia_ai_optimizer_post_types', array( 'post', 'page' ) );
		if ( ! in_array( $post->post_type, $supported_types, true ) ) {
			return;
		}

		$asset_file = plugin_dir_path( __FILE__ ) . '../../assets/js/ai-content-optimizer.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = include $asset_file;
		} else {
			$asset = array(
				'dependencies' => array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-editor', 'wp-data', 'wp-i18n' ),
				'version'      => self::VERSION,
			);
		}

		// Enqueue JS
		wp_enqueue_script(
			'invenzia-ai-content-optimizer',
			plugin_dir_url( __FILE__ ) . '../../assets/js/ai-content-optimizer.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Localize script
		wp_localize_script(
			'invenzia-ai-content-optimizer',
			'invenziaAIOptimizer',
			array(
				'apiUrl'    => rest_url( $this->namespace . '/analyze' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'postId'    => $post->ID,
				'apiKey'    => $this->get_api_key(),
				'hasApiKey' => ! empty( $this->get_api_key() ),
			)
		);

		// Enqueue CSS
		wp_enqueue_style(
			'invenzia-ai-content-optimizer',
			plugin_dir_url( __FILE__ ) . '../../assets/css/ai-content-optimizer.css',
			array(),
			self::VERSION
		);
	}

	/**
	 * Get API key (placeholder for future AI integration).
	 *
	 * @return string
	 */
	private function get_api_key() {
		return get_option( 'invenzia_ai_api_key', '' );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/analyze',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_analysis' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'content' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'title'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'keyword' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check user permission for API access.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handle content analysis request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_analysis( $request ) {
		$content = $request->get_param( 'content' );
		$title   = $request->get_param( 'title' );
		$keyword = $request->get_param( 'keyword' );

		// Analyze content
		$analysis = $this->analyze_content( $content, $title, $keyword );

		return rest_ensure_response( $analysis );
	}

	/**
	 * Analyze content and return optimization data.
	 *
	 * @param string $content  Content to analyze.
	 * @param string $title    Post title.
	 * @param string $keyword  Target keyword.
	 * @return array
	 */
	private function analyze_content( $content, $title, $keyword = '' ) {
		// Strip HTML tags
		$plain_text = wp_strip_all_tags( $content );
		$plain_text = preg_replace( '/\s+/', ' ', $plain_text );
		$plain_text = trim( $plain_text );

		$word_count      = str_word_count( $plain_text );
		$sentence_count = preg_match_all( '/[.!?]+/', $plain_text, $matches ) ? count( $matches[0] ) : 1;
		$paragraph_count = substr_count( $content, '</p>' ) + substr_count( $content, '</h1>' ) + substr_count( $content, '</h2>' ) + substr_count( $content, '</h3>' );

		// Extract headings
		$headings = $this->extract_headings( $content );

		// Keyword analysis
		$keyword_analysis = $this->analyze_keyword( $plain_text, $keyword );

		// Readability analysis
		$readability = $this->analyze_readability( $plain_text, $word_count, $sentence_count );

		// Calculate overall SEO score
		$seo_score = $this->calculate_seo_score( $keyword_analysis, $readability, $headings, $word_count );

		// Generate AI suggestions
		$suggestions = $this->generate_suggestions( $title, $plain_text, $keyword, $headings );

		// Determine glow color
		$glow_color = $this->get_glow_color( $seo_score );

		return array(
			'success'    => true,
			'seo_score'  => $seo_score,
			'glow_color' => $glow_color,
			'keyword'    => $keyword_analysis,
			'readability' => $readability,
			'headings'   => $headings,
			'word_count' => $word_count,
			'suggestions' => $suggestions,
			'timestamp'  => current_time( 'mysql' ),
		);
	}

	/**
	 * Extract headings from content.
	 *
	 * @param string $content HTML content.
	 * @return array
	 */
	private function extract_headings( $content ) {
		$headings = array(
			'h1' => array(),
			'h2' => array(),
			'h3' => array(),
			'h4' => array(),
			'h5' => array(),
			'h6' => array(),
		);

		foreach ( array_keys( $headings ) as $tag ) {
			preg_match_all( '/<' . $tag . '([^>]*)>(.*?)<\/' . $tag . '>/is', $content, $matches );
			if ( ! empty( $matches[2] ) ) {
				$headings[ $tag ] = array_map( 'wp_strip_all_tags', $matches[2] );
			}
		}

		return $headings;
	}

	/**
	 * Analyze keyword usage.
	 *
	 * @param string $content Content text.
	 * @param string $keyword Target keyword.
	 * @return array
	 */
	private function analyze_keyword( $content, $keyword ) {
		if ( empty( $keyword ) ) {
			return array(
				'has_keyword' => false,
				'density'     => 0,
				'count'       => 0,
				'in_title'    => false,
				'in_first_para' => false,
				'lsi_keywords' => array(),
			);
		}

		$keyword_lower  = strtolower( $keyword );
		$content_lower  = strtolower( $content );
		$word_count     = str_word_count( $content );
		$keyword_count  = substr_count( $content_lower, $keyword_lower );
		$density        = $word_count > 0 ? round( ( $keyword_count / $word_count ) * 100, 2 ) : 0;

		$first_para = substr( $content, 0, strpos( $content, '</p>' ) ?: 200 );

		return array(
			'has_keyword'    => true,
			'keyword'        => $keyword,
			'density'        => $density,
			'count'          => $keyword_count,
			'in_title'       => strpos( $content_lower, $keyword_lower ) !== false && strpos( $content_lower, $keyword_lower ) < 100,
			'in_first_para'  => stripos( $first_para, $keyword ) !== false,
			'optimal_density'=> $density >= 1 && $density <= 3,
			'lsi_keywords'   => $this->suggest_lsi_keywords( $keyword ),
		);
	}

	/**
	 * Suggest LSI keywords (placeholder).
	 *
	 * @param string $keyword Target keyword.
	 * @return array
	 */
	private function suggest_lsi_keywords( $keyword ) {
		// Placeholder LSI keywords - in production, this would call an AI API
		$lsi_map = array(
			'default' => array(
				array( 'keyword' => 'best practices', 'relevance' => 85 ),
				array( 'keyword' => 'guide', 'relevance' => 78 ),
				array( 'keyword' => 'tips', 'relevance' => 72 ),
				array( 'keyword' => 'tutorial', 'relevance' => 65 ),
				array( 'keyword' => 'examples', 'relevance' => 60 ),
			),
		);

		return $lsi_map['default'];
	}

	/**
	 * Analyze readability.
	 *
	 * @param string $content      Content text.
	 * @param int    $word_count   Word count.
	 * @param int    $sentence_count Sentence count.
	 * @return array
	 */
	private function analyze_readability( $content, $word_count, $sentence_count ) {
		if ( $sentence_count === 0 ) {
			$sentence_count = 1;
		}

		$avg_sentence_length = $word_count / $sentence_count;

		// Count words per sentence
		$sentences           = preg_split( '/[.!?]+/', $content );
		$long_sentences      = 0;
		$very_long_sentences = 0;

		foreach ( $sentences as $sentence ) {
			$words = str_word_count( trim( $sentence ) );
			if ( $words > 20 ) {
				$long_sentences++;
			}
			if ( $words > 30 ) {
				$very_long_sentences++;
			}
		}

		// Passive voice detection (basic pattern matching)
		$passive_patterns = array(
			'/\b(is|are|was|were|been|being)\s+\w+ed\b/i',
			'/\b(by)\s+[\w\s]+\b/i',
		);
		$passive_count = 0;
		foreach ( $passive_patterns as $pattern ) {
			$passive_count += preg_match_all( $pattern, $content );
		}

		// Calculate readability score (simplified Flesch)
		$flesch_score = 206.835 - ( 1.015 * ( $word_count / $sentence_count ) ) - ( 84.6 * ( $this->count_syllables( $content ) / $word_count ) );
		$flesch_score = max( 0, min( 100, round( $flesch_score, 1 ) ) );

		return array(
			'avg_sentence_length' => round( $avg_sentence_length, 1 ),
			'long_sentences'      => $long_sentences,
			'very_long_sentences' => $very_long_sentences,
			'passive_voice_count' => $passive_count,
			'flesch_score'        => $flesch_score,
			'readability_level'   => $this->get_readability_level( $flesch_score ),
		);
	}

	/**
	 * Count syllables in text (approximation).
	 *
	 * @param string $text Text to analyze.
	 * @return int
	 */
	private function count_syllables( $text ) {
		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z]/', ' ', $text );
		$words = explode( ' ', $text );
		$count = 0;

		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( empty( $word ) ) {
				continue;
			}

			// Simple syllable counting
			$word_count = str_word_count( $word, 1, 'aeiouy' );
			$letters    = str_split( $word );
			$prev_char  = '';

			// Count vowels
			foreach ( $letters as $char ) {
				if ( in_array( $char, array( 'a', 'e', 'i', 'o', 'u', 'y' ) ) && $prev_char !== $char ) {
					$count++;
				}
				$prev_char = $char;
			}
		}

		return $count > 0 ? $count : 1;
	}

	/**
	 * Get readability level from Flesch score.
	 *
	 * @param float $flesch_score Flesch score.
	 * @return string
	 */
	private function get_readability_level( $flesch_score ) {
		if ( $flesch_score >= 90 ) {
			return 'Very Easy';
		} elseif ( $flesch_score >= 80 ) {
			return 'Easy';
		} elseif ( $flesch_score >= 70 ) {
			return 'Fairly Easy';
		} elseif ( $flesch_score >= 60 ) {
			return 'Standard';
		} elseif ( $flesch_score >= 50 ) {
			return 'Fairly Difficult';
		} elseif ( $flesch_score >= 30 ) {
			return 'Difficult';
		} else {
			return 'Very Difficult';
		}
	}

	/**
	 * Calculate overall SEO score.
	 *
	 * @param array $keyword_analysis Keyword analysis data.
	 * @param array $readability      Readability data.
	 * @param array $headings         Headings data.
	 * @param int   $word_count       Word count.
	 * @return int
	 */
	private function calculate_seo_score( $keyword_analysis, $readability, $headings, $word_count ) {
		$score = 0;
		$max_score = 100;

		// Keyword optimization (35 points)
		if ( $keyword_analysis['has_keyword'] ) {
			$score += 10;
			if ( $keyword_analysis['optimal_density'] ) {
				$score += 15;
			}
			if ( $keyword_analysis['in_title'] ) {
				$score += 5;
			}
			if ( $keyword_analysis['in_first_para'] ) {
				$score += 5;
			}
		}

		// Content length (20 points)
		if ( $word_count >= 300 ) {
			$score += 10;
		}
		if ( $word_count >= 600 ) {
			$score += 10;
		}

		// Readability (20 points)
		if ( $readability['flesch_score'] >= 60 ) {
			$score += 20;
		} elseif ( $readability['flesch_score'] >= 40 ) {
			$score += 10;
		}

		// Headings structure (25 points)
		if ( ! empty( $headings['h1'] ) ) {
			$score += 10;
		}
		if ( ! empty( $headings['h2'] ) ) {
			$score += 10;
		}
		if ( ! empty( $headings['h3'] ) ) {
			$score += 5;
		}

		return min( $max_score, $score );
	}

	/**
	 * Get glow color based on score.
	 *
	 * @param int $score SEO score.
	 * @return string
	 */
	private function get_glow_color( $score ) {
		if ( $score >= 80 ) {
			return 'gold';
		} elseif ( $score >= 50 ) {
			return 'silver';
		} else {
			return 'blue';
		}
	}

	/**
	 * Generate AI suggestions.
	 *
	 * @param string $title    Post title.
	 * @param string $content  Content text.
	 * @param string $keyword  Target keyword.
	 * @param array  $headings Headings data.
	 * @return array
	 */
	private function generate_suggestions( $title, $content, $keyword, $headings ) {
		$suggestions = array();

		// Title suggestions
		$suggestions['title'] = array(
			'current' => $title,
			'better'  => $this->suggest_better_title( $title, $keyword ),
		);

		// Meta description suggestions
		$suggestions['meta_description'] = array(
			'suggested' => $this->suggest_meta_description( $content, $keyword ),
		);

		// Content gap suggestions
		$suggestions['content_gaps'] = $this->identify_content_gaps( $headings, $keyword );

		// Improvements
		$suggestions['improvements'] = array();

		if ( empty( $headings['h1'] ) ) {
			$suggestions['improvements'][] = array(
				'type'    => 'warning',
				'message' => 'Add an H1 heading to improve SEO structure.',
			);
		}

		if ( empty( $headings['h2'] ) ) {
			$suggestions['improvements'][] = array(
				'type'    => 'suggestion',
				'message' => 'Consider adding H2 headings to break up your content.',
			);
		}

		return $suggestions;
	}

	/**
	 * Suggest better title.
	 *
	 * @param string $title   Current title.
	 * @param string $keyword Target keyword.
	 * @return array
	 */
	private function suggest_better_title( $title, $keyword ) {
		$suggestions = array();

		if ( ! empty( $keyword ) && stripos( $title, $keyword ) === false ) {
			$suggestions[] = sprintf( 'Consider adding "%s" to your title.', $keyword );
		}

		if ( strlen( $title ) > 60 ) {
			$suggestions[] = 'Your title is too long. Keep it under 60 characters.';
		} elseif ( strlen( $title ) < 30 ) {
			$suggestions[] = 'Your title is quite short. Consider making it more descriptive.';
		}

		if ( empty( $suggestions ) ) {
			$suggestions[] = 'Your title looks good!';
		}

		return $suggestions;
	}

	/**
	 * Suggest meta description.
	 *
	 * @param string $content Content text.
	 * @param string $keyword Target keyword.
	 * @return array
	 */
	private function suggest_meta_description( $content, $keyword ) {
		$description = substr( wp_strip_all_tags( $content ), 0, 160 );

		if ( ! empty( $keyword ) && stripos( $description, $keyword ) === false ) {
			$description = substr( $description, 0, 150 ) . '...';
		}

		return array(
			array(
				'text'    => $description,
				'length'  => strlen( $description ),
				'optimal' => strlen( $description ) >= 120 && strlen( $description ) <= 160,
			),
		);
	}

	/**
	 * Identify content gaps.
	 *
	 * @param array  $headings Headings data.
	 * @param string $keyword  Target keyword.
	 * @return array
	 */
	private function identify_content_gaps( $headings, $keyword ) {
		$gaps = array();

		if ( empty( $headings['h2'] ) ) {
			$gaps[] = array(
				'type'    => 'heading',
				'message' => 'Missing H2 headings. Add section headings to improve readability.',
			);
		}

		if ( ! empty( $keyword ) ) {
			$has_related = false;
			foreach ( $headings as $level => $level_headings ) {
				foreach ( $level_headings as $heading ) {
					if ( stripos( $heading, $keyword ) !== false ) {
						$has_related = true;
						break 2;
					}
				}
			}

			if ( ! $has_related ) {
				$gaps[] = array(
					'type'    => 'content',
					'message' => sprintf( 'Consider adding sections that relate to "%s".', $keyword ),
				);
			}
		}

		return $gaps;
	}
}

// Initialize the feature
new Invenzia_AI_Content_Optimizer();
