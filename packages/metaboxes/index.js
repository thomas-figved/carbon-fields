/* eslint no-console: [ 'error', { allow: [ 'error' ] } ] */

/**
 * External dependencies.
 */
import { values } from 'lodash';
import { select } from '@wordpress/data';
import { setLocaleData } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import initializeMonitors from './monitors';
import isGutenberg from './utils/is-gutenberg';
import { renderContainer } from './containers/helpers';

/**
 * The internal dependencies.
 */
import './store';
import './fields';
import './containers';

/**
 * Sets the locale data for the package type
 */
setLocaleData( window.cf.config.locale, 'carbon-fields-ui' );

/**
 * Determines the rendering context.
 *
 * @type {string}
 */
const context = isGutenberg() ? 'gutenberg' : 'classic';

/**
 * Abracadabra! Poof! Containers everywhere ...
 */
initializeMonitors( context );

values( select( 'carbon-fields/metaboxes' ).getContainers() )
	.forEach( ( container ) => renderContainer( container, context ) );
