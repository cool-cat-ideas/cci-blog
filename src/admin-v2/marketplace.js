import { useCallback, useEffect, useRef, useState } from 'react';
import { pluginData } from './api';

const inFlightRequests = new Map();

export function fetchMarketplaceJson( endpoint ) {
	if ( inFlightRequests.has( endpoint ) ) {
		return inFlightRequests.get( endpoint );
	}

	const request = fetch( endpoint, {
		credentials: 'same-origin',
		headers: {
			Accept: 'application/json',
			'X-WP-Nonce': pluginData.nonce || '',
		},
	} ).then( ( response ) => {
		if ( ! response.ok ) {
			throw new Error( `Marketplace request failed (${ response.status }).` );
		}

		return response.json();
	} ).finally( () => {
		inFlightRequests.delete( endpoint );
	} );

	inFlightRequests.set( endpoint, request );

	return request;
}

export function useMarketplaceJson( endpoint, { enabled = true, retryDelay = pluginData.marketplaceRetryDelay || 120000 } = {} ) {
	const initialData = initialMarketplaceDataForEndpoint( endpoint );
	const [ data, setData ] = useState( initialData );
	const [ loading, setLoading ] = useState( Boolean( endpoint && enabled && ! initialData ) );
	const [ error, setError ] = useState( '' );
	const [ retrying, setRetrying ] = useState( false );
	const timerRef = useRef( null );

	const clearRetry = useCallback( () => {
		if ( timerRef.current ) {
			window.clearTimeout( timerRef.current );
			timerRef.current = null;
		}
	}, [] );

	const load = useCallback(
		( { silent = false, force = false } = {} ) => {
			if ( ! endpoint || ! enabled ) {
				setLoading( false );
				return Promise.resolve( null );
			}

			const url = force ? withQueryArg( endpoint, 'force', '1' ) : endpoint;

			if ( ! silent ) {
				setLoading( true );
			}

			setRetrying( silent );

			return fetchMarketplaceJson( url )
				.then( ( response ) => {
					const feedError =
						( response?._meta?.stale || response?._meta?.ok === false ) && response?._meta?.error
							? response._meta.error
							: '';

					setData( response || null );
					clearRetry();
					setError( feedError );

					if ( feedError ) {
						timerRef.current = window.setTimeout( () => {
							load( { silent: true, force: true } );
						}, retryDelay );
					}

					return response;
				} )
				.catch( ( requestError ) => {
					setData( ( currentData ) => ( silent && currentData ? currentData : null ) );
					setError( requestError.message || 'Marketplace request failed.' );
					timerRef.current = window.setTimeout( () => {
						load( { silent: true, force: true } );
					}, retryDelay );
					return null;
				} )
				.finally( () => {
					if ( ! silent ) {
						setLoading( false );
					}
					setRetrying( false );
				} );
		},
		[ clearRetry, enabled, endpoint, retryDelay ]
	);

	useEffect( () => {
		load( { silent: Boolean( initialData ) } );

		return clearRetry;
	}, [ clearRetry, initialData, load ] );

	const refresh = useCallback( () => {
		clearRetry();
		return load( { force: true } );
	}, [ clearRetry, load ] );

	return { data, loading, error, retrying, refresh };
}

function initialMarketplaceDataForEndpoint( endpoint ) {
	if ( ! endpoint || ! pluginData.marketplaceInitialFeed ) {
		return null;
	}

	const knownEndpoints = [
		pluginData.marketplaceFeedEndpoint,
		pluginData.newsEndpoint,
		pluginData.templateStoreEndpoint,
		pluginData.extensionStoreEndpoint,
		pluginData.integrationStoreEndpoint,
	].filter( Boolean );

	if ( knownEndpoints.some( ( knownEndpoint ) => sameEndpoint( knownEndpoint, endpoint ) ) ) {
		return pluginData.marketplaceInitialFeed;
	}

	return null;
}

function sameEndpoint( firstEndpoint, secondEndpoint ) {
	try {
		const firstUrl = new URL( firstEndpoint, window.location.origin );
		const secondUrl = new URL( secondEndpoint, window.location.origin );
		firstUrl.searchParams.delete( 'force' );
		secondUrl.searchParams.delete( 'force' );

		return firstUrl.toString() === secondUrl.toString();
	} catch ( error ) {
		return firstEndpoint === secondEndpoint;
	}
}

function withQueryArg( endpoint, key, value ) {
	try {
		const url = new URL( endpoint, window.location.origin );
		url.searchParams.set( key, value );

		return url.toString();
	} catch ( error ) {
		const separator = endpoint.includes( '?' ) ? '&' : '?';

		return `${ endpoint }${ separator }${ encodeURIComponent( key ) }=${ encodeURIComponent( value ) }`;
	}
}

export function collectionFromResponse( response, keys = [] ) {
	const roots = [
		response,
		response?.data,
		response?.product,
		response?.catalog,
		response?.payload,
		response?.result,
	].filter( Boolean );
	const collectionKeys = [ ...keys, 'docs', 'items', 'results', 'records' ];

	for ( const root of [ ...roots ] ) {
		for ( const key of keys ) {
			if ( root?.[ key ] && typeof root[ key ] === 'object' && ! Array.isArray( root[ key ] ) ) {
				roots.push( root[ key ] );
			}
		}
	}

	for ( const root of roots ) {
		if ( Array.isArray( root ) ) {
			return root;
		}

		for ( const key of collectionKeys ) {
			if ( Array.isArray( root?.[ key ] ) ) {
				return root[ key ];
			}
		}
	}

	return [];
}

export function readableValue( value, fallback = '' ) {
	if ( value === null || value === undefined || value === '' ) {
		return fallback;
	}

	if ( typeof value === 'string' || typeof value === 'number' ) {
		return String( value );
	}

	if ( Array.isArray( value ) ) {
		return value.map( ( item ) => readableValue( item ) ).filter( Boolean ).join( ', ' ) || fallback;
	}

	if ( typeof value === 'object' ) {
		return readableValue(
			value.rendered ||
				value.label ||
				value.title ||
				value.name ||
				value.value ||
				value.text ||
				value.pl ||
				value.en,
			fallback
		);
	}

	return fallback;
}

export function normalizeTags( tags ) {
	if ( ! Array.isArray( tags ) ) {
		return [];
	}

	return tags
		.map( ( tag ) => readableValue( tag?.title || tag?.name || tag?.label || tag?.value || tag ) )
		.filter( Boolean );
}

export function mediaUrl( value ) {
	if ( ! value ) {
		return '';
	}

	if ( typeof value === 'string' ) {
		return value;
	}

	if ( Array.isArray( value ) ) {
		return mediaUrl( value[ 0 ] );
	}

	if ( typeof value === 'object' ) {
		return (
			value.url ||
			value.thumbnailURL ||
			value.filename ||
			value.image?.url ||
			value.media?.url ||
			value.sizes?.card?.url ||
			value.sizes?.medium?.url ||
			value.sizes?.thumbnail?.url ||
			''
		);
	}

	return '';
}

export function productUrl( item ) {
	return readableValue(
		item?.url ||
			item?.urls?.marketplace ||
			item?.urls?.product ||
			item?.urls?.demo ||
			item?.urls?.download ||
			item?.urls?.documentation ||
			item?.productUrl ||
			item?.permalink ||
			item?.shopUrl ||
			item?.href ||
			item?.link ||
			item?.links?.product ||
			item?.product?.url
	);
}

export function isPaidProduct( item ) {
	const plan = readableValue( item?.plan || item?.access || item?.license || item?.type || item?.availability ).toLowerCase();

	return Boolean(
		item?.pro ||
			item?.premium ||
			item?.isPro ||
			item?.isPremium ||
			item?.requiresPro ||
			item?.requiresLicense ||
			item?.price ||
			item?.priceFormatted ||
			item?.regularPrice ||
			item?.pricing?.price ||
			plan.includes( 'pro' ) ||
			plan.includes( 'paid' ) ||
			plan.includes( 'included_pro' ) ||
			plan.includes( 'premium' )
	);
}
