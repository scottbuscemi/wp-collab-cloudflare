import { addFilter } from '@wordpress/hooks';
import * as encoding from 'lib0/encoding';
import * as decoding from 'lib0/decoding';
import * as awarenessProtocol from 'y-protocols/awareness';

const MSG_SYNC = 0;
const MSG_AWARENESS = 1;
const SYNC_STEP1 = 0;
const SYNC_STEP2 = 1;
const SYNC_UPDATE = 2;

const config = window.pantheonRtc || {};

if ( config.wsUrl ) {
	addFilter(
		'sync.providers',
		'pantheon-rtc/websocket-provider',
		() => {
			return [
				async ( { objectType, objectId, ydoc, awareness } ) => {
					// Grab Yjs from wp-sync's public exports.
					const Y = window.wp?.sync?.Y;
					if ( ! Y ) {
						// eslint-disable-next-line no-console
						console.error( 'Pantheon RTC: wp.sync.Y not found — wp-sync may not be loaded.' );
						return { destroy: () => {}, on: () => {} };
					}

					const room = `${ objectType.replace( /\//g, '-' ) }-${
						objectId ?? 'collection'
					}`;
					const wsUrl = `${ config.wsUrl }/parties/collaboration/${ room }`;

					let ws;
					let connected = false;
					let destroyed = false;
					const statusCallbacks = [];

					function sendMsg( buf ) {
						if ( ws && ws.readyState === WebSocket.OPEN ) {
							ws.send( buf );
						}
					}

					// -- Sync protocol helpers --

					function sendSyncStep1() {
						const enc = encoding.createEncoder();
						encoding.writeVarUint( enc, MSG_SYNC );
						encoding.writeVarUint( enc, SYNC_STEP1 );
						encoding.writeVarUint8Array(
							enc,
							Y.encodeStateVector( ydoc )
						);
						sendMsg( encoding.toUint8Array( enc ) );
					}

					function sendSyncStep2( sv ) {
						const enc = encoding.createEncoder();
						encoding.writeVarUint( enc, MSG_SYNC );
						encoding.writeVarUint( enc, SYNC_STEP2 );
						encoding.writeVarUint8Array(
							enc,
							Y.encodeStateAsUpdate( ydoc, sv )
						);
						sendMsg( encoding.toUint8Array( enc ) );
					}

					function sendUpdate( update ) {
						const enc = encoding.createEncoder();
						encoding.writeVarUint( enc, MSG_SYNC );
						encoding.writeVarUint( enc, SYNC_UPDATE );
						encoding.writeVarUint8Array( enc, update );
						sendMsg( encoding.toUint8Array( enc ) );
					}

					function handleSyncMessage( dec ) {
						const syncType = decoding.readVarUint( dec );
						switch ( syncType ) {
							case SYNC_STEP1: {
								const sv = decoding.readVarUint8Array( dec );
								sendSyncStep2( sv );
								break;
							}
							case SYNC_STEP2:
							case SYNC_UPDATE: {
								const update = decoding.readVarUint8Array( dec );
								Y.applyUpdate( ydoc, update, 'ws-provider' );
								break;
							}
						}
					}

					// -- Awareness --

					function sendAwareness( changedClients ) {
						const enc = encoding.createEncoder();
						encoding.writeVarUint( enc, MSG_AWARENESS );
						encoding.writeVarUint8Array(
							enc,
							awarenessProtocol.encodeAwarenessUpdate(
								awareness,
								changedClients
							)
						);
						sendMsg( encoding.toUint8Array( enc ) );
					}

					// -- Doc & awareness event handlers --

					const onDocUpdate = ( update, origin ) => {
						if ( origin !== 'ws-provider' ) {
							sendUpdate( update );
						}
					};

					const onAwarenessUpdate = ( { added, updated, removed } ) => {
						const changed = added.concat( updated, removed );
						sendAwareness( changed );
					};

					ydoc.on( 'update', onDocUpdate );
					if ( awareness ) {
						awareness.on( 'update', onAwarenessUpdate );
					}

					// -- WebSocket connection --

					function connect() {
						if ( destroyed ) {
							return;
						}
						ws = new WebSocket( wsUrl );
						ws.binaryType = 'arraybuffer';

						ws.addEventListener( 'open', () => {
							connected = true;
							statusCallbacks.forEach( ( cb ) =>
								cb( { status: 'connected' } )
							);
							sendSyncStep1();
							if ( awareness ) {
								sendAwareness( [
									awareness.clientID,
								] );
							}
						} );

						ws.addEventListener( 'message', ( event ) => {
							const data = new Uint8Array( event.data );
							const dec = decoding.createDecoder( data );
							const msgType = decoding.readVarUint( dec );

							switch ( msgType ) {
								case MSG_SYNC:
									handleSyncMessage( dec );
									break;
								case MSG_AWARENESS:
									if ( awareness ) {
										const update =
											decoding.readVarUint8Array( dec );
										awarenessProtocol.applyAwarenessUpdate(
											awareness,
											update,
											'ws-provider'
										);
									}
									break;
							}
						} );

						ws.addEventListener( 'close', () => {
							connected = false;
							statusCallbacks.forEach( ( cb ) =>
								cb( { status: 'disconnected' } )
							);
							if ( ! destroyed ) {
								setTimeout( connect, 2000 );
							}
						} );

						ws.addEventListener( 'error', () => {
							ws.close();
						} );
					}

					connect();

					return {
						destroy: () => {
							destroyed = true;
							ydoc.off( 'update', onDocUpdate );
							if ( awareness ) {
								awareness.off( 'update', onAwarenessUpdate );
								awarenessProtocol.removeAwarenessStates(
									awareness,
									[ awareness.clientID ],
									'provider-destroy'
								);
							}
							if ( ws ) {
								ws.close();
							}
						},
						on: ( event, callback ) => {
							if ( event === 'status' ) {
								statusCallbacks.push( callback );
								if ( connected ) {
									callback( { status: 'connected' } );
								}
							}
						},
					};
				},
			];
		}
	);
}
