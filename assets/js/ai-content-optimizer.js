/**
 * AI Content Optimizer - Gutenberg Sidebar Plugin
 *
 * @package Invenzia_SEO_Matrix
 */

( function() {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { useState, useEffect, useRef } = wp.element;
	const { Panel, PanelBody, PanelRow } = wp.components;
	const { Spinner } = wp.components;
	const { __ } = wp.i18n;
	const { apiFetch } = wp;
	const { subscribe, select } = wp.data;

	/**
	 * SEO Score Component
	 */
	const SEOScoreMeter = ( { score, glowColor } ) => {
		const radius = 50;
		const circumference = 2 * Math.PI * radius;
		const offset = circumference - ( score / 100 ) * circumference;

		return (
			<div className="invenzia-ai-score-container">
				<div className={ `invenzia-ai-score-meter invenzia-ai-glow-${glowColor}` }>
					<svg width="120" height="120">
						<defs>
							<linearGradient id="goldGradient" x1="0%" y1="0%" x2="100%" y2="100%">
								<stop offset="0%" stopColor="#ffd700" />
								<stop offset="100%" stopColor="#ffed4a" />
							</linearGradient>
							<linearGradient id="silverGradient" x1="0%" y1="0%" x2="100%" y2="100%">
								<stop offset="0%" stopColor="#c0c0c0" />
								<stop offset="100%" stopColor="#e8e8e8" />
							</linearGradient>
							<linearGradient id="blueGradient" x1="0%" y1="0%" x2="100%" y2="100%">
								<stop offset="0%" stopColor="#6495ed" />
								<stop offset="100%" stopColor="#87ceeb" />
							</linearGradient>
						</defs>
						<circle
							className="invenzia-ai-score-bg"
							cx="60"
							cy="60"
							r={ radius }
							fill="none"
							strokeWidth="8"
						/>
						<circle
							className="invenzia-ai-score-progress"
							cx="60"
							cy="60"
							r={ radius }
							fill="none"
							strokeWidth="8"
							strokeDasharray={ circumference }
							strokeDashoffset={ offset }
							strokeLinecap="round"
							style={ {
								transform: 'rotate(-90deg)',
								transformOrigin: '60px 60px',
							} }
						/>
					</svg>
					<div className="invenzia-ai-score-value">{ score }</div>
				</div>
				<p className="invenzia-ai-score-label">SEO Score</p>
			</div>
		);
	};

	/**
	 * Main AI Content Optimizer Component
	 */
	const AIContentOptimizer = () => {
		const [ analysis, setAnalysis ] = useState( null );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( null );
		const [ showSuggestions, setShowSuggestions ] = useState( false );
		const debounceTimer = useRef( null );

		// Get current post content and title
		const getEditorData = () => {
			const editor = select( 'core/editor' );
			return {
				title: editor.getEditedPostAttribute( 'title' ) || '',
				content: editor.getEditedPostAttribute( 'content' ) || '',
				keyword: editor.getEditedPostAttribute( 'meta' )?._invenzia_focus_keyword || '',
			};
		};

		// Analyze content
		const analyzeContent = async () => {
			const { title, content, keyword } = getEditorData();

			if ( ! content.trim() ) {
				setAnalysis( null );
				return;
			}

			setLoading( true );
			setError( null );

			try {
				const response = await apiFetch( {
					path: '/invenzia-seo/v1/analyze',
					method: 'POST',
					data: {
						title,
						content,
						keyword,
					},
				} );

				setAnalysis( response );
			} catch ( err ) {
				setError( err.message || 'Analysis failed. Please try again.' );
			} finally {
				setLoading( false );
			}
		};

		// Debounced analysis on content change
		useEffect( () => {
			const unsubscribe = subscribe( () => {
				const { content, title } = getEditorData();

				if ( debounceTimer.current ) {
					clearTimeout( debounceTimer.current );
				}

				debounceTimer.current = setTimeout( () => {
					if ( content.trim() || title.trim() ) {
						analyzeContent();
					}
				}, 1500 ); // 1.5 second debounce
			} );

			// Initial analysis
			analyzeContent();

			return () => {
				unsubscribe();
				if ( debounceTimer.current ) {
					clearTimeout( debounceTimer.current );
				}
			};
		}, [] );

		if ( ! analysis && ! loading ) {
			return (
				<div className="invenzia-ai-empty-state">
					<p>{ __( 'Start writing to see AI-powered SEO recommendations.', 'invenzia-seo' ) }</p>
				</div>
			);
		}

		if ( loading ) {
			return (
				<div className="invenzia-ai-loading-state">
					<Spinner />
					<p>{ __( 'Analyzing your content...', 'invenzia-seo' ) }</p>
				</div>
			);
		}

		if ( error ) {
			return (
				<div className="invenzia-ai-error-state">
					<p>{ error }</p>
					<button
						className="invenzia-ai-retry-btn"
						onClick={ analyzeContent }
					>
						{ __( 'Retry', 'invenzia-seo' ) }
					</button>
				</div>
			);
		}

		return (
			<div className="invenzia-ai-optimizer">
				{/* SEO Score */}
				<SEOScoreMeter score={ analysis.seo_score } glowColor={ analysis.glow_color } />

				{/* Keyword Optimization */}
				<PanelBody
					title={ __( 'Keyword Optimization', 'invenzia-seo' ) }
					initialOpen={ true }
					className="invenzia-ai-panel-body"
				>
					<PanelRow>
						<div className="invenzia-ai-metric">
							<span className="invenzia-ai-metric-label">{ __( 'Keyword Density', 'invenzia-seo' ) }</span>
							<span className={ `invenzia-ai-metric-value ${ analysis.keyword.optimal_density ? 'optimal' : 'suboptimal' }` }>
								{ analysis.keyword.density }%
							</span>
						</div>
					</PanelRow>
					{ analysis.keyword.has_keyword && (
						<>
							<PanelRow>
								<div className="invenzia-ai-checklist">
									<span className={ `invenzia-ai-check ${ analysis.keyword.in_title ? 'checked' : 'unchecked' }` }>
										{ analysis.keyword.in_title ? '✓' : '✗' }
									</span>
									<span>{ __( 'In title', 'invenzia-seo' ) }</span>
								</div>
							</PanelRow>
							<PanelRow>
								<div className="invenzia-ai-checklist">
									<span className={ `invenzia-ai-check ${ analysis.keyword.in_first_para ? 'checked' : 'unchecked' }` }>
										{ analysis.keyword.in_first_para ? '✓' : '✗' }
									</span>
									<span>{ __( 'In first paragraph', 'invenzia-seo' ) }</span>
								</div>
							</PanelRow>
							{ analysis.keyword.lsi_keywords.length > 0 && (
								<PanelRow>
									<div className="invenzia-ai-lsi-keywords">
										<h4>{ __( 'LSI Keywords', 'invenzia-seo' ) }</h4>
										<ul>
											{ analysis.keyword.lsi_keywords.map( ( lsi, index ) => (
												<li key={ index }>
													<span className="invenzia-ai-lsi-keyword">{ lsi.keyword }</span>
													<span className="invenzia-ai-lsi-relevance">{ lsi.relevance }%</span>
												</li>
											) ) }
										</ul>
									</div>
								</PanelRow>
							) }
						</>
					) }
				</PanelBody>

				{/* Readability Analysis */}
				<PanelBody
					title={ __( 'Readability', 'invenzia-seo' ) }
					initialOpen={ false }
					className="invenzia-ai-panel-body"
				>
					<PanelRow>
						<div className="invenzia-ai-metric">
							<span className="invenzia-ai-metric-label">{ __( 'Readability Score', 'invenzia-seo' ) }</span>
							<span className="invenzia-ai-metric-value">
								{ analysis.readability.flesch_score }/100
							</span>
						</div>
					</PanelRow>
					<PanelRow>
						<div className="invenzia-ai-metric">
							<span className="invenzia-ai-metric-label">{ __( 'Level', 'invenzia-seo' ) }</span>
							<span className="invenzia-ai-metric-value">
								{ analysis.readability.readability_level }
							</span>
						</div>
					</PanelRow>
					<PanelRow>
						<div className="invenzia-ai-metric">
							<span className="invenzia-ai-metric-label">{ __( 'Avg. Sentence Length', 'invenzia-seo' ) }</span>
							<span className="invenzia-ai-metric-value">
								{ analysis.readability.avg_sentence_length } words
							</span>
						</div>
					</PanelRow>
					<PanelRow>
						<div className="invenzia-ai-metric">
							<span className="invenzia-ai-metric-label">{ __( 'Word Count', 'invenzia-seo' ) }</span>
							<span className="invenzia-ai-metric-value">
								{ analysis.word_count }
							</span>
						</div>
					</PanelRow>
				</PanelBody>

				{/* AI Suggestions */}
				<PanelBody
					title={ __( 'AI Suggestions', 'invenzia-seo' ) }
					initialOpen={ false }
					className="invenzia-ai-panel-body"
				>
					{ analysis.suggestions.improvements.length > 0 && (
						<div className="invenzia-ai-suggestions-list">
							{ analysis.suggestions.improvements.map( ( suggestion, index ) => (
								<div key={ index } className={ `invenzia-ai-suggestion invenzia-ai-suggestion-${suggestion.type}` }>
									<span className="invenzia-ai-suggestion-icon">
										{ suggestion.type === 'warning' ? '⚠' : '💡' }
									</span>
									<span>{ suggestion.message }</span>
								</div>
							) ) }
						</div>
					) }

					{ analysis.suggestions.title.better.length > 0 && (
						<PanelRow>
							<div className="invenzia-ai-title-suggestions">
								<h4>{ __( 'Title Improvements', 'invenzia-seo' ) }</h4>
								<ul>
									{ analysis.suggestions.title.better.map( ( suggestion, index ) => (
										<li key={ index }>{ suggestion }</li>
									) ) }
								</ul>
							</div>
						</PanelRow>
					)}

					{ analysis.suggestions.content_gaps.length > 0 && (
						<PanelRow>
							<div className="invenzia-ai-content-gaps">
								<h4>{ __( 'Content Gaps', 'invenzia-seo' ) }</h4>
								<ul>
									{ analysis.suggestions.content_gaps.map( ( gap, index ) => (
										<li key={ index }>{ gap.message }</li>
									) ) }
								</ul>
							</div>
						</PanelRow>
					)}
				</PanelBody>

				{/* Refresh Button */}
				<div className="invenzia-ai-actions">
					<button
						className="invenzia-ai-refresh-btn"
						onClick={ analyzeContent }
						disabled={ loading }
					>
						{ __( 'Refresh Analysis', 'invenzia-seo' ) }
					</button>
				</div>
			</div>
		);
	};

	// Register the plugin
	registerPlugin( 'invenzia-ai-content-optimizer', {
		render: () => (
			<>
				<PluginSidebarMoreMenuItem
					target="invenzia-ai-content-optimizer-sidebar"
					icon="art"
				>
					{ __( 'AI Content Optimizer', 'invenzia-seo' ) }
				</PluginSidebarMoreMenuItem>
				<PluginSidebar
					name="invenzia-ai-content-optimizer-sidebar"
					title={ __( 'AI Content Optimizer', 'invenzia-seo' ) }
					icon="art"
				>
					<AIContentOptimizer />
				</PluginSidebar>
			</>
		),
	} );
} )();
