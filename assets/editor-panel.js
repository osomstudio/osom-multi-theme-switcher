/**
 * Shows which theme the edited item renders in, and — when the editor happens to
 * run a different theme — offers a one-click fix.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! window.omtsEditor ) {
		return;
	}

	var data = window.omtsEditor;
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var components = wp.components;
	var PluginDocumentSettingPanel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	/**
	 * Switch the dashboard theme for the current user and reload.
	 *
	 * @param {string} theme Theme slug.
	 */
	function switchEditorTheme( theme ) {
		var body = new window.FormData();

		body.append( 'action', 'omts_switch_admin_theme' );
		body.append( 'nonce', data.switchNonce );
		body.append( 'theme', theme );

		window.fetch( data.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result && result.success ) {
					window.location.reload();
				}
			} );
	}

	/**
	 * Warn once per editor session about a theme mismatch.
	 */
	function warnAboutMismatch() {
		if ( ! data.mismatch || ! wp.data || ! wp.data.dispatch( 'core/notices' ) ) {
			return;
		}

		var actions = [];

		if ( data.canManage ) {
			actions.push( {
				label: wp.i18n.sprintf(
					/* translators: %s: Theme name */
					__( 'Switch editor to %s', 'osom-multi-theme-switcher' ),
					data.ruleThemeName
				),
				onClick: function () {
					switchEditorTheme( data.ruleTheme );
				}
			} );
		}

		wp.data.dispatch( 'core/notices' ).createWarningNotice(
			wp.i18n.sprintf(
				/* translators: 1: Theme the item renders in, 2: Theme the editor runs */
				__( 'This item is displayed in the %1$s theme, but the editor is running %2$s. Blocks and fields may differ from the frontend.', 'osom-multi-theme-switcher' ),
				data.ruleThemeName,
				data.editorName
			),
			{ id: 'omts-theme-mismatch', isDismissible: true, actions: actions }
		);
	}

	/**
	 * The document sidebar panel.
	 *
	 * @return {Object} Panel element.
	 */
	function ThemePanel() {
		// Notices are dispatched from an effect, never during render.
		wp.element.useEffect( function () {
			warnAboutMismatch();
		}, [] );

		var children = [
			el(
				'p',
				{ className: 'omts-editor-theme', key: 'theme' },
				el( 'span', { className: 'omts-editor-theme__label' }, __( 'Renders in', 'osom-multi-theme-switcher' ) ),
				el( 'strong', { className: 'omts-editor-theme__name' }, data.ruleThemeName )
			),
			el(
				'p',
				{ className: 'omts-editor-source', key: 'source' },
				data.hasRule
					? wp.i18n.sprintf(
						/* translators: %s: Rule description */
						__( 'Set by rule: %s', 'osom-multi-theme-switcher' ),
						data.ruleLabel
					)
					: __( 'No rule applies — this item uses the main theme.', 'osom-multi-theme-switcher' )
			)
		];

		if ( data.mismatch ) {
			children.push(
				el(
					components.Notice,
					{ status: 'warning', isDismissible: false, key: 'mismatch' },
					el(
						'p',
						null,
						wp.i18n.sprintf(
							/* translators: %s: Theme name */
							__( 'The editor is running %s, so the block list may not match the frontend.', 'osom-multi-theme-switcher' ),
							data.editorName
						)
					),
					data.canManage
						? el(
							components.Button,
							{
								variant: 'primary',
								onClick: function () {
									switchEditorTheme( data.ruleTheme );
								}
							},
							wp.i18n.sprintf(
								/* translators: %s: Theme name */
								__( 'Switch editor to %s', 'osom-multi-theme-switcher' ),
								data.ruleThemeName
							)
						)
						: null
				)
			);
		} else {
			children.push(
				el(
					'p',
					{ className: 'omts-editor-match', key: 'match' },
					__( 'The editor is running the same theme, so the blocks and fields here match the frontend.', 'osom-multi-theme-switcher' )
				)
			);
		}

		if ( data.canManage ) {
			children.push(
				el(
					'a',
					{ href: data.rulesUrl, className: 'omts-editor-link', key: 'link' },
					__( 'Manage theme rules', 'osom-multi-theme-switcher' )
				)
			);
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'omts-theme-panel',
				title: __( 'Theme for this item', 'osom-multi-theme-switcher' ),
				className: 'omts-editor-panel'
			},
			children
		);
	}

	wp.plugins.registerPlugin( 'omts-theme-context', {
		render: ThemePanel,
		icon: 'admin-appearance'
	} );
} )( window.wp );
