<?php
/**
 * @package     Redcore
 * @subpackage  Api
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

/**
 * Transform api output
 *
 * @package     Redcore
 * @subpackage  Api
 * @since       1.4
 */
class JApiHalTransformFile extends JApiHalTransformBase
{
	/**
	 * Method to transform an internal representation to an external one.
	 *
	 * @param   mixed  $definition  Field definition.
	 *
	 * @return mixed Transformed value.
	 */
	public static function toExternal($definition)
	{
		return $definition;
	}

	/**
	 * Method to transform an external representation to an internal one.
	 *
	 * @param   mixed  $definition  Field definition.
	 *
	 * @return mixed Transformed value.
	 */
	public static function toInternal($definition)
	{
		// This is already converted to file type so we are returning it as is
		if (is_array($definition))
		{
			return $definition;
		}

		return !empty($_FILES[$definition]) ? $_FILES[$definition] : false;
	}
}
