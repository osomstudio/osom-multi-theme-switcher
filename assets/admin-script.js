/* global jQuery, omtsAjax */
jQuery( document ).ready( function ( $ ) {
	'use strict';

	// Types that need a two-step object -> item selection.
	var TWO_STEP_TYPES = [ 'custom_post_type', 'taxonomy' ];

	// Types that load items directly, with no intermediate step.
	var DIRECT_ITEM_TYPES = [ 'page', 'post' ];

	var i18n = omtsAjax.i18n;

	/**
	 * Show a message in the page notice area instead of a browser alert.
	 *
	 * @param {string} type    One of success, error, warning, info.
	 * @param {string} message Message text.
	 * @param {Object} action  Optional { label, onClick } button.
	 */
	function notice( type, message, action ) {
		var box = $( '<div class="notice notice-' + type + ' is-dismissible omts-notice"><p></p></div>' );

		box.find( 'p' ).text( message );

		if ( action ) {
			var button = $( '<button type="button" class="button button-small omts-notice-action"></button>' )
				.text( action.label )
				.on( 'click', function () {
					box.remove();
					action.onClick();
				} );

			box.find( 'p' ).append( ' ' ).append( button );
		}

		box.append( '<button type="button" class="notice-dismiss"></button>' );
		box.find( '.notice-dismiss' ).on( 'click', function () {
			box.remove();
		} );

		$( '#omts-notices' ).prepend( box );

		if ( 'error' !== type ) {
			window.setTimeout( function () {
				box.fadeOut( 200, function () {
					box.remove();
				} );
			}, 8000 );
		}
	}

	/**
	 * Send a request to admin-ajax with the plugin nonce.
	 *
	 * @param {string} action Action name without the omts_ prefix.
	 * @param {Object} data   Payload.
	 * @return {Object} jQuery promise.
	 */
	function request( action, data ) {
		return $.ajax( {
			url: omtsAjax.ajaxurl,
			type: 'POST',
			data: $.extend( { action: 'omts_' + action, nonce: omtsAjax.nonce }, data || {} )
		} );
	}

	/**
	 * Renumber rows after the list changes, so positions match the stored order.
	 */
	function renumberRows() {
		$( '#omts-rules-tbody tr' ).not( '.omts-no-rules' ).each( function ( position ) {
			var row = $( this );

			row.attr( 'data-index', position );
			row.find( '.omts-position' ).text( position + 1 );
			row.find( '[data-index]' ).attr( 'data-index', position );
		} );
	}

	/**
	 * Drop the "no rules yet" placeholder once a rule exists.
	 */
	function clearEmptyState() {
		$( '#omts-rules-tbody .omts-no-rules' ).remove();
	}

	/**
	 * Put the placeholder back when the last rule is gone.
	 */
	function restoreEmptyState() {
		if ( 0 === $( '#omts-rules-tbody tr' ).not( '.omts-no-rules' ).length ) {
			$( '#omts-rules-tbody' ).html(
				$( '<tr class="omts-no-rules"><td colspan="6"></td></tr>' ).find( 'td' ).text( i18n.noRules ).end()
			);
		}
	}

	// ---------------------------------------------------------------------
	// Add rule form
	// ---------------------------------------------------------------------

	$( '#omts-rule-type' ).on( 'change', function () {
		var ruleType = $( this ).val();
		var labels = omtsAjax.labels[ ruleType ] || {};

		$( '.omts-rule-row' ).hide();
		$( '#omts-rule-object' ).html( '<option value="">' + omtsAjax.selectLabel + '</option>' );
		$( '#omts-rule-item' ).html( '<option value="">' + omtsAjax.selectLabel + '</option>' );
		$( '#omts-item-help' ).text( labels.help || '' );

		if ( ! ruleType ) {
			return;
		}

		if ( labels.object ) {
			$( '#omts-rule-object-label' ).text( labels.object );
		}

		if ( labels.item ) {
			$( '#omts-rule-item-label' ).text( labels.item );
		}

		if ( 'url' === ruleType ) {
			$( '#omts-url-row' ).show();
			return;
		}

		if ( -1 !== TWO_STEP_TYPES.indexOf( ruleType ) ) {
			$( '#omts-rule-object-row' ).show();
			loadRuleObjects( ruleType );
			return;
		}

		if ( -1 !== DIRECT_ITEM_TYPES.indexOf( ruleType ) ) {
			$( '#omts-rule-item-row' ).show();
			loadRuleItems( ruleType, '' );
		}
	} );

	$( '#omts-rule-object' ).on( 'change', function () {
		var objectType = $( this ).val();
		var ruleType = $( '#omts-rule-type' ).val();

		$( '#omts-rule-item' ).html( '<option value="">' + omtsAjax.selectLabel + '</option>' );
		$( '#omts-rule-item-row' ).hide();

		if ( ! objectType ) {
			return;
		}

		$( '#omts-rule-item-row' ).show();
		loadRuleItems( ruleType, objectType );
	} );

	/**
	 * Load the first level of a cascading selector.
	 *
	 * @param {string} ruleType Selected rule type.
	 */
	function loadRuleObjects( ruleType ) {
		var spinner = $( '#omts-object-spinner' );
		var select = $( '#omts-rule-object' );

		spinner.addClass( 'is-active' );
		select.prop( 'disabled', true );

		request( 'get_rule_objects', { rule_type: ruleType } )
			.done( function ( response ) {
				if ( response.success && response.data.objects ) {
					select.html( buildOptions( response.data.objects ) );
				}
			} )
			.fail( function () {
				notice( 'error', i18n.errorObjects );
			} )
			.always( function () {
				spinner.removeClass( 'is-active' );
				select.prop( 'disabled', false );
			} );
	}

	/**
	 * Load the second level of a cascading selector.
	 *
	 * @param {string} ruleType   Selected rule type.
	 * @param {string} objectType Selected object type.
	 */
	function loadRuleItems( ruleType, objectType ) {
		var spinner = $( '#omts-item-spinner' );
		var select = $( '#omts-rule-item' );

		spinner.addClass( 'is-active' );
		select.prop( 'disabled', true );

		request( 'get_rule_items', { rule_type: ruleType, object_type: objectType } )
			.done( function ( response ) {
				if ( response.success && response.data.items ) {
					select.html( buildOptions( response.data.items ) );
				}
			} )
			.fail( function () {
				notice( 'error', i18n.errorItems );
			} )
			.always( function () {
				spinner.removeClass( 'is-active' );
				select.prop( 'disabled', false );
			} );
	}

	/**
	 * Build option markup for a select.
	 *
	 * @param {Array} items Items with value and label.
	 * @return {jQuery} Option elements.
	 */
	function buildOptions( items ) {
		var options = $( '<select></select>' ).append(
			$( '<option></option>' ).val( '' ).text( omtsAjax.selectLabel )
		);

		items.forEach( function ( item ) {
			options.append( $( '<option></option>' ).val( item.value ).text( item.label ) );
		} );

		return options.children();
	}

	$( '#omts-add-rule-form' ).on( 'submit', function ( event ) {
		event.preventDefault();

		var button = $( this ).find( 'button[type="submit"]' );
		var ruleType = $( '#omts-rule-type' ).val();
		var theme = $( '#omts-theme-select' ).val();
		var payload = { rule_type: ruleType, theme: theme };

		if ( ! ruleType ) {
			notice( 'error', i18n.needType );
			return;
		}

		if ( ! theme ) {
			notice( 'error', i18n.needTheme );
			return;
		}

		if ( 'url' === ruleType ) {
			var url = $.trim( $( '#omts-url-input' ).val() );

			if ( ! url ) {
				notice( 'error', i18n.needUrl );
				return;
			}

			payload.custom_url = url;
			payload.url_match = $( 'input[name="url_match"]:checked' ).val();
		} else if ( -1 !== TWO_STEP_TYPES.indexOf( ruleType ) ) {
			var objectType = $( '#omts-rule-object' ).val();

			if ( ! objectType ) {
				notice( 'error', i18n.needObject );
				return;
			}

			if ( ! $( '#omts-rule-item' ).val() ) {
				notice( 'error', i18n.needItem );
				return;
			}

			payload.object_type = objectType;
			payload.item_id = $( '#omts-rule-item' ).val();
		} else {
			if ( ! $( '#omts-rule-item' ).val() ) {
				notice( 'error', i18n.needItem );
				return;
			}

			payload.item_id = $( '#omts-rule-item' ).val();
		}

		button.prop( 'disabled', true );

		request( 'save_rules', payload )
			.done( function ( response ) {
				if ( ! response.success ) {
					notice( 'error', response.data || i18n.errorSave );
					return;
				}

				clearEmptyState();
				$( '#omts-rules-tbody' ).append( response.data.row );
				renumberRows();
				notice( 'success', i18n.ruleAdded );

				if ( response.data.warning ) {
					notice( 'warning', response.data.warning );
				}

				$( '#omts-add-rule-form' )[ 0 ].reset();
				$( '.omts-rule-row' ).hide();
			} )
			.fail( function () {
				notice( 'error', i18n.errorSave );
			} )
			.always( function () {
				button.prop( 'disabled', false );
			} );
	} );

	// ---------------------------------------------------------------------
	// Rules table: delete, undo, pause, edit, reorder
	// ---------------------------------------------------------------------

	$( document ).on( 'click', '.omts-delete-rule', function () {
		var row = $( this ).closest( 'tr' );

		request( 'delete_rule', { index: row.data( 'index' ) } )
			.done( function ( response ) {
				if ( ! response.success ) {
					notice( 'error', response.data || i18n.errorDelete );
					return;
				}

				row.fadeOut( 200, function () {
					row.remove();
					renumberRows();
					restoreEmptyState();
				} );

				notice( 'success', i18n.ruleDeleted, {
					label: i18n.undo,
					onClick: function () {
						request( 'restore_rule', {} ).done( function () {
							window.location.reload();
						} );
					}
				} );
			} )
			.fail( function () {
				notice( 'error', i18n.errorDelete );
			} );
	} );

	$( document ).on( 'change', '.omts-toggle-rule', function () {
		var checkbox = $( this );
		var row = checkbox.closest( 'tr' );

		request( 'toggle_rule', {
			index: row.data( 'index' ),
			enabled: checkbox.is( ':checked' ) ? '1' : '0'
		} )
			.done( function ( response ) {
				if ( ! response.success ) {
					notice( 'error', response.data || i18n.errorGeneric );
					checkbox.prop( 'checked', ! checkbox.is( ':checked' ) );
					return;
				}

				row.replaceWith( response.data.row );
				renumberRows();
			} )
			.fail( function () {
				notice( 'error', i18n.errorGeneric );
			} );
	} );

	$( document ).on( 'click', '.omts-edit-rule', function () {
		var row = $( this ).closest( 'tr' );

		if ( row.hasClass( 'omts-editing' ) ) {
			return;
		}

		row.addClass( 'omts-editing' );

		var themeSelect = $( '<select class="omts-edit-theme"></select>' );

		omtsAjax.themes.forEach( function ( theme ) {
			themeSelect.append(
				$( '<option></option>' ).val( theme.value ).text( theme.label )
			);
		} );

		themeSelect.val( row.data( 'theme' ) );
		row.find( '.omts-cell-theme' ).empty().append( themeSelect );

		if ( 'url' === row.data( 'type' ) ) {
			var input = $( '<input type="text" class="omts-edit-value regular-text">' ).val( row.data( 'value' ) );
			var matchSelect = $( '<select class="omts-edit-match"></select>' )
				.append( $( '<option value="exact"></option>' ).text( i18n.matchExact || 'exact' ) )
				.append( $( '<option value="prefix"></option>' ).text( i18n.matchPrefix || 'prefix' ) );

			matchSelect.val( row.data( 'match' ) );
			row.find( '.omts-cell-target' ).empty().append( input ).append( ' ' ).append( matchSelect );
		}

		row.find( '.omts-cell-actions' ).empty()
			.append( $( '<button class="button button-small button-primary omts-save-rule"></button>' ).text( i18n.save ) )
			.append( ' ' )
			.append( $( '<button class="button button-small omts-cancel-edit"></button>' ).text( i18n.cancel ) );
	} );

	$( document ).on( 'click', '.omts-cancel-edit', function () {
		window.location.reload();
	} );

	$( document ).on( 'click', '.omts-save-rule', function () {
		var row = $( this ).closest( 'tr' );
		var payload = {
			index: row.data( 'index' ),
			theme: row.find( '.omts-edit-theme' ).val()
		};

		if ( 'url' === row.data( 'type' ) ) {
			payload.value = row.find( '.omts-edit-value' ).val();
			payload.match = row.find( '.omts-edit-match' ).val();
		}

		request( 'update_rule', payload )
			.done( function ( response ) {
				if ( ! response.success ) {
					notice( 'error', response.data || i18n.errorGeneric );
					return;
				}

				row.replaceWith( response.data.row );
				renumberRows();
				notice( 'success', i18n.ruleUpdated );
			} )
			.fail( function () {
				notice( 'error', i18n.errorGeneric );
			} );
	} );

	if ( $.fn.sortable ) {
		$( '#omts-rules-tbody' ).sortable( {
			handle: '.omts-drag-handle',
			axis: 'y',
			helper: function ( event, row ) {
				// Lock cell widths so the dragged row keeps its shape.
				row.children().each( function () {
					$( this ).width( $( this ).width() );
				} );

				return row;
			},
			update: function () {
				var order = $( '#omts-rules-tbody tr' ).not( '.omts-no-rules' ).map( function () {
					return $( this ).data( 'index' );
				} ).get();

				request( 'reorder_rules', { order: order } )
					.done( function ( response ) {
						if ( ! response.success ) {
							notice( 'error', response.data || i18n.errorGeneric );
							return;
						}

						renumberRows();
						notice( 'success', i18n.orderSaved );
					} )
					.fail( function () {
						notice( 'error', i18n.errorGeneric );
					} );
			}
		} );
	}

	// ---------------------------------------------------------------------
	// Address tester
	// ---------------------------------------------------------------------

	$( '#omts-test-form' ).on( 'submit', function ( event ) {
		event.preventDefault();

		var url = $.trim( $( '#omts-test-url' ).val() );
		var result = $( '#omts-test-result' );
		var spinner = $( '#omts-test-spinner' );

		if ( ! url ) {
			notice( 'error', i18n.testEmpty );
			return;
		}

		spinner.addClass( 'is-active' );
		result.removeClass( 'is-visible' ).empty();

		request( 'test_url', { url: url } )
			.done( function ( response ) {
				if ( ! response.success ) {
					notice( 'error', response.data || i18n.errorGeneric );
					return;
				}

				var data = response.data;
				var heading = $( '<p class="omts-test-theme"></p>' ).text( data.themeName );
				var detail = $( '<p class="omts-test-detail"></p>' ).text(
					data.isDefault
						? i18n.testDefault
						: i18n.matchedRule.replace( '%1$d', data.position ).replace( '%2$s', data.rule )
				);

				result.append( heading ).append( detail ).addClass( 'is-visible' );
			} )
			.fail( function () {
				notice( 'error', i18n.errorGeneric );
			} )
			.always( function () {
				spinner.removeClass( 'is-active' );
			} );
	} );

	// ---------------------------------------------------------------------
	// Broken rule cleanup
	// ---------------------------------------------------------------------

	$( document ).on( 'click', '.omts-cleanup-rules', function () {
		var button = $( this );

		button.prop( 'disabled', true );

		request( 'cleanup_rules', {} )
			.done( function ( response ) {
				if ( ! response.success ) {
					notice( 'error', response.data || i18n.errorGeneric );
					button.prop( 'disabled', false );
					return;
				}

				notice( 'success', i18n.cleanupDone );
				window.setTimeout( function () {
					window.location.reload();
				}, 600 );
			} )
			.fail( function () {
				notice( 'error', i18n.errorGeneric );
				button.prop( 'disabled', false );
			} );
	} );

	// ---------------------------------------------------------------------
	// REST prefixes (advanced)
	// ---------------------------------------------------------------------

	$( '#omts-add-prefix-form' ).on( 'submit', function ( event ) {
		event.preventDefault();

		var theme = $( '#omts-prefix-theme-select' ).val();
		var button = $( this ).find( 'button[type="submit"]' );

		if ( ! theme ) {
			notice( 'error', i18n.needTheme );
			return;
		}

		button.prop( 'disabled', true );

		request( 'save_rest_prefix', {
			theme: theme,
			prefix: $.trim( $( '#omts-rest-prefix-input' ).val() )
		} )
			.done( function ( response ) {
				if ( ! response.success ) {
					notice( 'error', response.data || i18n.errorGeneric );
					return;
				}

				notice( 'success', i18n.prefixSaved );
				window.setTimeout( function () {
					window.location.reload();
				}, 600 );
			} )
			.fail( function () {
				notice( 'error', i18n.errorGeneric );
			} )
			.always( function () {
				button.prop( 'disabled', false );
			} );
	} );

	$( document ).on( 'click', '.omts-delete-prefix', function () {
		var row = $( this ).closest( 'tr' );

		request( 'delete_rest_prefix', { index: row.data( 'index' ) } )
			.done( function ( response ) {
				if ( ! response.success ) {
					notice( 'error', response.data || i18n.errorGeneric );
					return;
				}

				row.fadeOut( 200, function () {
					row.remove();
				} );

				notice( 'success', i18n.prefixDeleted );
			} )
			.fail( function () {
				notice( 'error', i18n.errorGeneric );
			} );
	} );

	// Remember whether the "how it works" panel is open.
	var howto = document.getElementById( 'omts-howto' );

	if ( howto && window.localStorage ) {
		howto.open = 'closed' !== window.localStorage.getItem( 'omtsHowtoState' );
		howto.addEventListener( 'toggle', function () {
			window.localStorage.setItem( 'omtsHowtoState', howto.open ? 'open' : 'closed' );
		} );
	}
} );
