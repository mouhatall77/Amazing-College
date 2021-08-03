(function(wp) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	function sirscFeaturedImageButtons(OriginalComponent) {
		return function(props) {
			const thid = props.featuredImageId;

			if ( 0 == thid || 'undefined' == thid ) {
				// Return the default if no image is set.
				return (
					el(
						wp.element.Fragment,
						{},
						el(
							OriginalComponent,
							props
						),
					)
				);
			}

			const handleInfoClick = (event) => {
				sirscSingleDetails( thid );
			}

			const handleRegenerateClick = (event) => {
				sirscSingleRegenerate( thid );
			}

			const handleRawCleanupClick = (event) => {
				sirscSingleCleanup( thid );
			}

			// Return the buttons as expected.
			return (
				el(
					wp.element.Fragment,
					{},
					el(
						'div', {
							id: 'sirsc-buttons-wrapper-' + thid,
							className: 'sirsc-feature as-target sirsc-buttons tiny ' + sirscSettings.display_small_buttons
						}, el(
							'div', {
								className: 'button-primary',
								onClick: handleInfoClick,
								title: sirscSettings.button_options
							}, el(
								'div', {
									className: 'dashicons dashicons-format-gallery'
								}
							),
							sirscSettings.button_details
						), el(
							'div', {
								className: 'button-primary',
								onClick: handleRegenerateClick,
								title: sirscSettings.button_regenerate
							}, el(
								'div', {
									className: 'dashicons dashicons-update'
								}
							),
							sirscSettings.button_regenerate
						), el(
							'div', {
								className: 'button-primary',
								onClick: handleRawCleanupClick,
								title: sirscSettings.button_cleanup
							}, el(
								'div', {
									className: 'dashicons dashicons-editor-removeformatting'
								}
							),
							sirscSettings.button_cleanup
						)
					),
					el(
						'br'
					),
					el(
						OriginalComponent,
						props
					)
				)
			);
		}
	}

	// Add the custom hook for the Image Regenerate & Select Crop buttons.
	wp.hooks.addFilter('editor.PostFeaturedImage', 'image-regenerate-select-crop/sirsc-block', sirscFeaturedImageButtons);

	// Instruct the block th use the custom size.
	var withImageSize = function( size, mediaId, postId ) {
		return sirscSettings.admin_featured_size;
	};
	wp.hooks.addFilter( 'editor.PostFeaturedImage.imageSize', 'image-regenerate-select-crop/sirsc-block', withImageSize );

}) (
	window.wp
);
