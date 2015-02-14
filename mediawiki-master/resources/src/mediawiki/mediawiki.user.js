/**
 * @class mw.user
 * @singleton
 */
( function ( mw, $ ) {
	var user, i,
		deferreds = {},
		byteToHex = [],
		// Extend the skeleton mw.user from mediawiki.js
		// This is kind of ugly but we're stuck with this for b/c reasons
		options = mw.user.options || new mw.Map(),
		tokens = mw.user.tokens || new mw.Map();

	// Maps for number -> hex string conversion (with padding)
	// idea from: https://github.com/broofa/node-uuid/blob/master/uuid.js
	for ( i = 0; i < 256; i++ ) {
		byteToHex[i] = (i + 0x100).toString(16).substr(1);
	}

	/**
	 * Get the current user's groups or rights
	 *
	 * @private
	 * @param {string} info One of 'groups' or 'rights'
	 * @return {jQuery.Promise}
	 */
	function getUserInfo( info ) {
		var api;
		if ( !deferreds[info] ) {

			deferreds.rights = $.Deferred();
			deferreds.groups = $.Deferred();

			api = new mw.Api();
			api.get( {
				action: 'query',
				meta: 'userinfo',
				uiprop: 'rights|groups'
			} ).always( function ( data ) {
				var rights, groups;
				if ( data.query && data.query.userinfo ) {
					rights = data.query.userinfo.rights;
					groups = data.query.userinfo.groups;
				}
				deferreds.rights.resolve( rights || [] );
				deferreds.groups.resolve( groups || [] );
			} );

		}

		return deferreds[info].promise();
	}

	mw.user = user = {
		options: options,
		tokens: tokens,

		/**
		 * Generate a random user session ID
		 * This information would potentially be stored in a cookie to identify a user during a
		 * session or series of sessions. Its uniqueness should
		 * not be depended on unless the browser supports the crypto API.
		 *
		 * Known problems with Math.random():
		 * Using the Math.random function we have seen sets
		 * with 1% of non uniques among 200.000 values with Safari providing most of these.
		 * Given the prevalence of Safari in mobile the percentage of duplicates in
		 * mobile usages of this code is probably higher.
		 *
		 * Rationale:
		 * We need about 64 bits to make sure that probability of collision
		 * on 500 million (5*10^8) is <= 1%
		 * See: https://en.wikipedia.org/wiki/Birthday_problem#Probability_table
		 *
		 * @return {string} 64 bit integer in hex format, padded
		 */
		generateRandomSessionId: function () {
			/*jshint bitwise:false */
			var rnds, i, r, cryptoObj, hexRnds = new Array( 8 );
			cryptoObj = window.crypto || window.msCrypto; // for IE 11

			if ( cryptoObj ) {
				// We fill an array with 8 random values, each of which is 8 bits.
				// note that rnds is an array-like object not a true array
				rnds = new Uint8Array( 8 );
				cryptoObj.getRandomValues( rnds );
			} else {
				rnds = new Array( 8 );
				// From: https://github.com/broofa/node-uuid/blob/master/uuid.js
				for ( i = 0, r; i < 8; i++ ) {
					if ( ( i & 0x03 ) === 0 ) {
						r = Math.random() * 0x100000000;
					}
					rnds[i] = r >>> ( ( i & 0x03 ) << 3 ) & 0xff;
				}
			}
			// convert to hex using byteToHex that already contains padding
			for ( i = 0; i < rnds.length; i++ ) {
				hexRnds[i] = byteToHex[rnds[i]];
			}

			// concatenation of two random integers with entrophy n and m
			// returns a string with entrophy n+m if those strings are independent
			return hexRnds.join( '' );
		},

		/**
		 * Get the current user's database id
		 *
		 * Not to be confused with #id.
		 *
		 * @return {number} Current user's id, or 0 if user is anonymous
		 */
		getId: function () {
			return mw.config.get( 'wgUserId', 0 );
		},

		/**
		 * Get the current user's name
		 *
		 * @return {string|null} User name string or null if user is anonymous
		 */
		getName: function () {
			return mw.config.get( 'wgUserName' );
		},

		/**
		 * Get date user registered, if available
		 *
		 * @return {Date|boolean|null} Date user registered, or false for anonymous users, or
		 *  null when data is not available
		 */
		getRegistration: function () {
			var registration = mw.config.get( 'wgUserRegistration' );
			if ( user.isAnon() ) {
				return false;
			}
			if ( registration === null ) {
				// Information may not be available if they signed up before
				// MW began storing this.
				return null;
			}
			return new Date( registration );
		},

		/**
		 * Whether the current user is anonymous
		 *
		 * @return {boolean}
		 */
		isAnon: function () {
			return user.getName() === null;
		},

		/**
		 * Get an automatically generated random ID (stored in a session cookie)
		 *
		 * This ID is ephemeral for everyone, staying in their browser only until they close
		 * their browser.
		 *
		 * @return {string} Random session ID
		 */
		sessionId: function () {
			var sessionId = $.cookie( 'mediaWiki.user.sessionId' );
			if ( sessionId === undefined || sessionId === null ) {
				sessionId = user.generateRandomSessionId();
				$.cookie( 'mediaWiki.user.sessionId', sessionId, { expires: null, path: '/' } );
			}
			return sessionId;
		},

		/**
		 * Get the current user's name or the session ID
		 *
		 * Not to be confused with #getId.
		 *
		 * @return {string} User name or random session ID
		 */
		id: function () {
			return user.getName() || user.sessionId();
		},

		/**
		 * Get the user's bucket (place them in one if not done already)
		 *
		 *     mw.user.bucket( 'test', {
		 *         buckets: { ignored: 50, control: 25, test: 25 },
		 *         version: 1,
		 *         expires: 7
		 *     } );
		 *
		 * @deprecated since 1.23
		 * @param {string} key Name of bucket
		 * @param {Object} options Bucket configuration options
		 * @param {Object} options.buckets List of bucket-name/relative-probability pairs (required,
		 *  must have at least one pair)
		 * @param {number} [options.version=0] Version of bucket test, changing this forces
		 *  rebucketing
		 * @param {number} [options.expires=30] Length of time (in days) until the user gets
		 *  rebucketed
		 * @return {string} Bucket name - the randomly chosen key of the `options.buckets` object
		 */
		bucket: function ( key, options ) {
			var cookie, parts, version, bucket,
				range, k, rand, total;

			options = $.extend( {
				buckets: {},
				version: 0,
				expires: 30
			}, options || {} );

			cookie = $.cookie( 'mediaWiki.user.bucket:' + key );

			// Bucket information is stored as 2 integers, together as version:bucket like: "1:2"
			if ( typeof cookie === 'string' && cookie.length > 2 && cookie.indexOf( ':' ) !== -1 ) {
				parts = cookie.split( ':' );
				if ( parts.length > 1 && Number( parts[0] ) === options.version ) {
					version = Number( parts[0] );
					bucket = String( parts[1] );
				}
			}

			if ( bucket === undefined ) {
				if ( !$.isPlainObject( options.buckets ) ) {
					throw new Error( 'Invalid bucket. Object expected for options.buckets.' );
				}

				version = Number( options.version );

				// Find range
				range = 0;
				for ( k in options.buckets ) {
					range += options.buckets[k];
				}

				// Select random value within range
				rand = Math.random() * range;

				// Determine which bucket the value landed in
				total = 0;
				for ( k in options.buckets ) {
					bucket = k;
					total += options.buckets[k];
					if ( total >= rand ) {
						break;
					}
				}

				$.cookie(
					'mediaWiki.user.bucket:' + key,
					version + ':' + bucket,
					{ path: '/', expires: Number( options.expires ) }
				);
			}

			return bucket;
		},

		/**
		 * Get the current user's groups
		 *
		 * @param {Function} [callback]
		 * @return {jQuery.Promise}
		 */
		getGroups: function ( callback ) {
			return getUserInfo( 'groups' ).done( callback );
		},

		/**
		 * Get the current user's rights
		 *
		 * @param {Function} [callback]
		 * @return {jQuery.Promise}
		 */
		getRights: function ( callback ) {
			return getUserInfo( 'rights' ).done( callback );
		}
	};

}( mediaWiki, jQuery ) );
