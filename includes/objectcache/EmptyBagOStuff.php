<?php
/**
 * Dummy object caching.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Cache
 */

/**
 * A BagOStuff object with no objects in it. Used to provide a no-op object to calling code.
 *
 * @ingroup Cache
 */
class EmptyBagOStuff extends BagOStuff {

	/**
	 * @param string $key
	 * @param mixed $casToken [optional]
	 * @return bool
	 */
	public function get( $key, &$casToken = null ) {
		return false;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int $exp
	 * @return bool
	 */
	public function set( $key, $value, $exp = 0 ) {
		return true;
	}

	/**
	 * @param mixed $casToken
	 * @param string $key
	 * @param mixed $value
	 * @param int $exp
	 * @return bool
	 */
	protected function cas( $casToken, $key, $value, $exp = 0 ) {
		return true;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function delete( $key ) {
		return true;
	}

	/**
	 * @param string $key
	 * @param callable $callback Callback method to be executed
	 * @param int $exptime Either an interval in seconds or a unix timestamp for expiry
	 * @param int $attempts The amount of times to attempt a merge in case of failure
	 * @return bool Success
	 */
	public function merge( $key, $callback, $exptime = 0, $attempts = 10 ) {
		return true;
	}
}
